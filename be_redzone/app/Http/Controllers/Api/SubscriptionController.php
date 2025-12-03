<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\{Subscription, Subscriber, Plan, Addon, Payment, ServiceCredit};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SubscriptionController extends Controller
{
    /**
     * List all subscriptions
     */
    public function index()
    {
        return response()->json(
            Subscription::with(['subscriber', 'plan', 'addons', 'payments', 'serviceCredits'])
                ->latest()
                ->get()
        );
    }

    /**
     * Show a single subscription with relations
     */
    public function show(Subscription $subscription)
    {
        $subscription->load(['subscriber', 'plan', 'addons', 'payments', 'serviceCredits']);

        return response()->json([
            'subscription' => $subscription,
            'plans' => Plan::all()
        ]);
    }

    /**
     * Create a subscription for a subscriber
     */
    public function store(Request $request, Subscriber $subscriber)
    {

        Log::info($request->all());
        $data = $request->validate([
            'subscriber_id'          => 'required|exists:subscribers,id',
            'plan_id'          => 'required|exists:plans,id',
            'start_date'       => 'required|date',
            'monthly_discount' => 'nullable|numeric|min:0'
        ]);

        // $data['subscriber_id'] = $subscriber->id;

        $subscription = Subscription::create($data);

        return response()->json([
            'message' => 'Subscription created successfully',
            'subscription' => $subscription
        ]);
    }

    /**
     * Update subscription
     */
    public function update(Request $request, Subscription $subscription)
    {
        $data = $request->validate([
            'plan_id'          => 'sometimes|exists:plans,id',
            'start_date'       => 'sometimes|date',
            'monthly_discount' => 'nullable|numeric|min:0',
        ]);

        $subscription->update($data);

        return response()->json([
            'message' => 'Subscription updated successfully',
            'subscription' => $subscription
        ]);
    }

    /**
     * Delete subscription
     */
    public function destroy(Subscription $subscription)
    {
        $subscription->delete();

        return response()->json(['message' => 'Subscription deleted successfully']);
    }

    // ------------------------------------------------------------
    // ADD-ONS / PAYMENTS / SERVICE CREDITS
    // ------------------------------------------------------------

    /**
     * Add Add-on charge
     */
    public function addAddon(Request $request, Subscription $subscription)
    {
        $data = $request->validate([
            'name'       => 'required|string',
            'amount'     => 'required|numeric',
            'bill_month' => 'required|date'
        ]);

        $addon = $subscription->addons()->create($data);

        return response()->json([
            'message' => 'Addon added successfully',
            'addon'   => $addon
        ]);
    }

    /**
     * Add Payment
     */
    public function addPayment(Request $request, Subscription $subscription)
    {
        $data = $request->validate([
            'amount'    => 'required|numeric',
            'paid_at'   => 'required|date',
            'reference' => 'nullable|string',
            'notes'     => 'nullable|string'
        ]);

        $payment = $subscription->payments()->create($data);

        return response()->json([
            'message' => 'Payment recorded successfully',
            'payment' => $payment
        ]);
    }

    /**
     * Add Service Credit (Outage Days)
     */
    public function addCredit(Request $request, Subscription $subscription)
    {
        $data = $request->validate([
            'bill_month'  => 'required|date',
            'outage_days' => 'required|integer|min:0',
            'reason'      => 'nullable|string'
        ]);

        $credit = $subscription->serviceCredits()->create($data);

        return response()->json([
            'message' => 'Service credit added successfully',
            'service_credit' => $credit
        ]);
    }
}
