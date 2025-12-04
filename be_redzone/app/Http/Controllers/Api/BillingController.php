<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Subscription;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Mail;

class BillingController extends Controller
{
    /**
     * 1. List all subscribers with unpaid dues
     */
    public function subscribersWithDues()
    {
        $subscriptions = Subscription::with(['subscriber', 'plan', 'addons', 'serviceCredits', 'payments'])
            ->where('active', 1)
            ->get();

        $data = $subscriptions->map(fn ($s) => $this->computeBilling($s));

        return response()->json($data);
    }


    /**
     * 2. SOA JSON (used by SOA generator preview)
     */
    public function soaJson(Request $request, Subscription $subscription)
    {
        $month = $request->month ?? now()->format('Y-m');

        return response()->json(
            $this->computeBilling($subscription, $month)
        );
    }


    /**
     * 3. Download SOA PDF
     */
    public function soaPdf(Request $request, Subscription $subscription)
    {
        $month = $request->month ?? now()->format('Y-m');

        $soa = $this->computeBilling($subscription, $month);

        $pdf = Pdf::loadView('pdf.soa', compact('soa'));

        return $pdf->download("SOA-{$subscription->id}.pdf");
    }


    /**
     * 4. Email SOA
     */
    public function sendSoa(Request $request, Subscription $subscription)
    {
        $month = $request->month ?? now()->format('Y-m');
        $soa = $this->computeBilling($subscription, $month);

        $pdf = Pdf::loadView('pdf.soa', compact('soa'))->output();

        Mail::send('emails.soa', compact('soa'), function ($message) use ($subscription, $pdf) {
            $message->to($subscription->subscriber->email)
                ->subject('Statement of Account')
                ->attachData($pdf, 'SOA.pdf');
        });

        return response()->json(['message' => 'SOA sent successfully']);
    }


    /**
     * ===============================
     *            CORE ENGINE
     *  ===============================
     *  computeBilling()
     *  - computes the entire SOA logic
     *  - used by dues list, preview, PDF, email
     */
    private function computeBilling(Subscription $s, string $month = null)
    {
        $month = $month ?? now()->format('Y-m');

        $billingPeriod = \Carbon\Carbon::parse($month . '-01');

        // 1. Base Plan Price
        $base = (float) $s->plan->price;

        // 2. Monthly Discount
        $discount = (float) ($s->monthly_discount ?? 0);

        // 3. Add-ons for billing period
        $addons = (float) $s->addons()
            ->where('bill_month', 'like', "$month%")
            ->sum('amount');

        // 4. Outage credits for billing period
        //    Each outage day reduces amount by 10 pesos (customizable)
        $credits = (float) $s->serviceCredits()
            ->where('bill_month', 'like', "$month%")
            ->sum('outage_days') * 10;

        // 5. Payments for billing period
        $payments = (float) $s->payments()
            ->where('paid_at', 'like', "$month%")
            ->sum('amount');

        // 6. Compute total
        $subTotal = ($base - $discount) + $addons - $credits;
        $totalDue = max(0, $subTotal - $payments); // never negative

        return [
            'subscription_id' => $s->id,
            'subscriber' => $s->subscriber->name,
            'subscriber_email' => $s->subscriber->email,
            'plan' => $s->plan->name,
            'speed' => $s->plan->speed,

            'billing_period' => $billingPeriod->format('F Y'),

            'base_amount' => (float) ($base - $discount),
            'addons_amount' => (float) $addons,
            'credits_amount' => (float) $credits,
            'payments_amount' => (float) $payments,

            'total_due' => (float) $totalDue,

            'details' => [
                'discount' => (float) $discount,
                'raw_base' => (float) $base,
            ],
        ];
    }
}
