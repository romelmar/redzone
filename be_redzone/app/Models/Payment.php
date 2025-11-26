<?php
// app/Models/Payment.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $fillable = ['subscription_id','amount','paid_at','reference','notes'];
    protected $casts = ['paid_at' => 'date'];
}

