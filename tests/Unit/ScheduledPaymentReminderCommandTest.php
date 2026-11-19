<?php

namespace Tests\Unit;

use App\Console\Commands\SendScheduledPaymentReminders;
use App\PaymentReminderSchedule;
use App\Utils\GroupedPaymentReminderUtil;
use App\Utils\PaymentReminderOtpUtil;
use Carbon\Carbon;
use ReflectionMethod;
use Tests\TestCase;

class ScheduledPaymentReminderCommandTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_monthly_schedule_clamps_to_the_last_day_of_the_month()
    {
        Carbon::setTestNow(Carbon::parse('2027-02-28 08:00:00', 'Asia/Riyadh'));
        $schedule = new PaymentReminderSchedule([
            'schedule_type' => 'monthly',
            'day_of_month' => 31,
            'send_time' => '08:00:00',
            'timezone' => 'Asia/Riyadh',
        ]);
        $schedule->created_at = Carbon::parse('2027-01-01 00:00:00', 'UTC');

        $occurrence = $this->dueOccurrence($schedule);

        $this->assertSame('2027-02', $occurrence['run_key']);
        $this->assertSame('2027-02-28 08:00:00', $occurrence['scheduled_at']->format('Y-m-d H:i:s'));
    }

    public function test_monthly_schedule_does_not_run_for_a_time_before_it_was_created()
    {
        Carbon::setTestNow(Carbon::parse('2027-02-28 10:00:00', 'Asia/Riyadh'));
        $schedule = new PaymentReminderSchedule([
            'schedule_type' => 'monthly',
            'day_of_month' => 28,
            'send_time' => '08:00:00',
            'timezone' => 'Asia/Riyadh',
        ]);
        $schedule->created_at = Carbon::parse('2027-02-28 09:00:00', 'Asia/Riyadh')
            ->setTimezone(config('app.timezone'));

        $this->assertNull($this->dueOccurrence($schedule));
    }

    public function test_one_time_schedule_waits_until_its_business_local_send_time()
    {
        Carbon::setTestNow(Carbon::parse('2027-02-28 07:59:00', 'Asia/Riyadh'));
        $schedule = new PaymentReminderSchedule([
            'schedule_type' => 'one_time',
            'scheduled_date' => '2027-02-28',
            'send_time' => '08:00:00',
            'timezone' => 'Asia/Riyadh',
        ]);

        $this->assertNull($this->dueOccurrence($schedule));

        Carbon::setTestNow(Carbon::parse('2027-02-28 08:00:00', 'Asia/Riyadh'));
        $occurrence = $this->dueOccurrence($schedule);

        $this->assertSame('once', $occurrence['run_key']);
        $this->assertSame('2027-02-28 08:00:00', $occurrence['scheduled_at']->format('Y-m-d H:i:s'));
    }

    private function dueOccurrence(PaymentReminderSchedule $schedule)
    {
        $command = new SendScheduledPaymentReminders(
            $this->createMock(GroupedPaymentReminderUtil::class),
            $this->createMock(PaymentReminderOtpUtil::class)
        );
        $method = new ReflectionMethod($command, 'dueOccurrence');
        $method->setAccessible(true);

        return $method->invoke($command, $schedule);
    }
}
