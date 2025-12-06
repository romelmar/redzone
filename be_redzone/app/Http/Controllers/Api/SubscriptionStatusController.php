<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SubscriptionStatusController extends Controller
{
    /**
     * DEACTIVATE subscription.
     * Stores deactivate date.
     */
    public function deactivate(Subscription $subscription)
    {
        Log::info('Deactivating subscription ID: ' . $subscription->id);
        if (!$subscription->active) {
            return response()->json(['message' => 'Already inactive'], 422);
        }

        $subscription->update([
            'active'          => false,
            'deactivated_at'  => now(),
        ]);

        return response()->json([
            'message' => 'Subscription deactivated successfully.',
            'subscription' => $subscription,
        ]);
    }

    /**
     * ACTIVATE subscription.
     * Computes days elapsed since last deactivation
     * and stores into `reactivated_days_passed`.
     */
    public function activate(Subscription $subscription)
    {
        Log::info('subscription: ' . $subscription);
        if ($subscription->active) {
            return response()->json(['message' => 'Already active'], 422);
        }

        if (!$subscription->deactivated_at) {
            return response()->json(['message' => 'Deactivate date missing.'], 422);
        }

        // Compute how many days subscription was inactive
        $days = Carbon::parse($subscription->deactivated_at)->diffInDays(now());

        $subscription->update([
            'active'                  => true,
            'reactivated_days_passed' => $days,
            'deactivated_at'          => null, // clear
        ]);

        return response()->json([
            'message' => 'Subscription activated successfully.',
            'days_deducted' => $days,
            'subscription' => $subscription,
        ]);
    }
}
