<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ServiceCredit;
use App\Models\Subscription;
use Carbon\Carbon;
use Illuminate\Http\Request;

class ServiceCreditController extends Controller
{
    public function index(Request $request)
    {
        $sortBy = $request->get('sort_by', 'credit_month');
        $sortDir = strtolower($request->get('sort_dir', 'desc')) === 'asc' ? 'asc' : 'desc';

        $query = ServiceCredit::query()
            ->with('subscription.subscriber')
            ->select('service_credits.*');

        if ($request->filled('subscription_id')) {
            $query->where('subscription_id', $request->subscription_id);
        }

        if ($sortBy === 'subscription_name') {
            $query->leftJoin('subscriptions', 'service_credits.subscription_id', '=', 'subscriptions.id')
                ->leftJoin('subscribers', 'subscriptions.subscriber_id', '=', 'subscribers.id')
                ->orderBy('subscribers.name', $sortDir);
        } elseif (in_array($sortBy, ['credit_month', 'outage_days', 'reason'], true)) {
            $query->orderBy($sortBy, $sortDir);
        } else {
            $query->orderBy('credit_month', $sortDir);
        }

        return $query->paginate(20);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'subscription_id' => 'required|exists:subscriptions,id',
            'credit_month'    => 'required|date',
            'outage_days'            => 'required|integer|min:1',
            'reason'          => 'nullable|string',
        ]);

        $subscription = Subscription::with('plan')->findOrFail($data['subscription_id']);

        $month = Carbon::parse($data['credit_month'])->startOfMonth();
        $daysInMonth = $month->daysInMonth;
        if ($data['outage_days'] > $daysInMonth) {
            throw \Illuminate\Validation\ValidationException::withMessages(['outage_days' => 'Outage days cannot exceed the days in the month.']);
        }

        $amount = round(
            ($subscription->rateForMonth($month)['price'] / $daysInMonth) * $data['outage_days'],
            2
        );

        $credit = ServiceCredit::create([
            'subscription_id' => $subscription->id,
            'credit_month'    => $month,
            'outage_days'            => $data['outage_days'],
            'amount'          => $amount,
            'reason'          => $data['reason'] ?? null,
        ]);

        return response()->json($credit, 201);
    }

    public function show(ServiceCredit $serviceCredit)
    {
        return $serviceCredit;
    }

    public function update(Request $request, ServiceCredit $serviceCredit)
    {
        $data = $request->validate([
            'credit_month'  => 'sometimes|date',
            'outage_days' => 'sometimes|integer|min:0',
            'reason'      => 'nullable|string',
        ]);

        if (isset($data['credit_month']) || isset($data['outage_days'])) {
            $month = Carbon::parse($data['credit_month'] ?? $serviceCredit->credit_month)->startOfMonth();
            $days = $data['outage_days'] ?? $serviceCredit->outage_days;
            if ($days > $month->daysInMonth) {
                throw \Illuminate\Validation\ValidationException::withMessages(['outage_days' => 'Outage days cannot exceed the days in the month.']);
            }
            $data['credit_month'] = $month;
            $data['amount'] = round($serviceCredit->subscription->rateForMonth($month)['price'] / $month->daysInMonth * $days, 2);
        }
        $serviceCredit->update($data);
        return $serviceCredit;
    }

    public function destroy(ServiceCredit $serviceCredit)
    {
        $serviceCredit->delete();
        return response()->noContent();
    }
}
