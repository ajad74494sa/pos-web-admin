@extends('layouts.app')
@section('title', 'Scheduled Due Reminders')

@section('content')
    <section class="content-header">
        <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">Scheduled Due Reminders</h1>
    </section>

    <section class="content">
        @if (session('status'))
            <div class="alert {{ session('status.success') ? 'alert-success' : 'alert-danger' }}">
                {{ session('status.msg') }}
            </div>
        @endif
        @if ($errors->any())
            <div class="alert alert-danger">
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="box box-warning">
            <div class="box-header with-border">
                <h3 class="box-title">Scheduler authorization</h3>
            </div>
            <div class="box-body">
                <p>Scheduled reminders are currently <strong>{{ $scheduler_setting->is_enabled ? 'ON' : 'OFF' }}</strong>. No scheduled customer reminders are sent while this switch is off.</p>
                <p>Turning this on or off requires an OTP. Before every grouped or legacy scheduled reminder run, the scheduler also waits for a valid OTP. The code is sent through OpenWA first; if that fails, it is sent to the configured admin email. Codes expire in 15 minutes.</p>
                <form method="POST" action="{{ route('business.payment_reminder_schedules.toggle') }}">
                    @csrf
                    <input type="hidden" name="action" value="{{ $scheduler_setting->is_enabled ? 'disable' : 'enable' }}">
                    <button type="submit" class="btn {{ $scheduler_setting->is_enabled ? 'btn-danger' : 'btn-success' }}">
                        {{ $scheduler_setting->is_enabled ? 'Turn scheduler off (OTP required)' : 'Turn scheduler on (OTP required)' }}
                    </button>
                </form>
            </div>
        </div>

        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title">Authorized admin contacts</h3>
            </div>
            <form method="POST" action="{{ route('business.payment_reminder_schedules.otp_contacts') }}">
                @csrf
                <div class="box-body">
                    <p>These contacts receive authorization codes. OpenWA is tried first; the email is the fallback.</p>
                    <div class="form-group">
                        <label for="admin_phone">Admin phone for OpenWA</label>
                        <input type="tel" name="admin_phone" id="admin_phone" class="form-control" value="{{ old('admin_phone', data_get($business->sms_settings, 'reminder_otp_phone')) }}" required>
                    </div>
                    <div class="form-group">
                        <label for="admin_email">Admin email fallback</label>
                        <input type="email" name="admin_email" id="admin_email" class="form-control" value="{{ old('admin_email', data_get($business->sms_settings, 'reminder_otp_email')) }}" maxlength="255" required>
                    </div>
                </div>
                <div class="box-footer">
                    <button type="submit" class="btn btn-primary">Save authorization contacts</button>
                </div>
            </form>
        </div>

        @if ($otp_challenges->isNotEmpty())
            <div class="box box-danger">
                <div class="box-header with-border">
                    <h3 class="box-title">Pending OTP confirmations</h3>
                </div>
                <div class="box-body">
                    @foreach ($otp_challenges as $challenge)
                        <div class="well">
                            <p>
                                @if ($challenge->purpose === 'enable_scheduler')
                                    Confirm turning the scheduler on.
                                @elseif ($challenge->purpose === 'disable_scheduler')
                                    Confirm turning the scheduler off.
                                @elseif ($challenge->purpose === 'legacy_run')
                                    Confirm today's legacy reminder run.
                                @else
                                    Confirm scheduled due reminders
                                    @if ($challenge->scheduleRun && $challenge->scheduleRun->schedule)
                                        for {{ $challenge->scheduleRun->schedule->schedule_type === 'monthly' ? 'monthly schedule' : 'one-time schedule' }}.
                                    @endif
                                @endif
                                Expires {{ @format_datetime($challenge->expires_at) }}.
                            </p>
                            <form method="POST" action="{{ route('business.payment_reminder_schedules.confirm_otp') }}" class="form-inline">
                                @csrf
                                <input type="hidden" name="otp_challenge_id" value="{{ $challenge->id }}">
                                <div class="form-group">
                                    <label class="sr-only" for="otp_code_{{ $challenge->id }}">Six-digit code</label>
                                    <input type="text" name="otp_code" id="otp_code_{{ $challenge->id }}" class="form-control" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" placeholder="6-digit code" required>
                                </div>
                                <button type="submit" class="btn btn-danger">Confirm and continue</button>
                            </form>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title">Add a schedule</h3>
            </div>
            <form method="POST" action="{{ route('business.payment_reminder_schedules.store') }}">
                @csrf
                <div class="box-body">
                    <p>Each run sends one grouped WhatsApp reminder per customer with outstanding invoices, using the Payment reminder template.</p>
                    <p>Times use the business timezone: <strong>{{ $business->time_zone ?: config('app.timezone') }}</strong>.</p>

                    <div class="form-group">
                        <label for="schedule_type">Schedule type</label>
                        <select name="schedule_type" id="schedule_type" class="form-control" required>
                            <option value="one_time" {{ old('schedule_type', 'one_time') === 'one_time' ? 'selected' : '' }}>One-time date(s)</option>
                            <option value="monthly" {{ old('schedule_type') === 'monthly' ? 'selected' : '' }}>Monthly recurring day</option>
                        </select>
                    </div>

                    <div id="one_time_dates">
                        <label>Send on date(s)</label>
                        <div id="schedule_date_fields">
                            @foreach (old('run_dates', ['']) as $run_date)
                                <div class="form-group schedule-date-field">
                                    <div class="{{ $loop->first ? '' : 'input-group' }}">
                                        <input type="date" name="run_dates[]" class="form-control" value="{{ $run_date }}" required>
                                        @if (! $loop->first)
                                            <span class="input-group-btn"><button type="button" class="btn btn-default remove-schedule-date">Remove</button></span>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                        <button type="button" class="btn btn-default" id="add_schedule_date">Add another date</button>
                    </div>

                    <div id="monthly_day_field" class="form-group" style="display: none;">
                        <label for="day_of_month">Day of month</label>
                        <input type="number" name="day_of_month" id="day_of_month" class="form-control" min="1" max="31" disabled>
                        <small class="help-block">If a month has fewer days, a selected day 29–31 runs on that month's last day.</small>
                    </div>

                    <div class="form-group">
                        <label for="send_time">Send time</label>
                        <input type="time" name="send_time" id="send_time" class="form-control" value="{{ old('send_time', '08:00') }}" required>
                    </div>

                    <div class="form-group">
                        <label for="report_email">Summary report email</label>
                        <input type="email" name="report_email" id="report_email" class="form-control" value="{{ old('report_email', optional($business->owner)->email) }}" maxlength="255" required>
                    </div>
                </div>
                <div class="box-footer">
                    <button type="submit" class="btn btn-primary">Save schedule</button>
                    <a href="{{ action([\App\Http\Controllers\BusinessController::class, 'getBusinessSettings']) }}" class="btn btn-default">Back to Business Settings</a>
                </div>
            </form>
        </div>

        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title">Your schedules</h3>
            </div>
            <div class="box-body table-responsive">
                <table class="table table-bordered table-striped">
                    <thead>
                        <tr>
                            <th>Schedule</th>
                            <th>Send time</th>
                            <th>Timezone</th>
                            <th>Report email</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($schedules as $schedule)
                            <tr>
                                <td>
                                    @if ($schedule->schedule_type === 'one_time')
                                        One time — @format_date($schedule->scheduled_date)
                                    @else
                                        Monthly — day {{ $schedule->day_of_month }}
                                    @endif
                                </td>
                                <td>{{ substr($schedule->send_time, 0, 5) }}</td>
                                <td>{{ $schedule->timezone }}</td>
                                <td>{{ $schedule->report_email }}</td>
                                <td>{{ $schedule->is_active ? 'Active' : 'Cancelled or completed' }}</td>
                                <td>
                                    @if ($schedule->is_active)
                                        <form method="POST" action="{{ route('business.payment_reminder_schedules.destroy', $schedule->id) }}">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-xs btn-danger" onclick="return confirm('Cancel this schedule?')">Cancel</button>
                                        </form>
                                    @else
                                        -
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6">No schedules have been created.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </section>
@endsection

@section('javascript')
    <script>
        $(function() {
            function updateScheduleFields() {
                var oneTime = $('#schedule_type').val() === 'one_time';
                $('#one_time_dates').toggle(oneTime);
                $('#schedule_date_fields input').prop('disabled', !oneTime).prop('required', oneTime);
                $('#add_schedule_date').prop('disabled', !oneTime);
                $('#monthly_day_field').toggle(!oneTime);
                $('#day_of_month').prop('disabled', oneTime).prop('required', !oneTime);
            }

            $('#schedule_type').on('change', updateScheduleFields);
            $('#add_schedule_date').on('click', function() {
                $('#schedule_date_fields').append(
                    '<div class="form-group schedule-date-field"><div class="input-group"><input type="date" name="run_dates[]" class="form-control" required><span class="input-group-btn"><button type="button" class="btn btn-default remove-schedule-date">Remove</button></span></div></div>'
                );
            });
            $(document).on('click', '.remove-schedule-date', function() {
                $(this).closest('.schedule-date-field').remove();
            });

            updateScheduleFields();
        });
    </script>
@endsection
