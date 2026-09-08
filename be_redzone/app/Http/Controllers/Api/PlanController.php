<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use Illuminate\Http\Request;

class PlanController extends Controller
{
    public function index(Request $request)
    {
        $sortBy = $request->get('sort_by', 'name');
        $sortDir = strtolower($request->get('sort_dir', 'asc')) === 'asc' ? 'asc' : 'desc';
        $allowed = ['name', 'price', 'description', 'created_at'];
        $perPage = (int) $request->get('per_page', 10);

        if (!in_array($sortBy, $allowed, true)) {
            $sortBy = 'name';
        }

        return response()->json(
            Plan::orderBy($sortBy, $sortDir)
                ->paginate(max(1, $perPage))
        );
    }

    public function options(Request $request)
    {
        $search = $request->get('search');

        $plans = Plan::query()
            ->when($search, function ($query) use ($search) {
                $query->where('name', 'like', "%{$search}%");
            })
            ->orderBy('name')
            ->limit(20)
            ->get([
                'id',
                'name',
                'price',
                'speed',
            ])
            ->map(function ($plan) {
                return [
                    'id' => $plan->id,
                    'title' => $plan->name,
                    'subtitle' => "₱" . number_format($plan->price, 2) .
                        ($plan->speed ? " • {$plan->speed}" : ""),
                    'price' => $plan->price,
                    'speed' => $plan->speed,
                ];
            });

        return response()->json($plans);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
        ]);

        $plan = Plan::create($data);

        return response()->json([
            'message' => 'Plan created successfully',
            'plan' => $plan
        ]);
    }

    public function show(Plan $plan)
    {
        return response()->json($plan);
    }

    public function update(Request $request, Plan $plan)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
        ]);

        $plan->getConnection()->transaction(function () use ($plan, $data) {
            Plan::whereKey($plan->id)->lockForUpdate()->firstOrFail();
            $plan->refresh();
            $priceChanged = (float) $plan->price !== (float) $data['price'];
            $plan->update($data);
            if ($priceChanged) {
                $plan->subscriptions()->lockForUpdate()->each(function ($subscription) {
                    $subscription->recordRate(now()->startOfMonth()->addMonth());
                });
            }
        });

        return response()->json([
            'message' => 'Plan updated successfully',
            'plan' => $plan
        ]);
    }

    public function destroy(Plan $plan)
    {
        $plan->getConnection()->transaction(function () use ($plan) {
            Plan::whereKey($plan->id)->lockForUpdate()->firstOrFail();
            abort_if($plan->subscriptions()->exists(), 422, 'This plan has subscriptions and cannot be deleted.');
            $plan->delete();
        });

        return response()->json(['message' => 'Plan deleted successfully']);
    }
}
