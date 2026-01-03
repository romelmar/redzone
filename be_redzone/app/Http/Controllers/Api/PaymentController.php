<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function index(Request $request)
    {
        $query = Payment::query()->with('subscription.subscriber');

        if ($request->filled('subscription_id')) {
            $query->where('subscription_id', $request->subscription_id);
        }

        return $query->latest('payment_date')->paginate(20);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'subscription_id' => 'required|exists:subscriptions,id',
            'amount'          => 'required|numeric|min:0.01',
            'payment_date'         => 'required|date',
            'remarks'           => 'nullable|string',
        ]);

        return Payment::create($data);
    }

    public function show(Payment $payment)
    {
        return $payment;
    }

    public function update(Request $request, Payment $payment)
    {
        $data = $request->validate([
            'amount'    => 'sometimes|numeric|min:0.01',
            'payment_date'   => 'sometimes|date',
            'remarks'     => 'nullable|string',
        ]);

        $payment->update($data);
        return $payment;
    }

    public function destroy(Payment $payment)
    {
        $payment->delete();
        return response()->noContent();
    }
}
