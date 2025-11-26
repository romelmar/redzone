<?php

// app/Models/Subscriber.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subscriber extends Model
{
    protected $fillable = ['name','email','phone','address'];

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }
}
