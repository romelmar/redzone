<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\{Subscription, Subscriber, Plan, Addon, Payment, ServiceCredit};
use App\Services\BillingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SubscriptionController extends Controller
{
    /**
     * List all subscriptions
     */
public function index(Request $request, BillingService $billing)
{
    $page      = $request->get('page', 1);
    $perPage   = $request->get('per_page', 20);
    $search    = $request->get('search');
    $active    = $request->get('active'); // "1", "0" or null
    $planId    = $request->get('plan_id');
    $sortBy    = $request->get('sort_by', 'start_date');
    $sortDir   = $request->get('sort_dir', 'desc');

    // Only allow these columns to be sorted
    $allowedSort = [
        'subscriber_name',
        'plan_name',
        'start_date',
        'monthly_discount',
        'current_balance',
        'active',
    ];

    if (!in_array($sortBy, $allowedSort)) {
        $sortBy = 'start_date';
    }

    // Map frontend sort keys → DB columns
    $sortColumnMap = [
        'subscriber_name'   => 'subscribers.name',
        'plan_name'         => 'plans.name',
        'start_date'        => 'subscriptions.start_date',
        'monthly_discount'  => 'subscriptions.monthly_discount',
        'active'            => 'subscriptions.active',
        'current_balance'   => 'subscriptions.id', // sort later in memory
    ];

    $query = Subscription::query()
        ->with(['subscriber', 'plan'])
        ->leftJoin('subscribers', 'subscribers.id', '=', 'subscriptions.subscriber_id')
        ->leftJoin('plans', 'plans.id', '=', 'subscriptions.plan_id')
        ->select('subscriptions.*');

    // ─────────────────────────────────────────────
    // SEARCH
    // ─────────────────────────────────────────────
    if ($search) {
        $query->where(function ($q) use ($search) {
            $q->where('subscribers.name', 'LIKE', "%{$search}%")
              ->orWhere('plans.name', 'LIKE', "%{$search}%");
        });
    }

    // ─────────────────────────────────────────────
    // ACTIVE FILTER (boolean)
    // ─────────────────────────────────────────────
    if ($active !== null && $active !== '') {
        $query->where('subscriptions.active', (int)$active);
    }

    // ─────────────────────────────────────────────
    // PLAN FILTER
    // ─────────────────────────────────────────────
    if ($planId) {
        $query->where('subscriptions.plan_id', $planId);
    }

    // ─────────────────────────────────────────────
    // SORTING
    // ─────────────────────────────────────────────
    if ($sortBy === 'current_balance') {
        // sort later manually
        $query->orderBy('subscriptions.start_date', $sortDir);
    } else {
        $query->orderBy($sortColumnMap[$sortBy], $sortDir);
    }

    // ─────────────────────────────────────────────
    // PAGINATION
    // ─────────────────────────────────────────────
    $paginator = $query->paginate($perPage);

    // ─────────────────────────────────────────────
    // ADD current_balance FOR EACH SUBSCRIPTION
    // ─────────────────────────────────────────────
    $paginator->getCollection()->transform(function ($sub) use ($billing) {

        $calc = $billing->computeFor($sub, now()->startOfMonth());

        // You can change to previous_balance or total_due as preferred
        $sub->current_balance = $calc['total_due'];

        return $sub;
    });

    // Sort by computed balance if needed
    if ($sortBy === 'current_balance') {
        $sorted = $paginator->getCollection()->sortBy(
            'current_balance',
            SORT_REGULAR,
            $sortDir === 'desc'
        )->values();

        $paginator->setCollection($sorted);
    }

    return response()->json($paginator);
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
