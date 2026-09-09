<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CollectorRemittance extends Model
{
    public $timestamps = false;
    protected $guarded = ['id'];
    protected $hidden = ['request_key', 'request_hash'];
    protected $casts = ['created_at' => 'datetime'];
}
