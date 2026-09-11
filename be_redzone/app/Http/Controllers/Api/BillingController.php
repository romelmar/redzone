<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use App\Services\BillingService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class BillingController extends Controller
{
    /**
     * GET /api/dues
     * List all ACTIVE subscriptions with outstanding balance
     */
   public function subscribersWithDues(Request $request, BillingService $billing)
{
    $request->validate(['month' => 'sometimes|date', 'per_page' => 'sometimes|integer|min:1|max:100', 'page' => 'sometimes|integer|min:1']);
    $monthParam = $request->get('month', now()->startOfMonth()->toDateString());
    $billMonth = Carbon::parse($monthParam)->startOfMonth();

    $search = trim((string) $request->get('search', ''));
    $perPage = (int) $request->get('per_page', 10);
    $page = (int) $request->get('page', 1);
    $sortBy = $request->get('sort_by', 'subscriber');
    $sortDir = strtolower($request->get('sort_dir', 'asc')) === 'asc' ? 'asc' : 'desc';

    $subscriptions = Subscription::query()
        ->with(['subscriber', 'plan', 'rates', 'events', 'addons', 'payments', 'serviceCredits'])
        ->when(!$request->routeIs('billing-statements.index'), fn ($q) => $q->where('active', true))
        ->when($search !== '', function ($q) use ($search) {
            $q->where(function ($qq) use ($search) {
                if (ctype_digit($search)) {
                    $qq->orWhere('id', (int) $search);
                }

                $qq->orWhereHas('subscriber', function ($s) use ($search) {
                    $s->where('name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%");
                });

                $qq->orWhereHas('plan', function ($p) use ($search) {
                    $p->where('name', 'like', "%{$search}%");
                });
            });
        })
        ->get();

    $rows = $subscriptions->map(function (Subscription $sub) use ($billing, $billMonth) {
        $calc = $billing->computeFor($sub, $billMonth);

        return [
            'subscription_id' => $sub->id,
            'subscriber' => $sub->subscriber?->name ?? '',
            'subscriber_email' => $sub->subscriber?->email,
            'plan' => $sub->plan?->name ?? '',
            'speed' => $sub->plan?->speed ?? null,
            'billing_period' => $billMonth->format('F Y'),
            'previous_balance' => (float) ($calc['previous_balance'] ?? 0),
            'monthly_fee' => (float) ($calc['msf'] ?? 0),
            'discount' => (float) ($calc['discount'] ?? 0),
            'addons_amount' => (float) ($calc['addons_total'] ?? 0),
            'credits_amount' => (float) ($calc['credit_amount'] ?? $calc['outage_credit'] ?? 0),
            'payments_amount' => (float) ($calc['payments_total'] ?? 0),
            'current_bill' => (float) ($calc['current_bill'] ?? 0),
            'total_due' => (float) ($calc['total_due'] ?? 0),
        ];
    });

    if (in_array($sortBy, ['subscriber', 'plan', 'billing_period', 'current_bill', 'total_due', 'previous_balance', 'monthly_fee', 'addons_amount', 'credits_amount', 'payments_amount'], true)) {
        $rows = $sortDir === 'asc' ? $rows->sortBy($sortBy) : $rows->sortByDesc($sortBy);
    }

    $rows = $rows->values();
    $total = $rows->count();
    $paginatedRows = $rows->slice(($page - 1) * $perPage, $perPage)->values();

    return response()->json([
        'data' => $paginatedRows,
        'total' => $total,
        'current_page' => $page,
        'per_page' => $perPage,
        'last_page' => (int) ceil($total / $perPage),
    ]);
}


    /**
     * GET /api/subscriptions/{subscription}/soa-json
     */
    public function soaJson(Request $request, Subscription $subscription, BillingService $billing)
    {
        $request->validate(['month' => 'sometimes|date']);
        $monthParam = $request->get('month', now()->startOfMonth()->toDateString());
        $billMonth = Carbon::parse($monthParam)->startOfMonth();
        $billingPeriod = $subscription->billingPeriodForMonth($billMonth);

        $calc = $billing->computeFor(
            $subscription->load('subscriber', 'plan'),
            $billMonth
        );

        return response()->json([
            'subscription_id' => $subscription->id,
            'subscriber' => $subscription->subscriber?->name ?? '',
            'subscriber_email' => $subscription->subscriber?->email,
            'plan' => $subscription->plan?->name ?? '',
            'billing_period' => $billMonth->format('F Y'),
            'billing_period_start' => $billingPeriod['start']->toDateString(),
            'billing_period_end' => $billingPeriod['end']->toDateString(),
            'bill_no' => $subscription->billingMonthCount($billMonth),

            'previous_balance' => (float) $calc['previous_balance'],
            'base_amount' => (float) ($calc['msf'] - $calc['discount']),
            'addons_amount' => (float) $calc['addons_total'],
            'credits_amount' => (float) $calc['outage_credit'],
            'payments_amount' => (float) $calc['payments_total'],
            'current_bill' => (float) $calc['current_bill'],
            'total_due' => (float) $calc['total_due'],
        ]);
    }

    /**
     * GET /api/subscriptions/{subscription}/soa
     * Download SOA PDF
     */
    public function soaPdf(Request $request, Subscription $subscription, BillingService $billing)
    {
        $request->validate(['month' => 'sometimes|date']);
        $monthParam = $request->get('month', now()->startOfMonth()->toDateString());
        $billMonth = Carbon::parse($monthParam)->startOfMonth();
        $billingPeriod = $subscription->billingPeriodForMonth($billMonth);

        $calc = $billing->computeFor(
            $subscription->load('subscriber', 'plan'),
            $billMonth
        );

        $soa = [
            'subscription' => $subscription,
            'subscriber' => $subscription->subscriber,
            'plan' => $subscription->plan,
            'billing_period' => $billMonth,
            'billing_period_start' => $billingPeriod['start'],
            'billing_period_end' => $billingPeriod['end'],
            'previous_balance' => (float) $calc['previous_balance'],
            'base_amount' => (float) ($calc['msf'] - $calc['discount']),
            'addons_amount' => (float) $calc['addons_total'],
            'credits_amount' => (float) $calc['outage_credit'],
            'payments_amount' => (float) $calc['payments_total'],
            'current_bill' => (float) $calc['current_bill'],
            'total_due' => (float) $calc['total_due'],
            'credits_days' => (int) ($calc['credit_days'] ?? 0),
        ];

        $pdf = Pdf::loadView('pdf.soa', [
            'subscription' => $subscription,
            'soa' => $soa,
            'month' => $billMonth,
            'period_start' => $billingPeriod['start'],
            'period_end' => $billingPeriod['end'],
            'bill_no' => $this->generateBillNo($subscription, $billMonth),
            'printed_at' => now(),
        ])->setPaper('a4');

        return $pdf->download(
            'Billing-statement-' . $subscription->id . '-' . $billMonth->format('Y-m') . '.pdf'
        );
    }

    /**
     * POST /api/subscriptions/{subscription}/send-soa
     */
    public function sendSoa(Request $request, Subscription $subscription, BillingService $billing)
    {
        $request->validate(['month' => 'sometimes|date']);
        if (!$subscription->subscriber || !$subscription->subscriber->email) {
            return response()->json(['message' => 'Subscriber has no email'], 422);
        }

        $monthParam = $request->get('month', now()->startOfMonth()->toDateString());
        $billMonth = Carbon::parse($monthParam)->startOfMonth();
        $billingPeriod = $subscription->billingPeriodForMonth($billMonth);

        $calc = $billing->computeFor(
            $subscription->load('subscriber', 'plan'),
            $billMonth
        );

        $soa = [
            'subscription' => $subscription,
            'subscriber' => $subscription->subscriber,
            'plan' => $subscription->plan,
            'billing_period' => $billMonth,
            'billing_period_start' => $billingPeriod['start'],
            'billing_period_end' => $billingPeriod['end'],
            'previous_balance' => (float) $calc['previous_balance'],
            'base_amount' => (float) ($calc['msf'] - $calc['discount']),
            'addons_amount' => (float) $calc['addons_total'],
            'credits_amount' => (float) $calc['outage_credit'],
            'payments_amount' => (float) $calc['payments_total'],
            'current_bill' => (float) $calc['current_bill'],
            'total_due' => (float) $calc['total_due'],
            'credits_days' => (int) ($calc['credit_days'] ?? 0),
        ];

        $pdf = Pdf::loadView('pdf.soa', [
            'subscription' => $subscription,
            'soa' => $soa,
            'month' => $billMonth,
            'period_start' => $billingPeriod['start'],
            'period_end' => $billingPeriod['end'],
            'bill_no' => $subscription->billingMonthCount($billMonth),
            'printed_at' => now(),
        ])->output();

        Mail::send('emails.billing-statement', ['soa' => $soa], function ($message) use ($subscription, $pdf, $billMonth) {
            $message
                ->to($subscription->subscriber->email)
                ->subject('Billing statement - ' . $billMonth->format('F Y'))
                ->attachData(
                    $pdf,
                    'Billing-statement-' . $subscription->id . '-' . $billMonth->format('Y-m') . '.pdf',
                    ['mime' => 'application/pdf']
                );
        });

        return response()->json(['message' => 'Billing statement emailed successfully']);
    }

    private function generateBillNo(Subscription $subscription, Carbon $billMonth): string
    {
        return (string) $subscription->billingMonthCount($billMonth);
    }
}
