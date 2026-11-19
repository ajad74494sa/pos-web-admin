@extends('layouts.app')
@section('title', __('report.z_report'))

@section('content')
    <section class="content-header">
        <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">
            @lang('report.z_report')
            <small class="tw-text-sm md:tw-text-base tw-text-gray-700 tw-font-semibold">@lang('report.z_report_msg')</small>
        </h1>
    </section>

    <section class="content">
        <div class="print_section">
            <h2>{{ session()->get('business.name') }} - @lang('report.z_report')</h2>
        </div>

        <div class="row no-print">
            <div class="col-md-3 col-xs-12">
                <div class="input-group">
                    <span class="input-group-addon bg-light-blue"><i class="fa fa-map-marker"></i></span>
                    {!! Form::select('location_id', $business_locations, null, ['class' => 'form-control select2', 'id' => 'z_report_location_filter']) !!}
                </div>
            </div>
            <div class="col-md-3 col-xs-12">
                <button type="button" class="tw-dw-btn tw-dw-btn-primary tw-text-white tw-dw-btn-sm" id="z_report_date_filter">
                    <span><i class="fa fa-calendar"></i> {{ __('messages.filter_by_date') }}</span>
                    <i class="fa fa-caret-down"></i>
                </button>
            </div>
            <div class="col-md-6 col-xs-12 tw-flex tw-items-center tw-justify-end tw-gap-2">
                <button class="tw-dw-btn tw-dw-btn-primary tw-text-white tw-dw-btn-sm no-print" onclick="window.print();">
                    <i class="fa fa-print"></i> @lang('messages.print')
                </button>
            </div>
        </div>

        <div class="row tw-mt-4">
            <div id="z_report_data"></div>
        </div>
    </section>
@stop

@section('javascript')
    <script type="text/javascript">
        $(document).ready(function() {
            var z_report_data_div = $('#z_report_data');

            function updateZReport() {
                if (typeof $('#z_report_date_filter').data('daterangepicker') == 'undefined') {
                    return;
                }
                var start = $('#z_report_date_filter').data('daterangepicker').startDate.format('YYYY-MM-DD');
                var end = $('#z_report_date_filter').data('daterangepicker').endDate.format('YYYY-MM-DD');

                z_report_data_div.html('<div class="text-center"><i class="fas fa-sync fa-spin fa-fw fa-2x"></i></div>');
                $.ajax({
                    method: 'GET',
                    url: '/reports/z-report',
                    dataType: 'html',
                    data: {
                        start_date: start,
                        end_date: end,
                        location_id: $('#z_report_location_filter').val()
                    },
                    success: function(html) {
                        z_report_data_div.html(html);
                        __currency_convert_recursively(z_report_data_div);
                    }
                });
            }

            if ($('#z_report_date_filter').length == 1) {
                $('#z_report_date_filter').daterangepicker(dateRangeSettings, function(start, end) {
                    $('#z_report_date_filter span').html(
                        start.format(moment_date_format) + ' ~ ' + end.format(moment_date_format)
                    );
                    updateZReport();
                });
                updateZReport();
            }

            $('#z_report_location_filter').change(updateZReport);
        });
    </script>
@endsection
