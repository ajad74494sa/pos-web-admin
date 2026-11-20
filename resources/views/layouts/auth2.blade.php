<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<html>

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <!-- Tell the browser to be responsive to screen width -->
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- CSRF Token -->
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title') - {{ config('app.name', 'POS') }}</title>

    @include('layouts.partials.css')

    @include('layouts.partials.extracss_auth')

    <style>
        .auth2-content {
            box-sizing: border-box;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
            min-height: 100dvh;
        }

        .auth2-header,
        .auth2-header__primary,
        .auth2-header__actions {
            display: flex;
            align-items: center;
        }

        .auth2-header {
            width: 100%;
            justify-content: space-between;
            gap: 16px;
            padding: 8px clamp(0px, 2vw, 24px);
        }

        .auth2-header__primary,
        .auth2-header__actions {
            flex-wrap: wrap;
            gap: 16px;
        }

        .auth2-header__primary img {
            display: block;
            width: 48px;
            height: 48px;
            object-fit: contain;
        }

        .auth2-header__register {
            display: flex;
            width: 96px;
            height: 44px;
            align-items: center;
            justify-content: center;
            border: 1px solid rgba(255, 255, 255, .8);
            border-radius: 999px;
        }

        .auth2-header__actions details {
            margin: 0 !important;
        }

        @media (max-width: 640px) {
            .auth2-content {
                padding-right: 12px !important;
                padding-left: 12px !important;
            }

            .auth2-header {
                flex-direction: column;
                justify-content: center;
                gap: 8px;
                padding: 4px 0 10px;
            }

            .auth2-header__primary,
            .auth2-header__actions {
                justify-content: center;
                gap: 12px;
            }

            .auth2-header__primary img {
                width: 42px;
                height: 42px;
            }

            .auth2-header__register {
                width: 84px;
                height: 40px;
            }
        }

        @media (max-width: 640px) and (max-height: 720px) {
            .auth2-header {
                gap: 6px;
                padding-bottom: 6px;
            }

            .auth2-header__primary,
            .auth2-header__actions {
                gap: 8px;
            }

            .auth2-header__primary img {
                width: 36px;
                height: 36px;
            }

            .auth2-header__register {
                height: 36px;
            }
        }
    </style>

    <!--[if lt IE 9]>
    <script src="https://oss.maxcdn.com/html5shiv/3.7.3/html5shiv.min.js"></script>
    <script src="https://oss.maxcdn.com/respond/1.4.2/respond.min.js"></script>
    <![endif]-->
    <script src='https://www.google.com/recaptcha/api.js'></script>

</head>

<body class="pace-done" data-new-gr-c-s-check-loaded="14.1172.0" data-gr-ext-installed="" cz-shortcut-listen="true">
    @inject('request', 'Illuminate\Http\Request')
    @if (session('status') && session('status.success'))
        <input type="hidden" id="status_span" data-status="{{ session('status.success') }}"
            data-msg="{{ session('status.msg') }}">
    @endif
    <div class="container-fluid">
        <div class="row eq-height-row">
            <div class="col-md-12 col-sm-12 col-xs-12 right-col auth2-content tw-pt-4 tw-pb-10 tw-px-5">
                <header class="auth2-header">
                    {{-- <div
                        class="lg:tw-w-16 md:tw-h-16 tw-w-12 tw-h-12 tw-flex tw-items-center tw-justify-center tw-mx-auto tw-overflow-hidden tw-bg-white tw-rounded-full tw-p-0.5 tw-mb-4">
                        <img src="{{ asset('img/logo-small.png')}}" alt="lock" class="tw-rounded-full tw-object-fill" />
                    </div> --}}

                    <div class="auth2-header__primary">
                        <a href="{{ url('/') }}">
                            <img src="{{ asset('uploads/cms/logo.png') }}" alt="KR Soft Pos" />
                        </a>
                        @if(config('constants.SHOW_REPAIR_STATUS_LOGIN_SCREEN') && Route::has('repair-status'))
                            <a class="tw-text-white tw-font-medium tw-text-sm md:tw-text-base hover:tw-text-white"
                                href="{{ action([\Modules\Repair\Http\Controllers\CustomerRepairStatusController::class, 'index']) }}">
                                @lang('repair::lang.repair_status')
                            </a>
                        @endif
                        
                        @if(Route::has('member_scanner'))
                            <a class="tw-text-white tw-font-medium tw-text-sm md:tw-text-base hover:tw-text-white"
                                href="{{ action([\Modules\Gym\Http\Controllers\MemberController::class, 'member_scanner']) }}">
                                @lang('gym::lang.gym_member_profile')
                            </a>
                        @endif
                    </div>

                    <div class="auth2-header__actions">
                        @if (!($request->segment(1) == 'business' && $request->segment(2) == 'register'))
                            <!-- Register Url -->
                            @if (config('constants.allow_registration'))
                            {{-- <span
                                class="tw-text-white tw-font-medium tw-text-sm md:tw-text-base">{{ __('business.not_yet_registered') }}
                            </span> --}}

                            <div class="auth2-header__register">
                             <a href="{{ route('business.getRegister')}}@if(!empty(request()->lang)){{'?lang='.request()->lang}}@endif"
                                    class="tw-text-white tw-font-medium tw-text-sm md:tw-text-base hover:tw-text-white">
                                    {{ __('business.register') }}</a>
                            </div>

                                <!-- pricing url -->
                                @if (Route::has('pricing') && config('app.env') != 'demo' && $request->segment(1) != 'pricing')
                                    &nbsp; <a class="tw-text-white tw-font-medium tw-text-sm md:tw-text-base hover:tw-text-white"
                                        href="{{ action([\Modules\Superadmin\Http\Controllers\PricingController::class, 'index']) }}">@lang('superadmin::lang.pricing')</a>
                                @endif
                            @endif
                        @endif
                        @if ($request->segment(1) != 'login')
                            <a class="tw-text-white tw-font-medium tw-text-sm md:tw-text-base hover:tw-text-white"
                                href="{{ action([\App\Http\Controllers\Auth\LoginController::class, 'login'])}}@if(!empty(request()->lang)){{'?lang='.request()->lang}}@endif">{{ __('business.sign_in') }}</a>
                        @endif
                        @include('layouts.partials.language_btn')
                    </div>
                </header>
                @yield('content')
            </div>
        </div>
    </div>


    @include('layouts.partials.javascripts')

    <!-- Scripts -->
    <script src="{{ asset('js/login.js?v=' . $asset_v) }}"></script>

    @yield('javascript')

    <script type="text/javascript">
        $(document).ready(function() {
            $('.select2_register').select2();

            // $('input').iCheck({
            //     checkboxClass: 'icheckbox_square-blue',
            //     radioClass: 'iradio_square-blue',
            //     increaseArea: '20%' // optional
            // });
        });
    </script>
    <style>
        .wizard>.content {
            background-color: white !important;
        }
    </style>
</body>

</html>
