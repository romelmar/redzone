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

        $query = Addon::query()
            ->with(['subscription.subscriber', 'subscription.plan']);

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

        return response()->json(
            $query->latest('credit_month')->paginate($perPage)
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