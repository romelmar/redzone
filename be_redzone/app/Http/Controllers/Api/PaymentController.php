<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function index(Request $request)
    {
        $perPage = (int) $request->get('per_page', 10);

        $query = Payment::query()
            ->with(['subscription.subscriber', 'subscription.plan']);

        if ($request->filled('subscription_id')) {
            $query->where('subscription_id', $request->subscription_id);
        }

        if ($request->filled('payment_type')) {
            $query->where('payment_type', $request->payment_type);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('payment_date', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('payment_date', '<=', $request->date_to);
        }

        if ($request->filled('search')) {
            $search = trim($request->search);

            $query->where(function ($q) use ($search) {
                if (is_numeric($search)) {
                    $q->orWhere('id', (int) $search)
                      ->orWhere('amount', 'like', "%{$search}%");
                }

                $q->orWhere('remarks', 'like', "%{$search}%")
                  ->orWhere('payment_type', 'like', "%{$search}%")
                  ->orWhereHas('subscription.subscriber', function ($sq) use ($search) {
                      $sq->where('name', 'like', "%{$search}%")
                         ->orWhere('email', 'like', "%{$search}%")
                         ->orWhere('phone', 'like', "%{$search}%");
                  })
                  ->orWhereHas('subscription.plan', function ($pq) use ($search) {
                      $pq->where('name', 'like', "%{$search}%");
                  });
            });
        }

        return response()->json(
            $query->latest('payment_date')->paginate($perPage)
        );
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'subscription_id' => 'required|exists:subscriptions,id',
            'amount'          => 'required|numeric|min:0.01',
            'payment_date'    => 'required|date',
            'payment_type'    => 'required|in:payment,offset,adjustment',
            'remarks'         => 'nullable|string',
        ]);

        $payment = Payment::create($data);

        return response()->json(
            $payment->load(['subscription.subscriber', 'subscription.plan']),
            201
        );
    }

    public function show(Payment $payment)
    {
        return response()->json(
            $payment->load(['subscription.subscriber', 'subscription.plan'])
        );
    }

    public function update(Request $request, Payment $payment)
    {
        $data = $request->validate([
            'subscription_id' => 'sometimes|exists:subscriptions,id',
            'amount'          => 'sometimes|numeric|min:0.01',
            'payment_date'    => 'sometimes|date',
            'payment_type'    => 'sometimes|in:payment,offset,adjustment',
            'remarks'         => 'nullable|string',
        ]);

        $payment->update($data);

        return response()->json(
            $payment->load(['subscription.subscriber', 'subscription.plan'])
        );
    }

    public function destroy(Payment $payment)
    {
        $payment->delete();

        return response()->noContent();
    }
}