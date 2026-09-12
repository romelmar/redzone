<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use Illuminate\Http\Request;

class PaymentHistoryController extends Controller
{
    public function index(Request $request, Subscription $subscription)
    {
        $input = $request->validate([
            'page' => 'sometimes|integer|min:1',
            'per_page' => 'sometimes|integer|min:1|max:100',
            'sort_dir' => 'sometimes|in:asc,desc',
        ]);
        $query = $subscription->payments()->withTrashed();
        $totalReceived = (clone $query)->whereNull('deleted_at')->where(function ($q) {
            $q->where('payment_type', 'payment')->orWhereNull('payment_type')->orWhere('payment_type', '');
        })->sum('amount');
        $direction = $input['sort_dir'] ?? 'desc';
        $payments = $query->orderBy('payment_date', $direction)->orderBy('id', $direction)
            ->paginate($input['per_page'] ?? 20);
        return response()->json([
            'subscription_id' => $subscription->id,
            'subscriber' => $subscription->subscriber?->name,
            'total_received' => round((float) $totalReceived, 2),
            'data' => $payments->getCollection()->map(fn ($payment) => [
                'id' => $payment->id, 'payment_date' => $payment->payment_date?->toDateString(),
                'amount' => (float) $payment->amount, 'payment_type' => $payment->payment_type ?: 'payment',
                'payment_method' => $payment->payment_method, 'collector_name' => $payment->collector_name,
                'remarks' => $payment->remarks, 'status' => $payment->trashed() ? 'voided' : 'recorded',
            ]),
            'total' => $payments->total(), 'current_page' => $payments->currentPage(),
            'last_page' => $payments->lastPage(),
        ]);
    }
}
