<?php

// app/Models/Addon.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Addon extends Model
{
    protected $fillable = ['subscription_id','name','amount','bill_month'];
    protected $casts = ['bill_month' => 'date'];
}

