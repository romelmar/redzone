<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\{CollectorRemittance, Payment, PaymentAudit, Subscriber, Subscription};
use App\Services\BillingService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OperationsController extends Controller
{
    private function date(Request $request): string
    {
        $request->validate(['date' => 'sometimes|date_format:Y-m-d']);
        return $request->get('date', now('Asia/Manila')->toDateString());
    }

    public function dashboard(Request $request, BillingService $billing)
    {
        $date = Carbon::parse($this->date($request))->startOfDay();
        $overdue = [];
        $upcoming = [];
        $outstanding = 0;
        Subscription::with(['subscriber', 'plan', 'rates', 'addons', 'serviceCredits', 'payments'])
            ->whereDate('start_date', '<=', $date->copy()->addDays(7))
            ->chunkById(200, function ($subscriptions) use ($date, $billing, &$overdue, &$upcoming, &$outstanding) {
                foreach ($subscriptions as $sub) {
                    $sub->setRelation('payments', $sub->payments->filter(fn ($p) => $p->payment_date->lte($date)));
                    $calc = $billing->computeFor($sub, $date->copy()->startOfMonth());
                    if ($sub->start_date->gt($date)) {
                        $calc['total_due'] = 0;
                        $calc['previous_balance'] = 0;
                    }
                    $outstanding += (int) round($calc['total_due'] * 100);
                    $dueDate = $sub->dueDateForMonth($date);
                    $late = $dueDate->lt($date) ? $calc['total_due']
                        : max(0, $calc['previous_balance'] - $calc['payments_total'] - $calc['outage_credit']);
                    $row = ['subscription_id' => $sub->id, 'subscriber' => $sub->subscriber?->name,
                        'active' => (bool) $sub->active, 'due_date' => $dueDate->toDateString()];
                    if ($late > 0) {
                        $overdue[] = $row + ['amount' => round($late, 2)];
                    }
                    if ($dueDate->lt($date)) {
                        $dueDate = $sub->dueDateForMonth($date->copy()->startOfMonth()->addMonth());
                    }
                    if ($sub->active && $dueDate->betweenIncluded($date, $date->copy()->addDays(7))
                        && $sub->start_date->lte($dueDate) && (!$sub->end_date || $sub->end_date->gte($dueDate))) {
                        $upcoming[] = array_replace($row, ['due_date' => $dueDate->toDateString()]);
                    }
                }
            });
        $collections = Payment::whereDate('payment_date', $date)->where('payment_type', 'payment');
        return response()->json([
            'date' => $date->toDateString(),
            'subscribers' => Subscriber::count(),
            'active_subscriptions' => Subscription::where('active', true)->count(),
            'inactive_subscriptions' => Subscription::where('active', false)->count(),
            'collections_today' => round((float) (clone $collections)->sum('amount'), 2),
            'payments_today' => (clone $collections)->count(),
            'outstanding_balance' => $outstanding / 100,
            'overdue_count' => count($overdue),
            'overdue_total' => round(array_sum(array_column($overdue, 'amount')), 2),
            'overdue' => collect($overdue)->sortByDesc('amount')->take(15)->values(),
            'upcoming_count' => count($upcoming),
            'upcoming' => collect($upcoming)->sortBy('due_date')->take(15)->values(),
        ]);
    }

    public function reconciliation(Request $request)
    {
        $date = $this->date($request);
        $rows = [];
        $payments = Payment::whereDate('payment_date', $date)->where('payment_type', 'payment')
            ->select('collector_name', 'payment_method')->selectRaw('SUM(amount) AS total, COUNT(*) AS payment_count')
            ->groupBy('collector_name', 'payment_method')->get();
        $remittances = CollectorRemittance::where('collection_date', $date)->orderByDesc('id')->get();
        foreach ($payments as $payment) {
            $key = json_encode([$payment->collector_name === null ? null : mb_strtolower(trim($payment->collector_name)), $payment->payment_method]);
            $rows[$key] ??= ['collector_name' => $payment->collector_name, 'payment_method' => $payment->payment_method,
                'collected_cents' => 0, 'remitted_cents' => 0, 'payment_count' => 0];
            $rows[$key]['collected_cents'] += (int) round($payment->total * 100);
            $rows[$key]['payment_count'] += (int) $payment->payment_count;
        }
        foreach ($remittances->whereNull('voided_at') as $remittance) {
            $key = json_encode([mb_strtolower(trim($remittance->collector_name)), $remittance->payment_method]);
            $rows[$key] ??= ['collector_name' => $remittance->collector_name, 'payment_method' => $remittance->payment_method,
                'collected_cents' => 0, 'remitted_cents' => 0, 'payment_count' => 0];
            $rows[$key]['remitted_cents'] += (int) round($remittance->amount * 100);
        }
        $result = collect($rows)->map(fn ($row) => [
            'collector_name' => $row['collector_name'], 'payment_method' => $row['payment_method'],
            'payment_count' => $row['payment_count'], 'collected' => $row['collected_cents'] / 100,
            'remitted' => $row['remitted_cents'] / 100,
            'unremitted' => ($row['collected_cents'] - $row['remitted_cents']) / 100,
        ])->sortBy('collector_name')->values();
        return response()->json(['date' => $date, 'rows' => $result, 'remittances' => $remittances]);
    }

    public function remit(Request $request)
    {
        $data = $request->validate([
            'collection_date' => 'required|date_format:Y-m-d|before_or_equal:'.now('Asia/Manila')->toDateString(),
            'collector_name' => 'required|string|max:255', 'payment_method' => 'required|in:cash,gcash,bank',
            'amount' => 'required|numeric|min:0.01|max:9999999999.99|decimal:0,2',
            'reference' => 'required|string|max:255', 'notes' => 'nullable|string|max:2000',
            'request_key' => 'required|uuid',
        ]);
        $key = $data['request_key'];
        unset($data['request_key']);
        $data['amount'] = number_format((float) $data['amount'], 2, '.', '');
        $data['notes'] = $data['notes'] ?? null;
        ksort($data);
        $hash = hash('sha256', json_encode($data));
        $remittance = CollectorRemittance::query()->createOrFirst(['request_key' => $key], $data + [
            'request_hash' => $hash, 'recorded_by' => $request->user()->id,
            'recorded_by_name' => $request->user()->name, 'created_at' => now(),
        ]);
        abort_if($remittance->request_hash !== $hash || $remittance->voided_at, 409, 'This request was already used. Reopen the form to record a new remittance.');
        return response()->json($remittance, 201);
    }

    public function voidRemittance(Request $request, CollectorRemittance $remittance)
    {
        $data = $request->validate(['reason' => 'required|string|min:3|max:1000']);
        DB::transaction(function () use ($request, $remittance, $data) {
            $locked = CollectorRemittance::whereKey($remittance->id)->lockForUpdate()->firstOrFail();
            abort_if($locked->voided_at, 409, 'This remittance is already voided.');
            $locked->update(['voided_at' => now(), 'void_reason' => $data['reason'],
                'voided_by' => $request->user()->id, 'voided_by_name' => $request->user()->name]);
        });
        return response()->noContent();
    }

    public function audits(Request $request)
    {
        $data = $request->validate(['payment_id' => 'nullable|integer|min:1', 'page' => 'sometimes|integer|min:1']);
        return PaymentAudit::when($data['payment_id'] ?? null, fn ($q, $id) => $q->where('payment_id', $id))
            ->orderByDesc('id')->paginate(25);
    }
}
