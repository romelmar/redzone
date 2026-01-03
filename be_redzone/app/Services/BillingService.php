<?php

// app/Services/BillingService.php
namespace App\Services;

use App\Models\Subscription;
use Carbon\Carbon;

class BillingService
{
  /**
   * Returns an array with:
   * - previous_balance
   * - current_charges (msf, discount, addons_total, outage_credit)
   * - total_due
   * - due_date (Carbon)
   */
public function computeFor(Subscription $sub, Carbon $billMonth): array
{
    $billMonth = $billMonth->copy()->startOfMonth();

    // ── Base values ───────────────────────────
    $planPrice = (float) ($sub->plan?->price ?? 0);
    $discount  = (float) ($sub->monthly_discount ?? 0);

    // ── Add-ons (month-based) ─────────────────
    $addonsTotal = (float) $sub->addons()
        ->whereDate('credit_month', $billMonth)
        ->sum('amount');

    // ── Service credits (ALL months ≤ bill month) ──
    $creditDays = (int) $sub->serviceCredits()
        ->whereDate('credit_month', '<=', $billMonth)
        ->sum('outage_days');

    $daysInMonth  = $billMonth->daysInMonth;
    $creditAmount = round(($planPrice / $daysInMonth) * $creditDays, 2);

    // ── Current bill ──────────────────────────
    $currentBill = max(
        0,
        $planPrice - $discount + $addonsTotal
    );

    // ── Charges BEFORE this month ─────────────
    $prevCharges = (float) $this->lifetimeChargesUntil($sub, $billMonth);

    // ── Payments BEFORE this month ────────────
    $prevPayments = (float) $sub->payments()
        ->whereDate('payment_date', '<', $billMonth)
        ->sum('amount');

    // ── Previous balance ──────────────────────
    $previousBalance = round($prevCharges - $prevPayments, 2);

    // ── Payments THIS month ───────────────────
    $paymentsThisMonth = (float) $sub->payments()
        ->whereBetween('payment_date', [
            $billMonth,
            $billMonth->copy()->endOfMonth(),
        ])
        ->sum('amount');

    // ── FINAL TOTAL DUE ───────────────────────
    $totalDue = max(
        0,
        round(
            ($previousBalance + $currentBill)
            - $paymentsThisMonth
            - $creditAmount,
            2
        )
    );

    return [
        'previous_balance' => $previousBalance,
        'msf'              => $planPrice,
        'discount'         => $discount,

        'addons_total'     => $addonsTotal,
        'credit_days'      => $creditDays,
        'outage_credit'    => $creditAmount,

        'payments_total'   => $paymentsThisMonth,

        'current_bill'     => $currentBill,
        'total_due'        => $totalDue,

        'due_date'         => $sub->dueDateForMonth($billMonth),
    ];
}



  // Sum of all monthly “MSF - discount + addons - outageCredit” up to but not including $untilMonth
  protected function lifetimeChargesUntil(Subscription $sub, Carbon $untilMonth): float
  {
    $start = $sub->start_date->copy()->startOfMonth();
    $end = $untilMonth->copy()->subMonth(); // last completed month before current

    if ($start->greaterThan($end)) return 0;

    $sum = 0.0;
    $cursor = $start->copy();
    while ($cursor->lessThanOrEqualTo($end)) {
      $plan = $sub->plan->price;
      $discount = $sub->monthly_discount;
      $addons = $sub->addons()->whereDate('credit_month', $cursor)->sum('amount');
      $outageDays = (int)$sub->serviceCredits()->whereDate('credit_month', $cursor)->sum('outage_days');
      $outageCredit = round(($plan / $cursor->daysInMonth) * $outageDays, 2);

      $sum += max(0, $plan - $discount + $addons - $outageCredit);
      $cursor->addMonth();
    }
    return round($sum, 2);
  }
}
