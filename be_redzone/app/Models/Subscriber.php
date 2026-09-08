<?php

// app/Models/Subscriber.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;

class Subscriber extends Model
{
    protected $fillable = ['name', 'email', 'phone', 'address'];

    public static function createWithNextId(array $attributes): self
    {
        $connection = (new static)->getConnection();

        for ($attempt = 0; $attempt < 5; $attempt++) {
            try {
                return $connection->transaction(function () use ($attributes) {
                    // Read current rows, not the auto-increment counter left by deleted data.
                    $last = static::query()->orderByDesc('id')->lockForUpdate()->first(['id']);
                    $subscriber = new static($attributes);
                    $subscriber->id = ($last?->id ?? 0) + 1;
                    $subscriber->save();

                    return $subscriber;
                }, 5);
            } catch (UniqueConstraintViolationException $exception) {
                // An empty table may have no row to lock. Retry a competing insert.
                if ($attempt === 4) {
                    throw $exception;
                }
            }
        }
    }
  

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
