<?php 
// app/Services/SubscriptionService.php
namespace App\Services;

use App\Models\Subscription;
use App\Models\User;

class SubscriptionService
{
    public function calculateSubscriptionPrice(User $user)
    {
        $discount = $user->discount;
        $basePrice = $user->subscription->plan->price;
        
        if ($discount) {
            $discountPercentage = $discount->percentage;
            $discountedPrice = $basePrice - ($basePrice * $discountPercentage / 100);
            return $discountedPrice;
        }
        
        return $basePrice;
    }
}