<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class PaymentReminderSchedulerSetting extends Model
{
    protected $fillable = [
        'business_id',
        'is_enabled',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
    ];

    public function business()
    {
        return $this->belongsTo(Business::class);
    }
}
