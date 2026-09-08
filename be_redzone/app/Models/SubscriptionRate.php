<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SubscriptionRate extends Model
{
    public $timestamps = false;
    protected $dateFormat = 'Y-m-d';

    protected $fillable = ['effective_from', 'price', 'discount'];
    protected $casts = ['effective_from' => 'date'];
}
