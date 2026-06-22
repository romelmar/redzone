<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Addon;
use Illuminate\Http\Request;

class AddonController extends Controller
{
    public function index(Request $request)
    {
        $perPage = (int) $request->get('per_page', 10);
        $sortBy = $request->get('sort_by', 'credit_month');
        $sortDir = strtolower($request->get('sort_dir', 'desc')) === 'asc' ? 'asc' : 'desc';

        $query = Addon::query()
            ->with(['subscription.subscriber', 'subscription.plan'])
            ->select('addons.*');

        if ($request->filled('subscription_id')) {
            $query->where('subscription_id', $request->subscription_id);
        }

        if ($request->filled('credit_month')) {
            $query->whereDate('credit_month', $request->credit_month);
        }

        if ($request->filled('search')) {
            $search = trim($request->search);

            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhereHas('subscription.subscriber', function ($sq) use ($search) {
                        $sq->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    })
                    ->orWhereHas('subscription.plan', function ($pq) use ($search) {
                        $pq->where('name', 'like', "%{$search}%");
                    });
            });
        }

        if ($sortBy === 'subscription_name') {
            $query->leftJoin('subscriptions', 'addons.subscription_id', '=', 'subscriptions.id')
                ->leftJoin('subscribers', 'subscriptions.subscriber_id', '=', 'subscribers.id')
                ->orderBy('subscribers.name', $sortDir);
        } elseif ($sortBy === 'plan_name') {
            $query->leftJoin('subscriptions', 'addons.subscription_id', '=', 'subscriptions.id')
                ->leftJoin('plans', 'subscriptions.plan_id', '=', 'plans.id')
                ->orderBy('plans.name', $sortDir);
        } elseif (in_array($sortBy, ['name', 'amount', 'credit_month'], true)) {
            $query->orderBy($sortBy, $sortDir);
        } else {
            $query->latest('credit_month');
        }

        return response()->json(
            $query->paginate($perPage)
        );
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'subscription_id' => ['required', 'exists:subscriptions,id'],
            'name'            => ['required', 'string', 'max:255'],
            'description'     => ['nullable', 'string'],
            'amount'          => ['required', 'numeric', 'min:0.01'],
            'credit_month'      => ['required', 'date'],
        ]);

        $addon = Addon::create([
            'subscription_id' => $data['subscription_id'],
            'name'            => $data['name'],
            'description'     => $data['description'] ?? null,
            'amount'          => $data['amount'],
            'credit_month'      => \Carbon\Carbon::parse($data['credit_month'])->startOfMonth()->toDateString(),
        ]);

        return response()->json(
            $addon->load(['subscription.subscriber', 'subscription.plan']),
            201
        );
    }

    public function show(Addon $addon)
    {
        return response()->json(
            $addon->load(['subscription.subscriber', 'subscription.plan'])
        );
    }

    public function update(Request $request, Addon $addon)
    {
        $data = $request->validate([
            'subscription_id' => ['sometimes', 'exists:subscriptions,id'],
            'name'            => ['sometimes', 'string', 'max:255'],
            'description'     => ['nullable', 'string'],
            'amount'          => ['sometimes', 'numeric', 'min:0.01'],
            'credit_month'      => ['sometimes', 'date'],
        ]);

        if (array_key_exists('credit_month', $data)) {
            $data['credit_month'] = \Carbon\Carbon::parse($data['credit_month'])->startOfMonth()->toDateString();
        }

        $addon->update($data);

        return response()->json(
            $addon->load(['subscription.subscriber', 'subscription.plan'])
        );
    }

    public function destroy(Addon $addon)
    {
        $addon->delete();

        return response()->noContent();
    }
}