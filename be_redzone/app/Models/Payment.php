<?php
// app/Models/Payment.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
  protected $fillable = ['subscription_id','amount','payment_date','payment_type','remarks'];
  protected $casts = ['payment_date'=>'date'];

    public function subscription()
    {
        return $this->belongsTo(Subscription::class);
    }
}
