<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OnlinePaymentRequest extends Model
{
    protected $guarded = ['id'];
    protected $hidden = ['token_hash', 'token_encrypted', 'checkout_url'];
    protected $casts = [
        'snapshot' => 'array', 'expires_at' => 'datetime', 'billing_month' => 'date',
        'settled_at' => 'datetime', 'amount_centavos' => 'integer',
    ];

    public function subscription() { return $this->belongsTo(Subscription::class); }
}
