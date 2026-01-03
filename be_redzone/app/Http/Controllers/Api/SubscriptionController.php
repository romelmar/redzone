<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\{Subscription, Subscriber, Plan, Addon, Payment, ServiceCredit};
use App\Services\BillingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class SubscriptionController extends Controller
{
    /**
     * List all subscriptions
     */
    public function index(Request $request)
    {
        Log::info('Fetching subscriptions with filters', $request->all());

        $perPage = (int) $request->get('per_page', 10);

        $query = Subscription::query()
            ->with(['subscriber', 'plan'])
            ->withSum('payments as payments_total', 'amount')
            ->withSum('addons as addons_total', 'amount')
            ->withSum('serviceCredits as credits_total', 'amount');

        /*
        |--------------------------------------------------------------------------
        | SEARCH (subscriber + plan)
        |--------------------------------------------------------------------------
        */
        if ($request->filled('search')) {
            $search = $request->search;

            $query->where(function ($q) use ($search) {
                $q->whereHas(
                    'subscriber',
                    fn($s) =>
                    $s->where('name', 'like', "%{$search}%")
                )
                    ->orWhereHas(
                        'plan',
                        fn($p) =>
                        $p->where('name', 'like', "%{$search}%")
                    );
            });
        }

        /*
        |--------------------------------------------------------------------------
        | ACTIVE FILTER (boolean)
        |--------------------------------------------------------------------------
        */
        if ($request->filled('active')) {
            // frontend sends: active = "active" | "inactive"
            $query->where('active', $request->active === 'active');
        }

        /*
        |--------------------------------------------------------------------------
        | PLAN FILTER
        |--------------------------------------------------------------------------
        */
        if ($request->filled('plan_id')) {
            $query->where('plan_id', $request->plan_id);
        }

        /*
        |--------------------------------------------------------------------------
        | SORTING
        |--------------------------------------------------------------------------
        */
        $sortBy  = $request->get('sort_by', 'start_date');
        $sortDir = $request->get('sort_dir', 'desc');

        match ($sortBy) {
            'subscriber_name' => $query
                ->join('subscribers', 'subscriptions.subscriber_id', '=', 'subscribers.id')
                ->orderBy('subscribers.name', $sortDir)
                ->select('subscriptions.*'),

            'plan_name' => $query
                ->join('plans', 'subscriptions.plan_id', '=', 'plans.id')
                ->orderBy('plans.name', $sortDir)
                ->select('subscriptions.*'),

            'monthly_discount' => $query->orderBy('monthly_discount', $sortDir),
            'start_date'       => $query->orderBy('start_date', $sortDir),
            'active'           => $query->orderBy('active', $sortDir),

            default => $query->orderBy('start_date', 'desc'),
        };

        /*
        |--------------------------------------------------------------------------
        | PAGINATION
        |--------------------------------------------------------------------------
        */
        $subscriptions = $query->paginate($perPage)->through(function ($s) {
            // compute current balance server-side
            $monthly = $s->plan?->price ?? 0;
            $discount = $s->monthly_discount ?? 0;

            $currentBalance =
                $monthly
                - $discount
                + ($s->addons_total ?? 0)
                - ($s->credits_total ?? 0)
                - ($s->payments_total ?? 0);

            return [
                'id'                => $s->id,
                'subscriber_id'     => $s->subscriber_id,
                'plan_id'           => $s->plan_id,

                'subscriber'        => $s->subscriber,
                'plan'              => $s->plan,

                'start_date'        => $s->start_date,
                'end_date'          => $s->end_date,

                'monthly_discount'  => $s->monthly_discount,
                'active'            => (bool) $s->active,

                'current_balance'   => round($currentBalance, 2),

                'created_at'        => $s->created_at,
                'updated_at'        => $s->updated_at,
            ];
        });

        return response()->json($subscriptions);
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
            'credit_month' => 'required|date'
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
            'payment_date'   => 'required|date',
            'remarks'     => 'nullable|string'
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
            'credit_month'  => 'required|date',
            'outage_days' => 'required|integer|min:0',
            'reason'      => 'nullable|string'
        ]);

        $credit = $subscription->serviceCredits()->create($data);

        return response()->json([
            'message' => 'Service credit added successfully',
            'service_credit' => $credit
        ]);
    }

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
