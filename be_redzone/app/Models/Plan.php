<?php

// app/Models/Plan.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Plan extends Model
{
    protected $fillable = ['name', 'description', 'price'];

    public function subscriptions()
    {
        return $this->hasMany(Subscription::class);
    }

    public function addons()
    {
        return $this->belongsToMany(Addon::class);
    }
}
