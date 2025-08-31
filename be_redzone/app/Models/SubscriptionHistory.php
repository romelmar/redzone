<?php

// app/Models/SubscriptionHistory.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SubscriptionHistory extends Model
{
    use HasFactory;

    protected $fillable = [
        'subscription_id',
        'action',
        'updated_at',
    ];

    public function subscription()
    {
        return $this->belongsTo(Subscription::class);
    }

    public $timestamps = true; // This is the default and ensures that updated_at is automatically managed
}
