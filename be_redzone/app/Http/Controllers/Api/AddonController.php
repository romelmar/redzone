<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Addon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AddonController extends Controller
{
    /**
     * List all addons (optionally filtered by subscription_id).
     */
    public function index(Request $request)
    {
        $query = Addon::with('subscription.subscriber');

        if ($request->filled('subscription_id')) {
            $query->where('subscription_id', $request->subscription_id);
        }

        return $query->latest()->paginate(20);
    }

    /**
     * Create a new addon.
     */
    public function store(Request $request)
    {
        Log::info($request->all());
        $data = $request->validate([
            'subscription_id' => 'required|exists:subscriptions,id',
            'name'            => 'required|string|max:255',
            'amount'          => 'required|numeric|min:0',
            'credit_month'      => 'nullable|date',  // optional
            'description'      => 'required|string|max:255',
        ]);
        

        return Addon::create($data)->load('subscription.subscriber');
    }

    /**
     * Show single addon.
     */
    public function show(Addon $addon)
    {
        return $addon->load('subscription.subscriber');
    }

    /**
     * Update addon.
     */
    public function update(Request $request, Addon $addon)
    {
        $data = $request->validate([
            'subscription_id' => 'sometimes|exists:subscriptions,id',
            'name'            => 'sometimes|string|max:255',
            'amount'          => 'sometimes|numeric|min:0',
            'credit_month'      => 'nullable|date',
        ]);

        $addon->update($data);

        return $addon->load('subscription.subscriber');
    }

    /**
     * Delete addon.
     */
    public function destroy(Addon $addon)
    {
        $addon->delete();
        return response()->noContent();
    }
}
