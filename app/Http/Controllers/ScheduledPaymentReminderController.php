<?php

namespace App\Http\Controllers;

use App\Business;
use App\PaymentReminderOtpChallenge;
use App\PaymentReminderSchedule;
use App\Utils\PaymentReminderOtpUtil;
use Carbon\Carbon;
use DateTimeZone;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ScheduledPaymentReminderController extends Controller
{
    protected $paymentReminderOtpUtil;

    public function __construct(PaymentReminderOtpUtil $paymentReminderOtpUtil)
    {
        $this->paymentReminderOtpUtil = $paymentReminderOtpUtil;
    }

    public function index()
    {
        $this->authorizeScheduleManagement();

        $business_id = request()->session()->get('user.business_id');
        $business = Business::with('owner')->where('id', $business_id)->firstOrFail();
        $scheduler_setting = $this->paymentReminderOtpUtil->schedulerSetting($business_id);
        $schedules = PaymentReminderSchedule::where('business_id', $business_id)
            ->orderBy('is_active', 'desc')
            ->orderBy('scheduled_date')
            ->orderBy('day_of_month')
            ->orderBy('send_time')
            ->get();
        $otp_challenges = PaymentReminderOtpChallenge::with('scheduleRun.schedule')
            ->where('business_id', $business_id)
            ->where('status', 'pending')
            ->where('expires_at', '>', Carbon::now())
            ->orderBy('created_at')
            ->get();

        return view('business.payment_reminder_schedules', compact('business', 'schedules', 'scheduler_setting', 'otp_challenges'));
    }

    public function saveOtpContacts(Request $request)
    {
        $this->authorizeScheduleManagement();
        $validated = $request->validate([
            'admin_phone' => ['required', 'string', 'min:7', 'max:32', 'regex:/^\+?[0-9][0-9\s().-]{5,30}$/'],
            'admin_email' => ['required', 'email', 'max:255'],
        ]);

        $business_id = $request->session()->get('user.business_id');
        $business = Business::where('id', $business_id)->firstOrFail();
        $sms_settings = $business->sms_settings ?? [];
        $sms_settings['reminder_otp_phone'] = trim($validated['admin_phone']);
        $sms_settings['reminder_otp_email'] = trim($validated['admin_email']);
        $business->sms_settings = $sms_settings;
        $business->save();
        $request->session()->put('business', $business);

        return redirect()->back()->with('status', [
            'success' => true,
            'msg' => 'Scheduler authorization contacts saved.',
        ]);
    }

    public function requestSchedulerToggle(Request $request)
    {
        $this->authorizeScheduleManagement();
        $validated = $request->validate([
            'action' => ['required', 'in:enable,disable'],
        ]);

        $business_id = $request->session()->get('user.business_id');
        $business = Business::where('id', $business_id)->firstOrFail();
        $setting = $this->paymentReminderOtpUtil->schedulerSetting($business_id);
        $target_enabled = $validated['action'] === 'enable';
        if ((bool) $setting->is_enabled === $target_enabled) {
            return redirect()->back()->with('status', [
                'success' => false,
                'msg' => 'The scheduler is already '.($target_enabled ? 'enabled.' : 'disabled.'),
            ]);
        }

        PaymentReminderOtpChallenge::where('business_id', $business_id)
            ->whereIn('purpose', ['enable_scheduler', 'disable_scheduler'])
            ->where('status', 'pending')
            ->update(['status' => 'cancelled', 'updated_at' => Carbon::now()]);

        $result = $this->paymentReminderOtpUtil->requestChallenge(
            $business,
            $target_enabled ? 'enable_scheduler' : 'disable_scheduler',
            (string) Str::uuid(),
            $request->user()->id
        );

        return redirect()->back()->with('status', [
            'success' => $result['success'],
            'msg' => $result['message'],
        ]);
    }

    public function confirmOtp(Request $request)
    {
        $this->authorizeScheduleManagement();
        $validated = $request->validate([
            'otp_challenge_id' => ['required', 'integer'],
            'otp_code' => ['required', 'digits:6'],
        ]);

        $business_id = $request->session()->get('user.business_id');
        $result = $this->paymentReminderOtpUtil->confirm(
            $business_id,
            $validated['otp_challenge_id'],
            $validated['otp_code']
        );

        if (! empty($result['success']) && $result['purpose'] === 'legacy_run') {
            $exit_code = Artisan::call('pos:autoSendPaymentReminder', [
                '--business-id' => $business_id,
                '--challenge-id' => $result['challenge']->id,
            ]);
            if ($exit_code !== 0) {
                return redirect()->back()->with('status', [
                    'success' => false,
                    'msg' => 'The code was accepted, but the legacy reminder command failed. Check the application log.',
                ]);
            }
        }

        return redirect()->back()->with('status', [
            'success' => $result['success'],
            'msg' => $result['message'],
        ]);
    }

    public function store(Request $request)
    {
        $this->authorizeScheduleManagement();

        $business_id = $request->session()->get('user.business_id');
        $business = Business::with('owner')->where('id', $business_id)->firstOrFail();
        $validated = $request->validate([
            'schedule_type' => ['required', 'in:one_time,monthly'],
            'send_time' => ['required', 'date_format:H:i'],
            'report_email' => ['required', 'email', 'max:255'],
            'run_dates' => ['required_if:schedule_type,one_time', 'array', 'min:1'],
            'run_dates.*' => ['required', 'date_format:Y-m-d', 'distinct'],
            'day_of_month' => ['required_if:schedule_type,monthly', 'integer', 'between:1,31'],
        ]);

        $timezone = $business->time_zone ?: config('app.timezone');
        try {
            $business_now = Carbon::now(new DateTimeZone($timezone));
        } catch (\Exception $e) {
            return redirect()->back()->withInput()->with('status', [
                'success' => false,
                'msg' => 'The business timezone is invalid. Correct it in Business Settings before adding a schedule.',
            ]);
        }

        if ($validated['schedule_type'] === 'one_time') {
            foreach ($validated['run_dates'] as $run_date) {
                $scheduled_at = Carbon::createFromFormat('Y-m-d H:i', $run_date.' '.$validated['send_time'], $timezone);
                if ($scheduled_at->lte($business_now)) {
                    return redirect()->back()->withInput()->with('status', [
                        'success' => false,
                        'msg' => 'Every one-time schedule must be set for a future date and time in the business timezone.',
                    ]);
                }
            }
        }

        $created_count = 0;
        $duplicate_count = 0;
        DB::transaction(function () use ($validated, $business, $business_id, $timezone, &$created_count, &$duplicate_count, $request) {
            if ($validated['schedule_type'] === 'one_time') {
                foreach ($validated['run_dates'] as $run_date) {
                    $exists = PaymentReminderSchedule::where('business_id', $business_id)
                        ->where('schedule_type', 'one_time')
                        ->where('scheduled_date', $run_date)
                        ->where('send_time', $validated['send_time'])
                        ->where('is_active', true)
                        ->exists();
                    if ($exists) {
                        $duplicate_count++;
                        continue;
                    }

                    PaymentReminderSchedule::create([
                        'business_id' => $business_id,
                        'schedule_type' => 'one_time',
                        'scheduled_date' => $run_date,
                        'send_time' => $validated['send_time'],
                        'timezone' => $timezone,
                        'report_email' => $validated['report_email'],
                        'is_active' => true,
                        'created_by' => $request->user()->id,
                    ]);
                    $created_count++;
                }
            } else {
                $exists = PaymentReminderSchedule::where('business_id', $business_id)
                    ->where('schedule_type', 'monthly')
                    ->where('day_of_month', $validated['day_of_month'])
                    ->where('send_time', $validated['send_time'])
                    ->where('is_active', true)
                    ->exists();
                if ($exists) {
                    $duplicate_count++;
                } else {
                    PaymentReminderSchedule::create([
                        'business_id' => $business_id,
                        'schedule_type' => 'monthly',
                        'day_of_month' => $validated['day_of_month'],
                        'send_time' => $validated['send_time'],
                        'timezone' => $timezone,
                        'report_email' => $validated['report_email'],
                        'is_active' => true,
                        'created_by' => $request->user()->id,
                    ]);
                    $created_count++;
                }
            }
        });

        $message = $created_count.' schedule(s) added.';
        if ($duplicate_count > 0) {
            $message .= ' '.$duplicate_count.' duplicate schedule(s) were skipped.';
        }

        return redirect()->back()->with('status', [
            'success' => $created_count > 0,
            'msg' => $message,
        ]);
    }

    public function destroy(Request $request, $id)
    {
        $this->authorizeScheduleManagement();

        $business_id = $request->session()->get('user.business_id');
        $schedule = PaymentReminderSchedule::where('business_id', $business_id)->findOrFail($id);
        $schedule->is_active = false;
        $schedule->save();

        return redirect()->back()->with('status', [
            'success' => true,
            'msg' => 'Schedule cancelled.',
        ]);
    }

    private function authorizeScheduleManagement()
    {
        if (! auth()->user()->can('business_settings.access')) {
            abort(403, 'Unauthorized action.');
        }
    }
}
