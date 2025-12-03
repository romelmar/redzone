<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Addon;
use Illuminate\Http\Request;

class AddonController extends Controller
{
    public function index(Request $request)
    {
        $query = Addon::query()->with('subscription.subscriber');

        if ($request->filled('subscription_id')) {
            $query->where('subscription_id', $request->subscription_id);
        }

        return $query->latest()->paginate(20);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'subscription_id' => 'required|exists:subscriptions,id',
            'name'            => 'required|string|max:255',
            'amount'          => 'required|numeric|min:0',
            'bill_month'      => 'required|date',
        ]);

        return Addon::create($data);
    }

    public function show(Addon $addon)
    {
        return $addon;
    }

    public function update(Request $request, Addon $addon)
    {
        $data = $request->validate([
            'name'       => 'sometimes|string|max:255',
            'amount'     => 'sometimes|numeric|min:0',
            'bill_month' => 'sometimes|date',
        ]);

        $addon->update($data);
        return $addon;
    }

    public function destroy(Addon $addon)
    {
        $addon->delete();
        return response()->noContent();
    }
}
