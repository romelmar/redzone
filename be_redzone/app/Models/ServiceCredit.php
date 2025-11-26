<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ServiceCredit extends Model
{
    protected $fillable = ['subscription_id','bill_month','outage_days','reason'];
    protected $casts = ['bill_month' => 'date'];
}