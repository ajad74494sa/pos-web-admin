@php
    $openwa_base = $sms_settings['openwa_base_url'] ?? null;
    $openwa_key = $sms_settings['openwa_api_key'] ?? null;
    $openwa_session = $sms_settings['openwa_session_id'] ?? null;
    $reminder_otp_phone = $sms_settings['reminder_otp_phone'] ?? null;
    $reminder_otp_email = $sms_settings['reminder_otp_email'] ?? null;
@endphp
<div class="pos-tab-content">
    <div class="row">
        <div class="col-xs-12">
            <div class="form-group">
                {!! Form::label('openwa_base_url', 'OpenWA Base URL:') !!}
                {!! Form::text('sms_settings[openwa_base_url]', $openwa_base, ['class' => 'form-control','placeholder' => 'http://147.93.72.104:2785', 'id' => 'openwa_base_url']); !!}
            </div>
        </div>
        <div class="col-xs-6">
            <div class="form-group">
                {!! Form::label('openwa_api_key', 'OpenWA API Key:') !!}
                {!! Form::text('sms_settings[openwa_api_key]', $openwa_key, ['class' => 'form-control','placeholder' => 'OpenWA API Key', 'id' => 'openwa_api_key']); !!}
            </div>
        </div>
        <div class="col-xs-6">
            <div class="form-group">
                {!! Form::label('openwa_session_id', 'OpenWA Session ID:') !!}
                {!! Form::text('sms_settings[openwa_session_id]', $openwa_session, ['class' => 'form-control','placeholder' => 'OpenWA Session ID', 'id' => 'openwa_session_id']); !!}
            </div>
        </div>

        <div class="col-md-8 col-xs-12">
            <div class="form-group">
                <div class="input-group">
                    {!! Form::text('test_number_openwa', null, ['class' => 'form-control','placeholder' => __('lang_v1.test_number'), 'id' => 'test_number_openwa']); !!}
                    <span class="input-group-btn">
                        <button type="button" class="btn btn-primary pull-right" id="test_openwa_btn">Test OpenWA</button>
                    </span>
                </div>
                <small class="help-block">Save the OpenWA settings before testing the connection.</small>
            </div>
        </div>
        <div class="col-xs-12">
            <h4>Scheduled reminder OTP contacts</h4>
            <p class="help-block">Authorization codes go to the admin phone through OpenWA first. If OpenWA fails, the code is emailed to the admin email. These are the same contacts used on the Scheduled Due Reminders page.</p>
        </div>
        <div class="col-sm-6">
            <div class="form-group">
                {!! Form::label('reminder_otp_phone', 'Authorized admin phone:') !!}
                {!! Form::text('sms_settings[reminder_otp_phone]', $reminder_otp_phone, ['class' => 'form-control', 'id' => 'reminder_otp_phone', 'placeholder' => '+9665XXXXXXXX']); !!}
            </div>
        </div>
        <div class="col-sm-6">
            <div class="form-group">
                {!! Form::label('reminder_otp_email', 'Authorized admin email (fallback):') !!}
                {!! Form::email('sms_settings[reminder_otp_email]', $reminder_otp_email, ['class' => 'form-control', 'id' => 'reminder_otp_email']); !!}
            </div>
        </div>
    </div>
</div>
