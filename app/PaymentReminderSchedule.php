<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class PaymentReminderSchedule extends Model
{
    protected $fillable = [
        'business_id',
        'schedule_type',
        'scheduled_date',
        'day_of_month',
        'send_time',
        'timezone',
        'report_email',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'scheduled_date' => 'date',
        'is_active' => 'boolean',
    ];

    public function business()
    {
        return $this->belongsTo(Business::class);
    }

    public function runs()
    {
        return $this->hasMany(PaymentReminderScheduleRun::class, 'schedule_id');
    }
}
