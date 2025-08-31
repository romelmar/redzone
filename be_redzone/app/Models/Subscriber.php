<?php

// app/Models/Subscriber.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Subscriber extends Model
{
    protected $fillable = ['name', 'email', 'phone', 'address'];

    public function subscriptions()
    {
        return $this->hasMany(Subscription::class);
    }

    // Method to compute the balance for all subscriptions of the subscriber
    public function computeBalance()
    {
        $balance = 0;

        // Iterate through each subscription of the subscriber
        foreach ($this->subscriptions as $subscription) {
            $balance += $subscription->computeBalance();
        }

        return $balance;
    }
}
