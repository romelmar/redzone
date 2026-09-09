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

        $sortBy = $request->get('sort_by', 'payment_date');
        $sortDir = strtolower($request->get('sort_dir', 'desc')) === 'asc' ? 'asc' : 'desc';

        if ($sortBy === 'subscriber_name') {
            $query->leftJoin('subscriptions', 'payments.subscription_id', '=', 'subscriptions.id')
                ->leftJoin('subscribers', 'subscriptions.subscriber_id', '=', 'subscribers.id')
                ->orderBy('subscribers.name', $sortDir)
                ->select('payments.*');
        } elseif ($sortBy === 'plan_name') {
            $query->leftJoin('subscriptions', 'payments.subscription_id', '=', 'subscriptions.id')
                ->leftJoin('plans', 'subscriptions.plan_id', '=', 'plans.id')
                ->orderBy('plans.name', $sortDir)
                ->select('payments.*');
        } elseif (in_array($sortBy, ['payment_date', 'amount', 'payment_type'], true)) {
            $query->orderBy($sortBy, $sortDir);
        } else {
            $query->orderBy('payment_date', $sortDir);
        }

        return response()->json(
            $query->paginate($perPage)
        );
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'collector_name' => 'nullable|string|max:255',
            'payment_method' => 'sometimes|in:cash,gcash,bank,unspecified',
            'request_key'     => 'nullable|uuid',
            'subscription_id' => 'required|exists:subscriptions,id',
            'amount'          => 'required|numeric|min:0.01|max:99999999.99|decimal:0,2',
            'payment_date'    => 'required|date',
            'payment_type'    => 'required|in:payment,offset,adjustment',
            'remarks'         => 'nullable|string',
        ]);

        $key = $data['request_key'] ?? null;
        unset($data['request_key']);
        $data['subscription_id'] = (int) $data['subscription_id'];
        $data['amount'] = number_format((float) $data['amount'], 2, '.', '');
        $data['payment_date'] = \Carbon\Carbon::parse($data['payment_date'])->toDateString();
        $data['remarks'] = $data['remarks'] ?? null;
        $data['collector_name'] = $data['collector_name'] ?? null;
        $data['payment_method'] = $data['payment_method'] ?? 'unspecified';
        $hash = hash('sha256', json_encode($data));
        $payment = \Illuminate\Support\Facades\DB::transaction(fn () => $key
            ? Payment::withTrashed()->createOrFirst(['request_key' => $key], $data + ['request_hash' => $hash])
            : Payment::create($data));
        abort_if($payment->trashed(), 409, 'This payment was voided. Open a new payment form to record another payment.');
        abort_if($key && $payment->request_hash !== $hash, 409, 'This payment request was already used with different details. Reopen the form to record another payment.');

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
            'reason' => 'required|string|min:3|max:1000',
            'collector_name' => 'nullable|string|max:255',
            'payment_method' => 'sometimes|in:cash,gcash,bank,unspecified',
            'subscription_id' => 'sometimes|exists:subscriptions,id',
            'amount'          => 'sometimes|numeric|min:0.01|max:99999999.99|decimal:0,2',
            'payment_date'    => 'sometimes|date',
            'payment_type'    => 'sometimes|in:payment,offset,adjustment',
            'remarks'         => 'nullable|string',
        ]);

        $payment = $payment->getConnection()->transaction(function () use ($payment, $data) {
            $locked = Payment::whereKey($payment->id)->lockForUpdate()->firstOrFail();
            $locked->auditReason = $data['reason'];
            unset($data['reason']);
            $locked->update($data);
            return $locked;
        });

        return response()->json(
            $payment->load(['subscription.subscriber', 'subscription.plan'])
        );
    }

    public function destroy(Request $request, Payment $payment)
    {
        $data = $request->validate(['reason' => 'required|string|min:3|max:1000']);
        $payment->getConnection()->transaction(function () use ($payment, $data) {
            $locked = Payment::whereKey($payment->id)->lockForUpdate()->firstOrFail();
            $locked->auditReason = $data['reason'];
            $locked->delete();
        });

        return response()->noContent();
    }
}
