<?php

namespace Tests\Feature;

use App\Business;
use App\PaymentReminderOtpChallenge;
use App\PaymentReminderSchedule;
use App\PaymentReminderScheduleRun;
use App\PaymentReminderSchedulerSetting;
use App\Utils\GroupedPaymentReminderUtil;
use App\Utils\PaymentReminderOtpUtil;
use Carbon\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Tests\TestCase;

class PaymentReminderOtpTest extends TestCase
{
    protected $original_database;

    protected function setUp(): void
    {
        parent::setUp();
        $this->original_database = config('database.default');
        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite' => [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
                'foreign_key_constraints' => true,
            ],
        ]);
        DB::purge('sqlite');
        DB::setDefaultConnection('sqlite');

        Schema::create('business', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
            $table->text('sms_settings')->nullable();
            $table->string('time_zone')->nullable();
            $table->timestamps();
        });

        $scheduler_migration = require base_path('database/migrations/2026_11_19_000001_create_payment_reminder_schedules_tables.php');
        $scheduler_migration->up();
        $authorization_migration = require base_path('database/migrations/2026_11_19_000002_create_payment_reminder_authorization_tables.php');
        $authorization_migration->up();

        Schema::create('notification_templates', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('business_id');
            $table->string('template_for');
            $table->boolean('auto_send')->default(false);
            $table->boolean('auto_send_sms')->default(false);
            $table->boolean('auto_send_wa_notif')->default(false);
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        DB::purge('sqlite');
        config(['database.default' => $this->original_database]);
        DB::setDefaultConnection($this->original_database);
        parent::tearDown();
    }

    public function test_scheduler_activation_requires_a_valid_otp()
    {
        Http::fake([
            'https://openwa.test/*' => Http::response(['success' => true], 200),
        ]);
        $business = $this->createBusiness();
        $otp_util = app(PaymentReminderOtpUtil::class);
        $challenge_result = $otp_util->requestChallenge($business, 'enable_scheduler', 'enable-test', 1);
        $challenge = $challenge_result['challenge'];

        $code = null;
        Http::assertSent(function ($request) use (&$code) {
            $text = $request['text'];
            preg_match('/code is ([0-9]{6})\\./', $text, $matches);
            $code = $matches[1] ?? null;

            return $request['chatId'] === '15551234567@c.us';
        });

        $this->assertTrue($challenge_result['success']);
        $this->assertNotEmpty($code);
        $this->assertNotSame($code, $challenge->code_hash);
        $this->assertFalse($otp_util->isSchedulerEnabled($business->id));

        $wrong_code_result = $otp_util->confirm($business->id, $challenge->id, $code === '000000' ? '000001' : '000000');
        $this->assertFalse($wrong_code_result['success']);
        $this->assertSame(1, $challenge->fresh()->attempts, json_encode($wrong_code_result));

        $confirm_result = $otp_util->confirm($business->id, $challenge->id, $code);
        $this->assertTrue($confirm_result['success']);
        $this->assertTrue($otp_util->isSchedulerEnabled($business->id));

        $disable_result = $otp_util->requestChallenge($business, 'disable_scheduler', 'disable-test', 1);
        $last_request = Http::recorded()->last()[0];
        preg_match('/code is ([0-9]{6})\\./', $last_request['text'], $disable_matches);
        $disable_code = $disable_matches[1] ?? null;
        $this->assertNotEmpty($disable_code);

        $disable_confirm_result = $otp_util->confirm($business->id, $disable_result['challenge']->id, $disable_code);
        $this->assertTrue($disable_confirm_result['success']);
        $this->assertFalse($otp_util->isSchedulerEnabled($business->id));
    }

    public function test_failed_openwa_otp_delivery_falls_back_to_email()
    {
        Http::fake([
            'https://openwa.test/*' => Http::response(['message' => 'unavailable'], 503),
        ]);
        Mail::shouldReceive('raw')->once()->andReturn(null);
        $business = $this->createBusiness();

        $result = app(PaymentReminderOtpUtil::class)->requestChallenge(
            $business,
            'enable_scheduler',
            'email-fallback-test',
            1
        );

        $this->assertTrue($result['success']);
        $this->assertSame('email', $result['challenge']->delivered_via);
        $this->assertSame('pending', $result['challenge']->status);
    }

    public function test_grouped_scheduled_run_waits_for_otp_before_sending_customer_reminders()
    {
        Carbon::setTestNow(Carbon::parse('2026-11-19 09:00:00', 'UTC'));
        Http::fake([
            'https://openwa.test/*' => Http::response(['success' => true], 200),
        ]);
        $grouped_reminder_util = $this->createMock(GroupedPaymentReminderUtil::class);
        $grouped_reminder_util->expects($this->once())
            ->method('send')
            ->willReturn([
                'sent' => 1,
                'failed' => 0,
                'eligible' => 1,
                'failure_messages' => [],
                'customers' => [['name' => 'Test customer', 'status' => 'submitted']],
                'error' => null,
                'error_code' => null,
            ]);
        $this->app->instance(GroupedPaymentReminderUtil::class, $grouped_reminder_util);
        $business = $this->createBusiness();
        PaymentReminderSchedulerSetting::create([
            'business_id' => $business->id,
            'is_enabled' => true,
        ]);
        PaymentReminderSchedule::create([
            'business_id' => $business->id,
            'schedule_type' => 'one_time',
            'scheduled_date' => '2026-11-18',
            'send_time' => '08:00:00',
            'timezone' => 'UTC',
            'report_email' => 'reports@example.test',
            'is_active' => true,
        ]);

        Artisan::call('pos:sendScheduledPaymentReminders');

        $run = PaymentReminderScheduleRun::firstOrFail();
        $this->assertSame('awaiting_otp', $run->status, (string) $run->report_details);
        $this->assertSame('pending', $run->otpChallenge->status);
        Http::assertSentCount(1);
        Http::assertSent(function ($request) {
            return $request['chatId'] === '15551234567@c.us'
                && strpos($request['text'], 'confirmation code') !== false;
        });

        $code = null;
        Http::assertSent(function ($request) use (&$code) {
            preg_match('/code is ([0-9]{6})\\./', $request['text'], $matches);
            $code = $matches[1] ?? null;

            return true;
        });
        $confirm_result = app(PaymentReminderOtpUtil::class)->confirm($business->id, $run->otpChallenge->id, $code);
        $this->assertTrue($confirm_result['success']);

        Mail::shouldReceive('raw')->once()->andReturn(null);
        Artisan::call('pos:sendScheduledPaymentReminders');

        $this->assertSame('completed', $run->fresh()->status);
        $this->assertSame(1, $run->fresh()->submitted_count);
    }

    public function test_legacy_daily_command_requests_otp_instead_of_sending_without_confirmation()
    {
        Http::fake([
            'https://openwa.test/*' => Http::response(['success' => true], 200),
        ]);
        $business = $this->createBusiness();
        PaymentReminderSchedulerSetting::create([
            'business_id' => $business->id,
            'is_enabled' => true,
        ]);
        DB::table('notification_templates')->insert([
            'business_id' => $business->id,
            'template_for' => 'payment_reminder',
            'auto_send' => false,
            'auto_send_sms' => true,
            'auto_send_wa_notif' => false,
        ]);

        Artisan::call('pos:autoSendPaymentReminder');

        $challenge = PaymentReminderOtpChallenge::where('purpose', 'legacy_run')->firstOrFail();
        $this->assertSame('pending', $challenge->status);
        Http::assertSentCount(1);
        Http::assertSent(function ($request) {
            return $request['chatId'] === '15551234567@c.us'
                && strpos($request['text'], 'confirmation code') !== false;
        });
    }

    private function createBusiness()
    {
        $business = new Business();
        $business->forceFill([
            'name' => 'OTP test business',
            'time_zone' => 'UTC',
            'sms_settings' => [
                'openwa_base_url' => 'https://openwa.test',
                'openwa_api_key' => 'test-key',
                'openwa_session_id' => 'test-session',
                'reminder_otp_phone' => '+15551234567',
                'reminder_otp_email' => 'admin@example.test',
            ],
        ]);
        $business->save();

        return $business;
    }
}
