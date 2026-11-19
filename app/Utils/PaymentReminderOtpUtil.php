<?php

namespace App\Utils;

use App\Business;
use App\PaymentReminderOtpChallenge;
use App\PaymentReminderSchedule;
use App\PaymentReminderScheduleRun;
use App\PaymentReminderSchedulerSetting;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

class PaymentReminderOtpUtil
{
    const CODE_LIFETIME_MINUTES = 15;

    const MAX_ATTEMPTS = 5;

    protected $notificationUtil;

    public function __construct(NotificationUtil $notificationUtil)
    {
        $this->notificationUtil = $notificationUtil;
    }

    public function schedulerSetting($business_id)
    {
        return PaymentReminderSchedulerSetting::firstOrCreate(
            ['business_id' => $business_id],
            ['is_enabled' => false]
        );
    }

    public function isSchedulerEnabled($business_id)
    {
        return (bool) PaymentReminderSchedulerSetting::where('business_id', $business_id)
            ->value('is_enabled');
    }

    public function requestChallenge(Business $business, $purpose, $reference_key, $requested_by = null, $schedule_run_id = null)
    {
        $existing = PaymentReminderOtpChallenge::where('business_id', $business->id)
            ->where('purpose', $purpose)
            ->where('reference_key', $reference_key)
            ->first();

        if (! empty($existing)) {
            return [
                'success' => $existing->status === 'pending' && $existing->expires_at->isFuture(),
                'challenge' => $existing,
                'message' => $existing->status === 'pending'
                    ? 'A confirmation code is already pending.'
                    : 'This confirmation request has already been processed.',
            ];
        }

        $sms_settings = $business->sms_settings ?? [];
        $admin_phone = trim((string) ($sms_settings['reminder_otp_phone'] ?? ''));
        $admin_email = trim((string) ($sms_settings['reminder_otp_email'] ?? ''));
        $code = (string) random_int(100000, 999999);

        $challenge = PaymentReminderOtpChallenge::create([
            'business_id' => $business->id,
            'schedule_run_id' => $schedule_run_id,
            'purpose' => $purpose,
            'reference_key' => $reference_key,
            'code_hash' => Hash::make($code),
            'status' => 'pending',
            'attempts' => 0,
            'expires_at' => Carbon::now()->addMinutes(self::CODE_LIFETIME_MINUTES),
            'created_by' => $requested_by,
        ]);

        $message = 'Your scheduled payment reminder confirmation code is '.$code.'. It expires in '
            .self::CODE_LIFETIME_MINUTES.' minutes. Do not share this code.';
        $openwa_error = null;
        if ($admin_phone !== '') {
            $openwa_response = $this->notificationUtil->sendOpenwaMessage([
                'sms_settings' => $sms_settings,
                'mobile_number' => $admin_phone,
                'whatsapp_text' => $message,
            ]);
            if (! empty($openwa_response['success'])) {
                $challenge->delivered_via = 'openwa';
                $challenge->save();

                return [
                    'success' => true,
                    'challenge' => $challenge,
                    'message' => 'A confirmation code was sent through OpenWA.',
                ];
            }
            $openwa_error = $openwa_response['message'] ?? 'OpenWA delivery failed.';
        }

        if ($admin_email !== '') {
            try {
                Mail::raw($message, function ($mail) use ($admin_email) {
                    $mail->to($admin_email)->subject('Scheduled Payment Reminder Confirmation Code');
                });
                $challenge->delivered_via = 'email';
                $challenge->save();

                return [
                    'success' => true,
                    'challenge' => $challenge,
                    'message' => $openwa_error
                        ? 'OpenWA was unavailable; a confirmation code was sent by email.'
                        : 'A confirmation code was sent by email.',
                ];
            } catch (\Throwable $e) {
                $openwa_error = trim(($openwa_error ? $openwa_error.'; ' : '').'Email delivery failed: '.$e->getMessage());
            }
        } elseif ($openwa_error === null) {
            $openwa_error = 'No administrator email or phone is configured.';
        }

        $challenge->status = 'delivery_failed';
        $challenge->delivery_error = substr($openwa_error ?: 'No confirmation channel is available.', 0, 1000);
        $challenge->save();
        \Log::error('Payment reminder OTP delivery failed for business '.$business->id.': '.$challenge->delivery_error);

        return [
            'success' => false,
            'challenge' => $challenge,
            'message' => 'The confirmation code could not be delivered. No reminders will be sent.',
        ];
    }

    public function confirm($business_id, $challenge_id, $code)
    {
        return DB::transaction(function () use ($business_id, $challenge_id, $code) {
            $challenge = PaymentReminderOtpChallenge::where('business_id', $business_id)
                ->where('id', $challenge_id)
                ->lockForUpdate()
                ->first();

            if (empty($challenge) || $challenge->status !== 'pending') {
                return ['success' => false, 'message' => 'This confirmation request is no longer active.'];
            }

            if ($challenge->expires_at->lte(Carbon::now())) {
                $challenge->status = 'expired';
                $challenge->save();

                return ['success' => false, 'message' => 'The confirmation code expired. No reminders were sent.'];
            }

            if ((int) $challenge->attempts >= self::MAX_ATTEMPTS) {
                $challenge->status = 'locked';
                $challenge->save();

                return ['success' => false, 'message' => 'Too many incorrect codes. Request a new confirmation.'];
            }

            if (! Hash::check((string) $code, $challenge->code_hash)) {
                $challenge->attempts = (int) $challenge->attempts + 1;
                if ($challenge->attempts >= self::MAX_ATTEMPTS) {
                    $challenge->status = 'locked';
                }
                $challenge->save();

                return ['success' => false, 'message' => 'The confirmation code is incorrect.'];
            }

            $setting = $this->schedulerSetting($business_id);
            if ($challenge->purpose === 'enable_scheduler' || $challenge->purpose === 'disable_scheduler') {
                $target_enabled = $challenge->purpose === 'enable_scheduler';
                if ((bool) $setting->is_enabled === $target_enabled) {
                    $challenge->status = 'cancelled';
                    $challenge->save();

                    return ['success' => false, 'message' => 'The scheduler state has already changed.'];
                }

                $setting->is_enabled = $target_enabled;
                $setting->save();

                if (! $target_enabled) {
                    PaymentReminderOtpChallenge::where('business_id', $business_id)
                        ->whereIn('purpose', ['grouped_run', 'legacy_run'])
                        ->where('status', 'pending')
                        ->update(['status' => 'cancelled', 'updated_at' => Carbon::now()]);
                }
            } elseif ($challenge->purpose === 'grouped_run') {
                $run = PaymentReminderScheduleRun::with('schedule')
                    ->where('id', $challenge->schedule_run_id)
                    ->lockForUpdate()
                    ->first();
                if (! $setting->is_enabled || empty($run) || empty($run->schedule) || ! $run->schedule->is_active) {
                    $challenge->status = 'cancelled';
                    $challenge->save();

                    return ['success' => false, 'message' => 'The scheduler or schedule is no longer active.'];
                }
                if ($run->status !== 'awaiting_otp') {
                    $challenge->status = 'cancelled';
                    $challenge->save();

                    return ['success' => false, 'message' => 'This reminder run is no longer awaiting confirmation.'];
                }
                $run->status = 'confirmed';
                $run->save();
            } elseif ($challenge->purpose === 'legacy_run') {
                if (! $setting->is_enabled) {
                    $challenge->status = 'cancelled';
                    $challenge->save();

                    return ['success' => false, 'message' => 'The scheduler is disabled. No reminders were sent.'];
                }
            } else {
                $challenge->status = 'cancelled';
                $challenge->save();

                return ['success' => false, 'message' => 'This confirmation request is invalid.'];
            }

            $challenge->status = 'confirmed';
            $challenge->confirmed_at = Carbon::now();
            $challenge->save();

            return [
                'success' => true,
                'purpose' => $challenge->purpose,
                'challenge' => $challenge,
                'message' => 'Confirmation accepted.',
            ];
        });
    }

    public function consumeLegacyChallenge($business_id, $challenge_id)
    {
        return DB::transaction(function () use ($business_id, $challenge_id) {
            $challenge = PaymentReminderOtpChallenge::where('business_id', $business_id)
                ->where('id', $challenge_id)
                ->where('purpose', 'legacy_run')
                ->where('status', 'confirmed')
                ->lockForUpdate()
                ->first();
            if (empty($challenge)) {
                return false;
            }

            $setting = PaymentReminderSchedulerSetting::where('business_id', $business_id)
                ->lockForUpdate()
                ->first();
            if (empty($setting) || ! $setting->is_enabled) {
                $challenge->status = 'cancelled';
                $challenge->save();

                return false;
            }

            $challenge->status = 'consumed';
            $challenge->consumed_at = Carbon::now();
            $challenge->save();

            return true;
        });
    }

    public function expireLegacyChallenges()
    {
        $challenges = PaymentReminderOtpChallenge::with('business')
            ->where('purpose', 'legacy_run')
            ->where('status', 'pending')
            ->where('expires_at', '<=', Carbon::now())
            ->get();

        foreach ($challenges as $challenge) {
            $updated = PaymentReminderOtpChallenge::where('id', $challenge->id)
                ->where('status', 'pending')
                ->update(['status' => 'expired', 'updated_at' => Carbon::now()]);
            if ($updated !== 1) {
                continue;
            }

            $admin_email = data_get($challenge->business->sms_settings, 'reminder_otp_email');
            if (empty($admin_email)) {
                continue;
            }

            try {
                Mail::raw(
                    'The daily legacy payment reminder run was skipped because its OTP was not confirmed within 15 minutes. No customer reminders were sent.',
                    function ($mail) use ($admin_email) {
                        $mail->to($admin_email)->subject('Scheduled Payment Reminder Run Skipped');
                    }
                );
            } catch (\Throwable $e) {
                \Log::error('Could not email legacy payment reminder OTP expiry for challenge '.$challenge->id.': '.$e->getMessage());
            }
        }
    }
}
