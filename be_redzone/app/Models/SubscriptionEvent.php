<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SubscriptionEvent extends Model
{
    protected $fillable = [
        'subscription_id',
        'type',
        'title',
        'description',
        'event_at',
    ];

    protected $casts = [
        'event_at' => 'datetime',
    ];

    public function subscription()
    {
        return $this->belongsTo(Subscription::class);
    }
}