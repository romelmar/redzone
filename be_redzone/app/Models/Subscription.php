<?php
// app/Models/Subscription.php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subscription extends Model
{
    protected $fillable = [
        'subscriber_id','plan_id','start_date','end_date',
        'monthly_discount','active',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date'   => 'date',
        'active'     => 'bool',
    ];

    public function subscriber(): BelongsTo
    {
        return $this->belongsTo(Subscriber::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function addons(): HasMany
    {
        return $this->hasMany(Addon::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function serviceCredits(): HasMany
    {
        return $this->hasMany(ServiceCredit::class);
    }

    // Due date = same day as start_date, per month
    public function dueDateForMonth(Carbon $month): Carbon
    {
        $day = (int) $this->start_date->day;
        return (clone $month)->day($day);
    }
}
