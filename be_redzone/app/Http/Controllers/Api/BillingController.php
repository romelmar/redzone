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
     * List all ACTIVE subscriptions with outstanding balance
     */
    public function subscribersWithDues(Request $request, BillingService $billing)
    {
        $monthParam = $request->get('month', now()->startOfMonth()->toDateString());
        $billMonth  = Carbon::parse($monthParam)->startOfMonth();

        $subscriptions = Subscription::with([
                'subscriber',
                'plan',
                'addons',
                'payments',
                'serviceCredits',
            ])
            ->where('active', true)
            ->get();

        $items = $subscriptions->map(function (Subscription $sub) use ($billing, $billMonth) {

            $calc = $billing->computeFor($sub, $billMonth);

            return [
                'subscription_id'   => $sub->id,

                'subscriber'        => $sub->subscriber?->name ?? '',
                'subscriber_email'  => $sub->subscriber?->email,

                'plan'              => $sub->plan?->name ?? '',
                'speed'             => $sub->plan?->speed ?? null,

                'billing_period'    => $billMonth->format('F Y'),

                'previous_balance'  => (float) $calc['previous_balance'],

                'monthly_fee'       => (float) $calc['msf'],
                'discount'          => (float) $calc['discount'],
                'addons_amount'     => (float) $calc['addons_total'],
                'credits_amount'    => (float) $calc['outage_credit'],
                'payments_amount'   => (float) $calc['payments_total'],

                'current_bill'      => (float) $calc['current_bill'],
                'total_due'         => (float) $calc['total_due'],
            ];
        })
        ->filter(fn ($row) => $row['total_due'] > 0)
        ->values();

        return response()->json($items);
    }

    /**
     * GET /api/subscriptions/{subscription}/soa-json
     */
    public function soaJson(Request $request, Subscription $subscription, BillingService $billing)
    {
        $monthParam = $request->get('month', now()->startOfMonth()->toDateString());
        $billMonth  = Carbon::parse($monthParam)->startOfMonth();

        $calc = $billing->computeFor(
            $subscription->load('subscriber', 'plan'),
            $billMonth
        );

        return response()->json([
            'subscription_id'   => $subscription->id,
            'subscriber'        => $subscription->subscriber?->name ?? '',
            'subscriber_email'  => $subscription->subscriber?->email,
            'plan'              => $subscription->plan?->name ?? '',
            'billing_period'    => $billMonth->format('F Y'),

            'previous_balance'  => (float) $calc['previous_balance'],
            'base_amount'       => (float) ($calc['msf'] - $calc['discount']),
            'addons_amount'     => (float) $calc['addons_total'],
            'credits_amount'    => (float) $calc['outage_credit'],
            'payments_amount'   => (float) $calc['payments_total'],
            'current_bill'      => (float) $calc['current_bill'],
            'total_due'         => (float) $calc['total_due'],
        ]);
    }

    /**
     * GET /api/subscriptions/{subscription}/soa
     * Download SOA PDF
     */
    public function soaPdf(Request $request, Subscription $subscription, BillingService $billing)
    {
        $monthParam = $request->get('month', now()->startOfMonth()->toDateString());
        $billMonth  = Carbon::parse($monthParam)->startOfMonth();

        $calc = $billing->computeFor(
            $subscription->load('subscriber', 'plan'),
            $billMonth
        );

        $soa = [
            'subscription'      => $subscription,
            'subscriber'        => $subscription->subscriber,
            'plan'              => $subscription->plan,
            'billing_period'    => $billMonth,
            'previous_balance'  => (float) $calc['previous_balance'],
            'base_amount'       => (float) ($calc['msf'] - $calc['discount']),
            'addons_amount'     => (float) $calc['addons_total'],
            'credits_amount'    => (float) $calc['outage_credit'],
            'payments_amount'   => (float) $calc['payments_total'],
            'current_bill'      => (float) $calc['current_bill'],
            'total_due'         => (float) $calc['total_due'],
            'credits_days'      => (int) ($calc['credits_days'] ?? 0),
        ];

        $pdf = Pdf::loadView('pdf.soa', [
            'subscription' => $subscription,
            'soa'          => $soa,
            'month'        => $billMonth,
            'bill_no'      => $this->generateBillNo($subscription, $billMonth),
        ])->setPaper('a4');

        return $pdf->download(
            'SOA-' . $subscription->id . '-' . $billMonth->format('Y-m') . '.pdf'
        );
    }

    /**
     * POST /api/subscriptions/{subscription}/send-soa
     */
    public function sendSoa(Request $request, Subscription $subscription, BillingService $billing)
    {
        if (!$subscription->subscriber || !$subscription->subscriber->email) {
            return response()->json(['message' => 'Subscriber has no email'], 422);
        }

        $monthParam = $request->get('month', now()->startOfMonth()->toDateString());
        $billMonth  = Carbon::parse($monthParam)->startOfMonth();

        $calc = $billing->computeFor(
            $subscription->load('subscriber', 'plan'),
            $billMonth
        );

        $soa = [
            'subscription'      => $subscription,
            'subscriber'        => $subscription->subscriber,
            'plan'              => $subscription->plan,
            'billing_period'    => $billMonth,
            'previous_balance'  => (float) $calc['previous_balance'],
            'base_amount'       => (float) ($calc['msf'] - $calc['discount']),
            'addons_amount'     => (float) $calc['addons_total'],
            'credits_amount'    => (float) $calc['outage_credit'],
            'payments_amount'   => (float) $calc['payments_total'],
            'current_bill'      => (float) $calc['current_bill'],
            'total_due'         => (float) $calc['total_due'],
            'credits_days'      => (int) ($calc['credits_days'] ?? 0),
        ];

        $pdf = Pdf::loadView('pdf.soa', [
            'subscription' => $subscription,
            'soa'          => $soa,
        ])->output();

        Mail::send('emails.soa', ['soa' => $soa], function ($message) use ($subscription, $pdf, $billMonth) {
            $message
                ->to($subscription->subscriber->email)
                ->subject('Statement of Account - ' . $billMonth->format('F Y'))
                ->attachData(
                    $pdf,
                    'SOA-' . $subscription->id . '-' . $billMonth->format('Y-m') . '.pdf',
                    ['mime' => 'application/pdf']
                );
        });

        return response()->json(['message' => 'SOA emailed successfully']);
    }

    private function generateBillNo(Subscription $subscription, Carbon $billMonth): string
    {
        return 'SOA-' . $billMonth->format('Ym') . '-' . str_pad($subscription->id, 4, '0', STR_PAD_LEFT);
    }
}
