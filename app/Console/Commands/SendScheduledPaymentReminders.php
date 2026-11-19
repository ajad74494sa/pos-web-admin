<?php

namespace App\Console\Commands;

use App\PaymentReminderSchedule;
use App\PaymentReminderScheduleRun;
use App\Utils\GroupedPaymentReminderUtil;
use App\Utils\PaymentReminderOtpUtil;
use Carbon\Carbon;
use DateTimeZone;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class SendScheduledPaymentReminders extends Command
{
    protected $signature = 'pos:sendScheduledPaymentReminders';

    protected $description = 'Send scheduled grouped WhatsApp due reminders and email run summaries';

    protected $groupedPaymentReminderUtil;

    protected $paymentReminderOtpUtil;

    public function __construct(GroupedPaymentReminderUtil $groupedPaymentReminderUtil, PaymentReminderOtpUtil $paymentReminderOtpUtil)
    {
        parent::__construct();
        $this->groupedPaymentReminderUtil = $groupedPaymentReminderUtil;
        $this->paymentReminderOtpUtil = $paymentReminderOtpUtil;
    }

    public function handle()
    {
        ini_set('max_execution_time', 0);
        ini_set('memory_limit', '512M');

        try {
            $this->paymentReminderOtpUtil->expireLegacyChallenges();
        } catch (\Throwable $e) {
            \Log::error('Could not expire legacy payment reminder OTP challenges: '.$e->getMessage());
        }

        $schedules = PaymentReminderSchedule::where('is_active', true)->with('business')->get();
        foreach ($schedules as $schedule) {
            try {
                $occurrence = $this->dueOccurrence($schedule);
                if (empty($occurrence)) {
                    continue;
                }

                $this->processOccurrence($schedule, $occurrence);
            } catch (\Throwable $e) {
                \Log::error('Scheduled payment reminder failed for schedule '.$schedule->id.': '.$e->getMessage());
            }
        }

        return self::SUCCESS;
    }

    private function dueOccurrence(PaymentReminderSchedule $schedule)
    {
        $timezone = new DateTimeZone($schedule->timezone);
        $now = Carbon::now($timezone);

        if ($schedule->schedule_type === 'one_time') {
            $date = $schedule->scheduled_date instanceof Carbon
                ? $schedule->scheduled_date->format('Y-m-d')
                : (string) $schedule->scheduled_date;
            $scheduled_at = Carbon::parse($date.' '.$schedule->send_time, $timezone);
            if ($now->lt($scheduled_at)) {
                return null;
            }

            return [
                'run_key' => 'once',
                'scheduled_at' => $scheduled_at,
            ];
        }

        if ($schedule->schedule_type === 'monthly') {
            $day = min((int) $schedule->day_of_month, $now->daysInMonth);
            $date = $now->format('Y-m-').str_pad((string) $day, 2, '0', STR_PAD_LEFT);
            $scheduled_at = Carbon::parse($date.' '.$schedule->send_time, $timezone);
            $created_at = $schedule->created_at->copy()->setTimezone($timezone);
            if ($now->lt($scheduled_at) || $scheduled_at->lt($created_at)) {
                return null;
            }

            return [
                'run_key' => $now->format('Y-m'),
                'scheduled_at' => $scheduled_at,
            ];
        }

        return null;
    }

    private function processOccurrence(PaymentReminderSchedule $schedule, array $occurrence)
    {
        $run = PaymentReminderScheduleRun::where('schedule_id', $schedule->id)
            ->where('run_key', $occurrence['run_key'])
            ->first();

        if (empty($run)) {
            $run = PaymentReminderScheduleRun::create([
                'schedule_id' => $schedule->id,
                'run_key' => $occurrence['run_key'],
                'scheduled_for' => $occurrence['scheduled_at']->copy()->setTimezone(config('app.timezone')),
                'status' => 'awaiting_otp',
                'started_at' => Carbon::now(),
            ]);
        }

        if ($run->status === 'awaiting_otp') {
            if (! $this->paymentReminderOtpUtil->isSchedulerEnabled($schedule->business_id)) {
                $this->finishWithoutSending($schedule, $run, $occurrence['scheduled_at'], 'The reminder scheduler is off. No messages were sent.');

                return;
            }

            $this->ensureRunChallenge($schedule, $run, $occurrence['scheduled_at']);

            return;
        }

        if ($run->status !== 'confirmed') {
            return;
        }

        if (! $schedule->is_active || ! $this->paymentReminderOtpUtil->isSchedulerEnabled($schedule->business_id)) {
            $this->finishWithoutSending($schedule, $run, $occurrence['scheduled_at'], 'The scheduler or schedule was turned off before confirmation. No messages were sent.');

            return;
        }

        $claimed = PaymentReminderScheduleRun::where('id', $run->id)
            ->where('status', 'confirmed')
            ->update(['status' => 'running', 'updated_at' => Carbon::now()]);
        if ($claimed === 1) {
            $run->refresh();
            $this->processRun($schedule, $run, $occurrence['scheduled_at']);
        }
    }

    private function ensureRunChallenge(PaymentReminderSchedule $schedule, PaymentReminderScheduleRun $run, Carbon $scheduled_at)
    {
        $challenge = $run->otpChallenge;
        if (empty($challenge)) {
            $result = $this->paymentReminderOtpUtil->requestChallenge(
                $schedule->business,
                'grouped_run',
                (string) $run->id,
                null,
                $run->id
            );
            $challenge = $result['challenge'];
            if (! $result['success']) {
                $this->finishWithoutSending($schedule, $run, $scheduled_at, $result['message'], 'failed');

                return;
            }
        }

        if ($challenge->status === 'pending' && $challenge->expires_at->isFuture()) {
            return;
        }

        if ($challenge->status === 'pending') {
            $challenge->status = 'expired';
            $challenge->save();
        }

        if ($challenge->status === 'confirmed') {
            $run->status = 'confirmed';
            $run->save();

            return;
        }

        $message = $challenge->status === 'expired'
            ? 'The OTP expired without confirmation. No messages were sent.'
            : ($challenge->status === 'cancelled'
                ? 'The OTP was cancelled before confirmation. No messages were sent.'
                : 'The OTP could not be delivered or confirmed. No messages were sent.');
        $this->finishWithoutSending($schedule, $run, $scheduled_at, $message);
    }

    private function finishWithoutSending(PaymentReminderSchedule $schedule, PaymentReminderScheduleRun $run, Carbon $scheduled_at, $message, $status = 'skipped')
    {
        if (in_array($run->status, ['completed', 'partial', 'failed', 'skipped'], true)) {
            return;
        }

        $run->update([
            'status' => $status,
            'failed_count' => 1,
            'report_details' => json_encode([
                'error' => $message,
                'failure_messages' => [$message],
                'customers' => [],
            ], JSON_UNESCAPED_UNICODE),
            'finished_at' => Carbon::now(),
        ]);

        if ($schedule->schedule_type === 'one_time') {
            $schedule->is_active = false;
            $schedule->save();
        }

        $this->emailSummary($schedule, $run, [
            'eligible' => 0,
            'sent' => 0,
            'failure_messages' => [$message],
            'error' => $message,
        ], $scheduled_at, 1);
    }

    private function processRun(PaymentReminderSchedule $schedule, PaymentReminderScheduleRun $run, Carbon $scheduled_at)
    {
        try {
            $result = $this->groupedPaymentReminderUtil->send($schedule->business_id, null, 'all', true);
        } catch (\Throwable $e) {
            \Log::error('Scheduled reminder run '.$run->id.' failed: '.$e->getMessage());
            $result = [
                'sent' => 0,
                'failed' => 1,
                'eligible' => 0,
                'failure_messages' => [$e->getMessage()],
                'customers' => [],
                'error' => 'The reminder run could not be completed.',
                'error_code' => 'run_error',
            ];
        }

        $no_due_customers = ($result['error_code'] ?? null) === 'no_due_customers';
        $failed_count = (int) $result['failed'];
        if (! empty($result['error']) && ! $no_due_customers && $failed_count === 0) {
            $failed_count = 1;
        }

        $status = $failed_count === 0 ? 'completed' : ((int) $result['sent'] > 0 ? 'partial' : 'failed');
        $run->update([
            'status' => $status,
            'customer_count' => (int) $result['eligible'],
            'submitted_count' => (int) $result['sent'],
            'failed_count' => $failed_count,
            'report_details' => json_encode([
                'error' => $result['error'],
                'failure_messages' => $result['failure_messages'],
                'customers' => $result['customers'],
            ], JSON_UNESCAPED_UNICODE),
            'finished_at' => Carbon::now(),
        ]);

        if ($schedule->schedule_type === 'one_time') {
            $schedule->is_active = false;
            $schedule->save();
        }

        $this->emailSummary($schedule, $run, $result, $scheduled_at, $failed_count);
    }

    private function emailSummary(PaymentReminderSchedule $schedule, PaymentReminderScheduleRun $run, array $result, Carbon $scheduled_at, $failed_count)
    {
        $business_name = $schedule->business->name;
        $scheduled_local = $scheduled_at->copy()->setTimezone($schedule->timezone);
        $lines = [
            'Scheduled WhatsApp due reminder summary',
            'Business: '.$business_name,
            'Scheduled for: '.$scheduled_local->format('Y-m-d H:i').' ('.$schedule->timezone.')',
            'Customers with outstanding invoices: '.(int) $result['eligible'],
            'Submitted or queued: '.(int) $result['sent'],
            'Failed or not confirmed: '.(int) $failed_count,
        ];

        if (! empty($result['error'])) {
            $lines[] = 'Run note: '.$result['error'];
        }
        if (! empty($result['failure_messages'])) {
            $lines[] = '';
            $lines[] = 'Failures:';
            foreach ($result['failure_messages'] as $message) {
                $lines[] = '- '.str_replace(["\r", "\n"], ' ', $message);
            }
        }

        $lines[] = '';
        $lines[] = 'Submitted or queued means the application accepted the request; it does not confirm final WhatsApp delivery.';

        try {
            Mail::raw(implode("\n", $lines), function ($message) use ($schedule) {
                $message->to($schedule->report_email)
                    ->subject('Scheduled WhatsApp Due Reminder Summary');
            });
            $run->report_sent = true;
            $run->report_error = null;
        } catch (\Throwable $e) {
            \Log::error('Could not email payment reminder report for run '.$run->id.': '.$e->getMessage());
            $run->report_sent = false;
            $run->report_error = substr($e->getMessage(), 0, 1000);
        }

        $run->save();
    }
}
