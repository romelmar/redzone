<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\{Subscription, Subscriber, Plan, Addon, Payment, ServiceCredit};
use App\Models\SubscriptionEvent;

use App\Services\BillingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;


class SubscriptionController extends Controller
{
    /**
     * List all subscriptions
     */
    public function index(Request $request, BillingService $billing)
    {

        $perPage = max(1, min(100, (int) $request->get('per_page', 10)));

        $query = Subscription::query()
            ->with(['subscriber', 'plan', 'rates', 'payments', 'addons', 'serviceCredits']);

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
        $sortDir = strtolower($request->get('sort_dir', 'desc')) === 'asc' ? 'asc' : 'desc';

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
        $subscriptions = $query->paginate($perPage)->through(function ($s) use ($billing) {
            // compute current balance server-side
            $currentBalance = $billing->computeFor($s, now()->startOfMonth())['total_due'];

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
public function options(Request $request)
{
    $query = Subscription::query()
        ->with(['subscriber', 'plan']);

    /*
    |--------------------------------------------------------------------------
    | SEARCH (lightweight)
    |--------------------------------------------------------------------------
    */
    if ($request->filled('search')) {
        $search = $request->search;

        $query->where(function ($q) use ($search) {
            $q->whereHas('subscriber', fn($s) =>
                $s->where('name', 'like', "%{$search}%")
            )->orWhereHas('plan', fn($p) =>
                $p->where('name', 'like', "%{$search}%")
            );
        });
    }

    /*
    |--------------------------------------------------------------------------
    | OPTIONAL: ONLY ACTIVE
    |--------------------------------------------------------------------------
    */
    if ($request->get('active_only', true)) {
        $query->where('active', true);
    }

    /*
    |--------------------------------------------------------------------------
    | LIMIT (important for performance)
    |--------------------------------------------------------------------------
    */
    $limit = $request->get('limit', 100);

    $subscriptions = $query
        ->orderByDesc('start_date')
        ->limit($limit)
        ->get()
        ->map(function ($s) {
            return [
                'value' => $s->id,
                'label' => "{$s->subscriber->name} - {$s->plan->name}",
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

        $subscription = \Illuminate\Support\Facades\DB::transaction(fn () => Subscription::create($data));

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

        // $validated = $request->validate([
        //     'subscriber_id'     => ['required', 'exists:subscribers,id'],
        //     'plan_id'           => ['required', 'exists:plans,id'],
        //     'start_date'        => ['required', 'date'],
        //     'end_date'          => ['nullable', 'date'],
        //     'monthly_discount'  => ['nullable', 'numeric', 'min:0'],
        //     'active'            => ['required', 'boolean'],
        //     'collector_name'    => ['nullable', 'string', 'max:255'],
        // ]);

        $data = $request->validate([
            'plan_id'          => 'sometimes|exists:plans,id',
            'start_date'       => 'sometimes|date',
            'monthly_discount' => 'nullable|numeric|min:0',
            'collector_name'    => ['nullable', 'string', 'max:255'],
        ]);

        if (isset($data['start_date']) && !Carbon::parse($data['start_date'])->isSameDay($subscription->start_date)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'start_date' => 'The start date cannot be changed after creation because it defines billing history.',
            ]);
        }
        $subscription->getConnection()->transaction(function () use ($subscription, $data) {
            $locked = Subscription::whereKey($subscription->id)->lockForUpdate()->firstOrFail();
            $locked->update($data);
            $subscription->refresh();
        });

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
        $subscription->getConnection()->transaction(function () use ($subscription) {
            Subscription::whereKey($subscription->id)->lockForUpdate()->firstOrFail();
            abort_if($subscription->payments()->withTrashed()->exists() || $subscription->addons()->exists()
                || $subscription->serviceCredits()->exists() || $subscription->events()->exists()
                || $subscription->start_date->copy()->startOfMonth()->lte(now()->startOfMonth()),
                422, 'This subscription has financial or activity history. Deactivate it instead.');
            $subscription->delete();
        });

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
        $subscription->getConnection()->transaction(function () use ($subscription) {
            $locked = Subscription::whereKey($subscription->id)->lockForUpdate()->firstOrFail();
            abort_if(!$locked->active, 422, 'Already inactive');
            $at = now();
            $locked->update(['active' => false, 'deactivated_at' => $at]);
            $locked->events()->create(['type' => 'deactivate', 'title' => 'Disconnected',
                'description' => 'Recurring fees stop after this month.', 'event_at' => $at]);
            $subscription->refresh();
        });

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
        $days = $subscription->getConnection()->transaction(function () use ($subscription) {
            $locked = Subscription::whereKey($subscription->id)->lockForUpdate()->firstOrFail();
            abort_if($locked->active, 422, 'Already active');
            $cutoff = $locked->deactivated_at ?? $locked->end_date;
            abort_if(!$cutoff, 422, 'The actual disconnection date is required before reactivation.');
            // Preserve legacy disconnections before clearing the current status fields.
            $last = $locked->events()->whereIn('type', ['activate', 'deactivate'])->orderByDesc('event_at')->orderByDesc('id')->first();
            if (!$last || $last->type !== 'deactivate') {
                $locked->events()->create(['type' => 'deactivate', 'title' => 'Disconnected', 'event_at' => $cutoff]);
            }
            $at = now();
            $days = (int) Carbon::parse($cutoff)->diffInDays($at);
            $locked->update(['active' => true, 'deactivated_at' => null, 'end_date' => null, 'reactivated_days_passed' => $days]);
            $locked->events()->create(['type' => 'activate', 'title' => 'Reconnected', 'event_at' => $at]);
            $subscription->refresh();
            return $days;
        });

        return response()->json([
            'message' => 'Subscription activated successfully.',
            'days_deducted' => $days,
            'subscription' => $subscription,
        ]);
    }

    public function assignCollector(Request $request, Subscription $subscription)
    {
        $validated = $request->validate([
            'collector_name' => ['nullable', 'string', 'max:255'],
        ]);

        $oldCollector = $subscription->collector_name;

        $subscription->update([
            'collector_name' => $validated['collector_name'] ?? null,
        ]);

        SubscriptionEvent::create([
            'subscription_id' => $subscription->id,
            'type' => 'collector',
            'title' => 'Collector Assigned',
            'description' => $oldCollector
                ? "Collector changed from {$oldCollector} to " . ($validated['collector_name'] ?: 'Unassigned')
                : "Collector assigned: " . ($validated['collector_name'] ?: 'Unassigned'),
            'event_at' => now(),
        ]);

        return response()->json([
            'message' => 'Collector assigned successfully.',
            'subscription' => $subscription->fresh(['subscriber', 'plan']),
        ]);
    }
}
