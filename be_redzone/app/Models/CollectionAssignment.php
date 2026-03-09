<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CollectionAssignment extends Model
{
    protected $fillable = [
        'subscription_id',
        'assignment_date',
        'collector_name',
        'notes',
    ];

    protected $casts = [
        'assignment_date' => 'date',
    ];

    public function subscription()
    {
        return $this->belongsTo(Subscription::class);
    }
}