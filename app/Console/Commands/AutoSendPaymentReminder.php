<?php

namespace App\Console\Commands;

use App\Business;
use App\Notifications\CustomerNotification;
use App\PaymentReminderOtpChallenge;
use App\NotificationTemplate;
use App\Transaction;
use App\Utils\NotificationUtil;
use App\Utils\PaymentReminderOtpUtil;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Notification;

class AutoSendPaymentReminder extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'pos:autoSendPaymentReminder {--business-id=} {--challenge-id=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sends payment reminder to customers with overdue sells if auto send is enabled in notification template for payment reminder';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct(NotificationUtil $notificationUtil, PaymentReminderOtpUtil $paymentReminderOtpUtil)
    {
        parent::__construct();

        $this->notificationUtil = $notificationUtil;
        $this->paymentReminderOtpUtil = $paymentReminderOtpUtil;
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        try {
            ini_set('max_execution_time', 0);
            ini_set('memory_limit', '512M');

            $business_id = $this->option('business-id');
            $challenge_id = $this->option('challenge-id');
            if (empty($business_id) && empty($challenge_id)) {
                $this->requestLegacyRunConfirmations();

                return self::SUCCESS;
            }

            if (empty($business_id) || empty($challenge_id)
                || ! $this->paymentReminderOtpUtil->consumeLegacyChallenge($business_id, $challenge_id)) {
                \Log::warning('Legacy payment reminder execution was blocked because OTP authorization was missing or already used.');

                return self::FAILURE;
            }

            $templates = NotificationTemplate::where('template_for', 'payment_reminder')
                                        ->where(function ($q) {
                                            $q->where('auto_send', 1)
                                            ->orWhere('auto_send_sms', 1)
                                            ->orWhere('auto_send_wa_notif', 1);
                                        })
                                        ->where('business_id', $business_id)
                                        ->get();

            foreach ($templates as $template) {
                $business = Business::with(['currency'])->where('id', $template->business_id)->first();

                $data = [
                    'subject' => $template->subject ?? '',
                    'sms_body' => $template->sms_body ?? '',
                    'whatsapp_text' => $template->whatsapp_text ?? '',
                    'email_body' => $template->email_body ?? '',
                    'template_for' => 'payment_reminder',
                    'cc' => $template->cc ?? '',
                    'bcc' => $template->bcc ?? '',
                    'auto_send' => ! empty($template->auto_send) ? 1 : 0,
                    'auto_send_sms' => ! empty($template->auto_send_sms) ? 1 : 0,
                    'auto_send_wa_notif' => ! empty($template->auto_send_wa_notif)
                     ? 1 : 0,
                ];

                $orig_data = [
                    'email_body' => $data['email_body'],
                    'sms_body' => $data['sms_body'],
                    'subject' => $data['subject'],
                    'whatsapp_text' => $data['whatsapp_text'],
                ];

                if (! empty($data['auto_send']) || ! empty($data['auto_send_sms'])) {
                    $overdue_sells = Transaction::where('transactions.business_id', $business->id)
                                    ->where('transactions.type', 'sell')
                                    ->where('transactions.status', 'final')
                                    ->leftjoin('activity_log as a', function ($join) {
                                        $join->on('a.subject_id', '=', 'transactions.id')
                                            ->where('subject_type', \App\Transaction::class)
                                            ->where('description', 'payment_reminder');
                                    })
                                    ->whereNull('a.id')
                                    ->with(['contact', 'payment_lines'])
                                    ->select('transactions.*')
                                    ->groupBy('transactions.id')
                                    ->OverDue()
                                    ->get();

                    foreach ($overdue_sells as $sell) {
                        $tag_replaced_data = $this->notificationUtil->replaceTags($business, $orig_data, $sell);

                        $data['email_body'] = $tag_replaced_data['email_body'];
                        $data['sms_body'] = $tag_replaced_data['sms_body'];
                        $data['subject'] = $tag_replaced_data['subject'];
                        $data['whatsapp_text'] = $tag_replaced_data['whatsapp_text'];

                        $data['email_settings'] = $business->email_settings ?? [];
                        $data['sms_settings'] = $business->sms_settings ?? [];

                        //send email notification
                        if (! empty($data['auto_send']) && ! empty($sell->contact->email)) {
                            try {
                                Notification::route('mail', [$sell->contact->email])
                                    ->notify(new CustomerNotification($data));

                                $this->notificationUtil->activityLog($sell, 'payment_reminder', null, ['email' => $sell->contact->email, 'is_automatic' => true], false);
                            } catch (\Exception $e) {
                                \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());
                            }
                        }

                        //send sms notification
                        if (! empty($data['auto_send_sms']) && ! empty($sell->contact->mobile)) {
                            try {
                                $this->notificationUtil->sendSms($data);

                                $this->notificationUtil->activityLog($sell, 'payment_reminder', null, ['mobile' => $sell->contact->mobile, 'is_automatic' => true], false);
                            } catch (\Exception $e) {
                                \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());
                            }
                        }

                        if (! empty($data['auto_send_wa_notif']) && ! empty($sell->contact->mobile)) {
                            try {
                                $openwa_response = $this->notificationUtil->dispatchOpenwaMessage($data);
                                if (! empty($openwa_response['success'])) {
                                    $this->notificationUtil->activityLog($sell, 'whatsapp_notification_sent', null, ['mobile' => $sell->contact->mobile, 'is_automatic' => true], false);
                                } else {
                                    \Log::emergency('OpenWA payment reminder failed: '.$openwa_response['message']);
                                }
                            } catch (\Exception $e) {
                                \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());
                            }
                        }

                    }
                }
            }
        } catch (\Exception $e) {
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

            return self::FAILURE;
        }
    }

    private function requestLegacyRunConfirmations()
    {
        $business_ids = NotificationTemplate::where('template_for', 'payment_reminder')
            ->where(function ($q) {
                $q->where('auto_send', 1)
                    ->orWhere('auto_send_sms', 1)
                    ->orWhere('auto_send_wa_notif', 1);
            })
            ->distinct()
            ->pluck('business_id');

        foreach ($business_ids as $business_id) {
            if (! $this->paymentReminderOtpUtil->isSchedulerEnabled($business_id)) {
                continue;
            }

            $business = Business::where('id', $business_id)->first();
            if (empty($business)) {
                continue;
            }

            $run_key = Carbon::now($business->time_zone ?: config('app.timezone'))->format('Y-m-d');
            $challenge_exists = PaymentReminderOtpChallenge::where('business_id', $business_id)
                ->where('purpose', 'legacy_run')
                ->where('reference_key', $run_key)
                ->exists();
            if ($challenge_exists) {
                continue;
            }

            $result = $this->paymentReminderOtpUtil->requestChallenge(
                $business,
                'legacy_run',
                $run_key,
                null
            );
            if (! $result['success']) {
                \Log::warning('Legacy payment reminder run was held because OTP delivery failed for business '.$business_id.'.');
            }
        }
    }
}
