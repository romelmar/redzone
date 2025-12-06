<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use App\Services\BillingService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class BillingController extends Controller
{
    /**
     * GET /api/dues
     * List all active subscriptions with a total_due > 0 for a given month.
     */
    public function subscribersWithDues(Request $request, BillingService $billing)
    {
        $monthParam = $request->get('month', now()->startOfMonth()->toDateString());
        $billMonth  = Carbon::parse($monthParam)->startOfMonth();

        // Load everything needed in one go
        $subscriptions = Subscription::with(['subscriber', 'plan', 'addons', 'payments', 'serviceCredits'])
            ->where('active', true)
            ->get();

        $items = $subscriptions->map(function (Subscription $sub) use ($billing, $billMonth) {
            $calc = $billing->computeFor($sub, $billMonth);

            // Break down into the shape expected by your Vue "Subscribers With Dues" table
            $base = (float)$calc['msf'];
            $discount = (float)$calc['discount'];
            $addons = (float)$calc['addons_total'];
            $credits = (float)$calc['outage_credit'];

            return [
                'subscription_id'   => $sub->id,
                'subscriber'        => $sub->subscriber?->name ?? '',
                'subscriber_email'  => $sub->subscriber?->email,
                'plan'              => $sub->plan?->name ?? '',
                'billing_period'    => $billMonth->isoFormat('MMMM YYYY'),

                // base_amount is MSF minus discount (for the month)
                'base_amount'       => (float)($base - $discount),
                'addons_amount'     => $addons,
                'credits_amount'    => $credits,

                'previous_balance'  => (float)$calc['previous_balance'],

                // this already includes previous balance + current bill
                'total_due'         => (float)$calc['total_due'],
            ];
        })
            // Only show those who actually have something to pay
            ->filter(fn($row) => $row['total_due'] > 0)
            ->values();

        return response()->json($items);
    }

    /**
     * GET /api/subscriptions/{subscription}/soa-json
     * JSON breakdown for SOA preview.
     */
    public function soaJson(Request $request, Subscription $subscription, BillingService $billing)
    {
        $monthParam = $request->get('month', now()->startOfMonth()->toDateString());
        $billMonth  = Carbon::parse($monthParam)->startOfMonth();

        $calc = $billing->computeFor($subscription->load('subscriber', 'plan'), $billMonth);

        $base = (float)$calc['msf'];
        $discount = (float)$calc['discount'];
        $addons = (float)$calc['addons_total'];
        $credits = (float)$calc['outage_credit'];

        $start = $billMonth->copy()->startOfMonth();
        $end   = $billMonth->copy()->endOfMonth();

        $creditsDays = $subscription->serviceCredits()
            ->whereBetween('bill_month', [$start, $end])
            ->sum('outage_days');

        $soa = [
            'subscription_id'   => $subscription->id,
            'subscriber'        => $subscription->subscriber?->name ?? '',
            'subscriber_email'  => $subscription->subscriber?->email,
            'plan'              => $subscription->plan?->name ?? '',
            'speed'             => $subscription->plan?->speed ?? null,
            'billing_period'    => $billMonth->isoFormat('MMMM YYYY'),

            'previous_balance'  => (float)$calc['previous_balance'],
            'base_amount'       => (float)($base - $discount),
            'addons_amount'     => $addons,
            'credits_amount'    => $credits,
            'credits_days'      => $creditsDays,
            'discount'          => $discount,
            'current_bill'      => (float)$calc['current_bill'],
            'total_due'         => (float)$calc['total_due'],
        ];

        return response()->json($soa);
    }

    /**
     * GET /api/subscriptions/{subscription}/soa
     * Download SOA PDF.
     */
    public function soaPdf(Request $request, Subscription $subscription, BillingService $billing)
    {
        $monthParam = $request->get('month', now()->startOfMonth()->toDateString());
        $billMonth  = Carbon::parse($monthParam)->startOfMonth();

        $calc = $billing->computeFor($subscription->load('subscriber', 'plan'), $billMonth);

        $base = (float)$calc['msf'];
        $discount = (float)$calc['discount'];
        $addons = (float)$calc['addons_total'];
        $credits = (float)$calc['outage_credit'];
        $start = $billMonth->copy()->startOfMonth();
        $end   = $billMonth->copy()->endOfMonth();

        $creditsDays = $subscription->serviceCredits()
            ->whereBetween('bill_month', [$start, $end])
            ->sum('outage_days');


        $soa = [
            'subscription'      => $subscription,
            'subscriber'        => $subscription->subscriber,
            'plan'              => $subscription->plan,
            'billing_period'    => $billMonth,
            'previous_balance'  => (float)$calc['previous_balance'],
            'base_amount'       => (float)($base - $discount),
            'addons_amount'     => $addons,
            'credits_amount'    => $credits,
            'credits_days'      => $creditsDays,
            'discount'          => $discount,
            'current_bill'      => (float)$calc['current_bill'],
            'total_due'         => (float)$calc['total_due'],
        ];

        // render your SOA blade; adjust view name if needed
        $pdf = Pdf::loadView('pdf.soa', [
            'subscription' => $subscription,
            'soa'          => $soa,
            'month'        => $billMonth,
            'bill_no'      => $this->generateBillNo($subscription, $billMonth), // or just any placeholder
        ])->setPaper('a4');

        $filename = 'SOA-' . $subscription->id . '-' . $billMonth->format('Y-m') . '.pdf';

        return $pdf->download($filename);
    }

    /**
     * POST /api/subscriptions/{subscription}/send-soa
     * Email SOA PDF to subscriber.
     */
    public function sendSoa(Request $request, Subscription $subscription, BillingService $billing)
    {
        if (!$subscription->subscriber || !$subscription->subscriber->email) {
            return response()->json(['message' => 'Subscriber has no email'], 422);
        }

        $monthParam = $request->get('month', now()->startOfMonth()->toDateString());
        $billMonth  = Carbon::parse($monthParam)->startOfMonth();

        $calc = $billing->computeFor($subscription->load('subscriber', 'plan'), $billMonth);

        $base = (float)$calc['msf'];
        $discount = (float)$calc['discount'];
        $addons = (float)$calc['addons_total'];
        $credits = (float)$calc['outage_credit'];
        $start = $billMonth->copy()->startOfMonth();
        $end   = $billMonth->copy()->endOfMonth();

        $creditsDays = $subscription->serviceCredits()
            ->whereBetween('bill_month', [$start, $end])
            ->sum('outage_days');

        $soa = [
            'subscription'      => $subscription,
            'subscriber'        => $subscription->subscriber,
            'plan'              => $subscription->plan,
            'billing_period'    => $billMonth,
            'previous_balance'  => (float)$calc['previous_balance'],
            'base_amount'       => (float)($base - $discount),
            'addons_amount'     => $addons,
            'credits_amount'    => $credits,
            'credits_days'      => $creditsDays,
            'discount'          => $discount,
            'current_bill'      => (float)$calc['current_bill'],
            'total_due'         => (float)$calc['total_due'],
        ];

        $pdf = Pdf::loadView('pdf.soa', ['soa' => $soa])
            ->setPaper('a4')
            ->output();

        Mail::send('emails.soa', ['soa' => $soa], function ($message) use ($subscription, $pdf, $billMonth) {
            $message
                ->to($subscription->subscriber->email)
                ->subject('Statement of Account - ' . $billMonth->isoFormat('MMMM YYYY'))
                ->attachData(
                    $pdf,
                    'SOA-' . $subscription->id . '-' . $billMonth->format('Y-m') . '.pdf',
                    ['mime' => 'application/pdf']
                );
        });

        return response()->json(['message' => 'SOA emailed successfully']);
    }

    private function generateBillNo(Subscription $subscription, \Carbon\Carbon $billMonth)
    {
        return 'SOA-'
            . $billMonth->format('Ym')
            . '-'
            . str_pad($subscription->id, 4, '0', STR_PAD_LEFT);
    }
}
