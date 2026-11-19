<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class PaymentReminderOtpChallenge extends Model
{
    protected $fillable = [
        'business_id',
        'schedule_run_id',
        'purpose',
        'reference_key',
        'code_hash',
        'status',
        'attempts',
        'delivered_via',
        'delivery_error',
        'expires_at',
        'confirmed_at',
        'consumed_at',
        'created_by',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'confirmed_at' => 'datetime',
        'consumed_at' => 'datetime',
    ];

    public function business()
    {
        return $this->belongsTo(Business::class);
    }

    public function scheduleRun()
    {
        return $this->belongsTo(PaymentReminderScheduleRun::class, 'schedule_run_id');
    }
}
