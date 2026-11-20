@extends('layouts.auth2')
@section('title', __('lang_v1.login'))
@inject('request', 'Illuminate\Http\Request')
@section('content')
    <style>
        .login-page-grid {
            box-sizing: border-box;
            display: grid;
            grid-template-columns: minmax(0, 1.05fr) minmax(0, 0.95fr);
            width: 100%;
            max-width: 1080px;
            min-height: min(570px, calc(100vh - 160px));
            min-height: min(570px, calc(100svh - 160px));
            margin: auto;
            overflow: hidden;
            border-radius: 28px;
            background: #fff;
            box-shadow: 0 28px 80px rgba(15, 23, 42, .24);
        }

        .login-side-panel {
            position: relative;
            display: flex;
            flex-direction: column;
            justify-content: center;
            min-width: 0;
            padding: clamp(28px, 5vw, 48px);
            overflow: hidden;
            color: #fff;
            background: linear-gradient(145deg, #172554 0%, #3730a3 58%, #4f46e5 100%);
        }

        .login-side-panel::before,
        .login-side-panel::after {
            position: absolute;
            content: "";
            border: 1px solid rgba(255, 255, 255, .13);
            border-radius: 50%;
            pointer-events: none;
        }

        .login-side-panel::before {
            width: 360px;
            height: 360px;
            right: -170px;
            top: -150px;
        }

        .login-side-panel::after {
            width: 260px;
            height: 260px;
            left: -150px;
            bottom: -130px;
        }

        .login-brand-content {
            position: relative;
            z-index: 1;
            text-align: center;
        }

        .login-brand-logo {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 64px;
            height: 64px;
            margin: 0 auto 32px;
            border-radius: 18px;
            background: rgba(255, 255, 255, .14);
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, .22);
        }

        .login-brand-logo img {
            max-width: 46px;
            max-height: 46px;
            object-fit: contain;
        }

        .login-brand-content h2 {
            margin: 0 0 12px;
            color: #fff;
            font-size: 32px;
            font-weight: 700;
            line-height: 1.2;
        }

        .login-brand-features {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 10px;
            margin-top: 24px;
        }

        .login-brand-features span {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 9px 12px;
            border: 1px solid rgba(255, 255, 255, .16);
            border-radius: 999px;
            background: rgba(255, 255, 255, .08);
            color: rgba(255, 255, 255, .9);
            font-size: 13px;
        }

        .login-brand-rule {
            width: 52px;
            height: 4px;
            margin: 28px auto 0;
            border-radius: 4px;
            background: #a5b4fc;
        }

        .login-form-panel {
            display: flex;
            align-items: center;
            justify-content: center;
            min-width: 0;
            padding: clamp(28px, 5vw, 48px);
        }

        .login-form-card {
            width: 100%;
            max-width: 390px;
            margin: 0;
            padding: 0;
            border: 0;
            border-radius: 0;
            background: transparent;
            box-shadow: none;
        }

        .login-form-card h1 {
            margin: 0;
            color: #111827;
            font-size: 28px;
            font-weight: 700;
            letter-spacing: -.025em;
        }

        .login-form-card h2 {
            margin: 8px 0 28px;
            color: #6b7280;
            font-size: 14px;
            font-weight: 400;
        }

        .login-form-card .form-group {
            margin-bottom: 20px;
        }

        .login-form-card .tw-dw-label {
            display: flex;
            margin-bottom: 7px;
        }

        .login-form-card .tw-dw-label span,
        .login-form-card .tw-dw-label a,
        .login-form-card .tw-dw-label label {
            color: #374151;
        }

        .login-form-card .tw-dw-form-control {
            position: relative;
            display: block;
        }

        .login-form-card input[type="text"],
        .login-form-card input[type="password"] {
            box-sizing: border-box;
            width: 100%;
            height: 48px;
            padding: 0 14px;
            border: 1px solid #d1d5db;
            border-radius: 10px;
            background: #fff;
            transition: border-color .2s ease, box-shadow .2s ease;
        }

        .login-form-card input[type="text"]:focus,
        .login-form-card input[type="password"]:focus {
            border-color: #6366f1;
            box-shadow: 0 0 0 3px rgba(99, 102, 241, .14);
        }

        .login-form-card #password {
            padding-inline-end: 48px;
        }

        .login-form-card #show_hide_icon {
            top: 30px !important;
            right: 10px !important;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 32px;
            height: 32px;
            padding: 0;
            border: 0;
            border-radius: 8px;
            background: transparent;
            cursor: pointer;
        }

        .login-form-card[dir="rtl"] #show_hide_icon {
            right: auto !important;
            left: 10px !important;
        }

        .login-form-card #show_hide_icon svg {
            width: 20px;
            height: 20px;
            stroke: #6b7280;
        }

        .login-form-card .login-submit {
            height: 48px;
            margin-top: 8px;
            border-radius: 10px;
            box-shadow: 0 8px 18px rgba(79, 70, 229, .2);
        }

        .login-form-card .login-register-link {
            margin-top: 20px;
            color: #6b7280;
            text-align: center;
        }

        .login-demo-panel {
            max-height: 570px;
            overflow-y: auto;
        }

        .login-demo-panel .box {
            margin: 0;
            border: 0;
            border-radius: 16px;
            box-shadow: none;
        }

        @media (max-width: 800px) {
            .login-page-grid {
                display: flex;
                flex-direction: column;
                width: min(100%, 560px);
                min-height: 0;
                margin: 12px auto 24px;
                border-radius: 20px;
            }

            .login-side-panel {
                min-height: 0;
                padding: 22px 24px;
            }

            .login-brand-logo {
                width: 52px;
                height: 52px;
                margin-bottom: 12px;
                border-radius: 15px;
            }

            .login-brand-content h2 {
                font-size: 24px;
            }

            .login-brand-rule {
                margin-top: 16px;
            }

            .login-brand-features {
                gap: 8px;
                margin-top: 14px;
            }

            .login-brand-features span {
                padding: 7px 10px;
                font-size: 12px;
            }

            .login-form-panel {
                padding: 28px clamp(20px, 6vw, 36px) 32px;
            }

            .login-form-card h2 {
                margin-bottom: 24px;
            }

            .login-demo-panel {
                max-height: none;
            }
        }

        @media (max-width: 640px) {
            .login-page-grid {
                margin-top: 0;
                margin-bottom: 12px;
                border-radius: 18px;
            }

            .login-side-panel {
                padding: 12px 18px;
            }

            .login-brand-content {
                display: grid;
                grid-template-columns: 44px minmax(0, 1fr);
                align-items: center;
                column-gap: 12px;
                text-align: left;
            }

            .login-brand-logo {
                grid-row: 1;
                width: 44px;
                height: 44px;
                margin: 0;
                border-radius: 12px;
            }

            .login-brand-logo img {
                max-width: 34px;
                max-height: 34px;
            }

            .login-brand-content h2 {
                grid-column: 2;
                margin: 0;
                font-size: 20px;
            }

            .login-brand-features,
            .login-brand-rule {
                display: none;
            }

            .login-form-panel {
                padding: 18px 20px 20px;
            }

            .login-form-inner {
                gap: 12px !important;
                padding: 0 !important;
            }

            .login-form-card h1 {
                font-size: 24px;
            }

            .login-form-card h2 {
                margin: 4px 0 14px;
            }

            .login-form-card .form-group {
                margin-bottom: 12px;
            }

            .login-form-card .tw-dw-label {
                margin-bottom: 4px;
            }

            .login-form-card input[type="text"],
            .login-form-card input[type="password"] {
                height: 44px;
            }

            .login-form-card .login-submit {
                height: 44px;
                margin-top: 4px;
            }

            .login-form-card .login-register-link {
                margin-top: 0;
            }
        }

        @media (max-width: 380px) {
            .login-side-panel {
                padding-right: 14px;
                padding-left: 14px;
            }

            .login-form-panel {
                padding-right: 16px;
                padding-left: 16px;
            }

            .login-form-card h1 {
                font-size: 22px;
            }
        }

        @media (max-width: 640px) and (max-height: 720px) {
            .login-side-panel {
                padding-top: 8px;
                padding-bottom: 8px;
            }

            .login-form-panel {
                padding-top: 12px;
                padding-bottom: 14px;
            }

            .login-form-inner {
                gap: 8px !important;
            }

            .login-form-card h2 {
                margin-bottom: 8px;
            }

            .login-form-card .form-group {
                margin-bottom: 8px;
            }
        }
    </style>
    @php
        $is_rtl = in_array(session()->get('user.language', config('app.locale')), config('constants.langs_rtl'));
        $username = old('username');
        $password = null;
        if (config('app.env') == 'demo') {
            $username = 'admin';
            $password = '123456';

            $demo_types = [
                'all_in_one' => 'admin',
                'super_market' => 'admin',
                'pharmacy' => 'admin-pharmacy',
                'electronics' => 'admin-electronics',
                'services' => 'admin-services',
                'restaurant' => 'admin-restaurant',
                'superadmin' => 'superadmin',
                'woocommerce' => 'woocommerce_user',
                'essentials' => 'admin-essentials',
                'manufacturing' => 'manufacturer-demo',
            ];

            if (!empty($_GET['demo_type']) && array_key_exists($_GET['demo_type'], $demo_types)) {
                $username = $demo_types[$_GET['demo_type']];
            }
        }
    @endphp
    <div class="login-page-grid">
        <div class="login-side-panel {{ config('app.env') == 'demo' ? 'login-demo-panel' : '' }}">
        @if (config('app.env') == 'demo')
        
                @component('components.widget', [
                    'class' => 'box-primary',
                    'header' =>
                        '<h4 class="text-center">Demo Shops <small><i> <br/>Demos are for example purpose only, this application <u>can be used in many other similar businesses.</u></i> <br/><b>Click button to login that business</b></small></h4>',
                ])
                    <a href="?demo_type=all_in_one" class="btn btn-app bg-olive demo-login" data-toggle="tooltip"
                        title="Showcases all feature available in the application."
                        data-admin="{{ $demo_types['all_in_one'] }}"> <i class="fas fa-star"></i> All In One</a>

                    <a href="?demo_type=pharmacy" class="btn bg-maroon btn-app demo-login" data-toggle="tooltip"
                        title="Shops with products having expiry dates." data-admin="{{ $demo_types['pharmacy'] }}"><i
                            class="fas fa-medkit"></i>Pharmacy</a>

                    <a href="?demo_type=services" class="btn bg-orange btn-app demo-login" data-toggle="tooltip"
                        title="For all service providers like Web Development, Restaurants, Repairing, Plumber, Salons, Beauty Parlors etc."
                        data-admin="{{ $demo_types['services'] }}"><i class="fas fa-wrench"></i>Multi-Service Center</a>

                    <a href="?demo_type=electronics" class="btn bg-purple btn-app demo-login" data-toggle="tooltip"
                        title="Products having IMEI or Serial number code." data-admin="{{ $demo_types['electronics'] }}"><i
                            class="fas fa-laptop"></i>Electronics & Mobile Shop</a>

                    <a href="?demo_type=super_market" class="btn bg-navy btn-app demo-login" data-toggle="tooltip"
                        title="Super market & Similar kind of shops." data-admin="{{ $demo_types['super_market'] }}"><i
                            class="fas fa-shopping-cart"></i> Super Market</a>

                    <a href="?demo_type=restaurant" class="btn bg-red btn-app demo-login" data-toggle="tooltip"
                        title="Restaurants, Salons and other similar kind of shops."
                        data-admin="{{ $demo_types['restaurant'] }}"><i class="fas fa-utensils"></i> Restaurant</a>
                    <hr>

                    <i class="icon fas fa-plug"></i> Premium optional modules:<br><br>

                    <a href="?demo_type=superadmin" class="btn bg-red-active btn-app demo-login" data-toggle="tooltip"
                        title="SaaS & Superadmin extension Demo" data-admin="{{ $demo_types['superadmin'] }}"><i
                            class="fas fa-university"></i> SaaS / Superadmin</a>

                    <a href="?demo_type=woocommerce" class="btn bg-woocommerce btn-app demo-login" data-toggle="tooltip"
                        title="WooCommerce demo user - Open web shop in minutes!!" style="color:white !important"
                        data-admin="{{ $demo_types['woocommerce'] }}"> <i class="fab fa-wordpress"></i> WooCommerce</a>

                    <a href="?demo_type=essentials" class="btn bg-navy btn-app demo-login" data-toggle="tooltip"
                        title="Essentials & HRM (human resource management) Module Demo" style="color:white !important"
                        data-admin="{{ $demo_types['essentials'] }}">
                        <i class="fas fa-check-circle"></i>
                        Essentials & HRM</a>

                    <a href="?demo_type=manufacturing" class="btn bg-orange btn-app demo-login" data-toggle="tooltip"
                        title="Manufacturing module demo" style="color:white !important"
                        data-admin="{{ $demo_types['manufacturing'] }}">
                        <i class="fas fa-industry"></i>
                        Manufacturing Module</a>

                    <a href="?demo_type=superadmin" class="btn bg-maroon btn-app demo-login" data-toggle="tooltip"
                        title="Project module demo" style="color:white !important"
                        data-admin="{{ $demo_types['superadmin'] }}">
                        <i class="fas fa-project-diagram"></i>
                        Project Module</a>

                    <a href="?demo_type=services" class="btn btn-app demo-login" data-toggle="tooltip"
                        title="Advance repair module demo" style="color:white !important; background-color: #bc8f8f"
                        data-admin="{{ $demo_types['services'] }}">
                        <i class="fas fa-wrench"></i>
                        Advance Repair Module</a>

                    <a href="{{ url('docs') }}" target="_blank" class="btn btn-app" data-toggle="tooltip"
                        title="Advance repair module demo" style="color:white !important; background-color: #2dce89">
                        <i class="fas fa-network-wired"></i>
                        Connector Module / API Documentation</a>
                @endcomponent
        @else
            <div class="login-brand-content">
                <div class="login-brand-logo">
                    <img src="{{ asset('uploads/cms/logo.png') }}" alt="KR Soft Pos">
                </div>
                <h2>{{ config('app.name', 'KR Soft Pos') }}</h2>
                <div class="login-brand-features">
                    <span><i class="fas fa-cash-register" aria-hidden="true"></i> @lang('sale.pos_sale')</span>
                    <span><i class="fas fa-boxes" aria-hidden="true"></i> @lang('sale.products')</span>
                    <span><i class="fas fa-chart-line" aria-hidden="true"></i> @lang('report.reports')</span>
                </div>
                <div class="login-brand-rule"></div>
            </div>
    @endif
        </div>
        <div class="login-form-panel">
            <div class="login-form-card" dir="{{ $is_rtl ? 'rtl' : 'ltr' }}">
                <div class="login-form-inner tw-flex tw-flex-col tw-gap-4 tw-dw-rounded-box tw-dw-p-6 tw-dw-max-w-md">
                    <div class="tw-flex tw-items-center tw-flex-col">
                        <h1>
                            @lang('lang_v1.welcome_back')
                        </h1>
                        <h2>
                            @lang('lang_v1.login_to_your') {{ config('app.name', 'KR Soft Pos') }}
                        </h2>
                    </div>

                    <form method="POST" action="{{ route('login') }}" id="login-form">
                        {{ csrf_field() }}
                        <div class="form-group has-feedback {{ $errors->has('username') ? ' has-error' : '' }}">
                            <label class="tw-dw-form-control">
                                <div class="tw-dw-label">
                                    <span
                                        class="tw-text-xs md:tw-text-sm tw-font-medium tw-text-black">@lang('lang_v1.username')</span>
                                </div>

                                <input
                                    name="username" required autocomplete="username" placeholder="@lang('lang_v1.username')"
                                    data-last-active-input="" id="username" type="text"
                                    value="{{ $username }}" />
                                @if ($errors->has('username'))
                                    <span class="help-block">
                                        <strong>{{ $errors->first('username') }}</strong>
                                    </span>
                                @endif
                            </label>
                        </div>

                        <div class="form-group has-feedback {{ $errors->has('password') ? ' has-error' : '' }}">
                            <label class="tw-dw-form-control">
                                <div class="tw-dw-label">
                                    <span
                                        class="tw-text-xs md:tw-text-sm tw-font-medium tw-text-black">@lang('lang_v1.password')</span>
                                    @if (config('app.env') != 'demo')
                                        <a href="{{ route('password.request') }}"
                                            class="tw-text-xs md:tw-text-sm tw-font-medium tw-bg-gradient-to-r tw-from-indigo-500 tw-to-blue-500 tw-inline-block tw-text-transparent tw-bg-clip-text hover:tw-text-[#467BF5]"
                                            tabindex="-1">@lang('lang_v1.forgot_your_password')</a>
                                    @endif
                                </div>

                                <input
                                    id="password" type="password" name="password" value="{{ $password }}" required autocomplete="current-password"
                                    placeholder="@lang('lang_v1.password')" />
                                <button type="button" id="show_hide_icon" class="show_hide_icon"
                                    style="position: absolute;">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-eye tw-w-6" viewBox="0 0 24 24" stroke-width="1.5" stroke="#000000" fill="none" stroke-linecap="round" stroke-linejoin="round">
                                        <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                                        <path d="M10 12a2 2 0 1 0 4 0a2 2 0 0 0 -4 0" />
                                        <path d="M21 12c-2.4 4 -5.4 6 -9 6c-3.6 0 -6.6 -2 -9 -6c2.4 -4 5.4 -6 9 -6c3.6 0 6.6 2 9 6" />
                                    </svg>
                                </button>
                            </label>
                            @if ($errors->has('password'))
                                <span class="help-block">
                                    <strong>{{ $errors->first('password') }}</strong>
                                </span>
                            @endif
                        </div>


                        <div class="tw-dw-form-control">
                            <label class="tw-dw-cursor-pointer tw-dw-label tw-self-start tw-gap-2">
                                <input type="checkbox" name="remember" {{ old('remember') ? 'checked' : '' }}
                                    class="tw-dw-checkbox">
                                <span
                                    class="tw-text-xs md:tw-text-sm tw-font-medium tw-text-black tw-mt-[0.2rem]">@lang('lang_v1.remember_me')</span>
                            </label>
                        </div>
                        @if(config('constants.enable_recaptcha'))
                        <div class="row">
                            <div class="col-md-12">
                                <div class="form-group">
                                    <div class="g-recaptcha" data-sitekey="{{ config('constants.google_recaptcha_key') }}"></div>
                                        @if ($errors->has('g-recaptcha-response'))
                                            <span class="text-danger">{{ $errors->first('g-recaptcha-response') }}</span>
                                        @endif
                                </div>  
                            </div>
                        </div>
                        @endif
                        <button type="submit"
                            class="login-submit tw-bg-gradient-to-r tw-from-indigo-500 tw-to-blue-500 tw-text-sm md:tw-text-base tw-text-white tw-font-semibold tw-w-full tw-max-w-full hover:tw-from-indigo-600 hover:tw-to-blue-600 focus:tw-outline-none focus:tw-ring-2 focus:tw-ring-blue-500 focus:tw-ring-offset-2 active:tw-from-indigo-700 active:tw-to-blue-700">
                            @lang('lang_v1.login')
                        </button>
                    </form>

                    <div class="login-register-link">
                        <!-- Register Url -->

                        @if (!($request->segment(1) == 'business' && $request->segment(2) == 'register'))
                            <!-- Register Url -->
                            @if (config('constants.allow_registration'))
                                <a href="{{ route('business.getRegister') }}@if (!empty(request()->lang)) {{ '?lang=' . request()->lang }} @endif"
                                    class="tw-text-sm tw-font-medium tw-text-gray-500 hover:tw-text-gray-500 tw-mt-2">{{ __('business.not_yet_registered') }}
                                    <span
                                        class="tw-text-sm tw-font-medium tw-bg-gradient-to-r tw-from-indigo-500 tw-to-blue-500 tw-inline-block tw-text-transparent tw-bg-clip-text hover:tw-text-[#467BF5] hover:tw-underline">{{ __('business.register_now') }}</span></a>
                            @endif
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>

@stop
@section('javascript')
    <script type="text/javascript">
        $(document).ready(function() {
            $('#show_hide_icon').off('click');
            $('.change_lang').click(function() {
                window.location = "{{ route('login') }}?lang=" + $(this).attr('value');
            });
            $('a.demo-login').click(function(e) {
                e.preventDefault();
                $('#username').val($(this).data('admin'));
                $('#password').val("{{ $password }}");
                $('form#login-form').submit();
            });

            $('#show_hide_icon').on('click', function(e) {
            e.preventDefault();
            const passwordInput = $('#password');

            if (passwordInput.attr('type') === 'password') {
                passwordInput.attr('type', 'text');
                $('#show_hide_icon').html('<svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-eye-off tw-w-6" viewBox="0 0 24 24" stroke-width="1.5" stroke="#000000" fill="none" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M10.585 10.587a2 2 0 0 0 2.829 2.828"/><path d="M16.681 16.673a8.717 8.717 0 0 1 -4.681 1.327c-3.6 0 -6.6 -2 -9 -6c1.272 -2.12 2.712 -3.678 4.32 -4.674m2.86 -1.146a9.055 9.055 0 0 1 1.82 -.18c3.6 0 6.6 2 9 6c-.666 1.11 -1.379 2.067 -2.138 2.87"/><path d="M3 3l18 18"/></svg>');
            }
            else if (passwordInput.attr('type') === 'text') {
                passwordInput.attr('type', 'password');
                $('#show_hide_icon').html('<svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-eye tw-w-6" viewBox="0 0 24 24" stroke-width="1.5" stroke="#000000" fill="none" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M10 12a2 2 0 1 0 4 0a2 2 0 0 0 -4 0"/><path d="M21 12c-2.4 4 -5.4 6 -9 6c-3.6 0 -6.6 -2 -9 -6c2.4 -4 5.4 -6 9 -6c3.6 0 6.6 2 9 6"/></svg>');
            }
        });
        })
    </script>
@endsection
