<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class PaymentReminderScheduleRun extends Model
{
    protected $fillable = [
        'schedule_id',
        'run_key',
        'scheduled_for',
        'status',
        'customer_count',
        'submitted_count',
        'failed_count',
        'report_details',
        'report_sent',
        'report_error',
        'started_at',
        'finished_at',
    ];

    protected $casts = [
        'scheduled_for' => 'datetime',
        'report_sent' => 'boolean',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function schedule()
    {
        return $this->belongsTo(PaymentReminderSchedule::class, 'schedule_id');
    }

    public function otpChallenge()
    {
        return $this->hasOne(PaymentReminderOtpChallenge::class, 'schedule_run_id');
    }
}
