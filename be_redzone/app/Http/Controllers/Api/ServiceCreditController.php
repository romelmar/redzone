<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ServiceCredit;
use Illuminate\Http\Request;

class ServiceCreditController extends Controller
{
    public function index(Request $request)
    {
        $query = ServiceCredit::query()->with('subscription.subscriber');

        if ($request->filled('subscription_id')) {
            $query->where('subscription_id', $request->subscription_id);
        }

        return $query->latest()->paginate(20);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'subscription_id' => 'required|exists:subscriptions,id',
            'bill_month'      => 'required|date',
            'outage_days'     => 'required|integer|min:0',
            'reason'          => 'nullable|string',
        ]);

        return ServiceCredit::create($data);
    }

    public function show(ServiceCredit $serviceCredit)
    {
        return $serviceCredit;
    }

    public function update(Request $request, ServiceCredit $serviceCredit)
    {
        $data = $request->validate([
            'bill_month'  => 'sometimes|date',
            'outage_days' => 'sometimes|integer|min:0',
            'reason'      => 'nullable|string',
        ]);

        $serviceCredit->update($data);
        return $serviceCredit;
    }

    public function destroy(ServiceCredit $serviceCredit)
    {
        $serviceCredit->delete();
        return response()->noContent();
    }
}
