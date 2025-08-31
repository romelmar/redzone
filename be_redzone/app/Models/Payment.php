<?php
// app/Models/Payment.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $fillable = ['subscription_id', 'amount','payment_type', 'note','payment_date'];

    public function subscription()
    {
        return $this->belongsTo(Subscription::class);
    }
}
