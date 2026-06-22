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

    $planPrice = (float) ($sub->plan?->price ?? 0);
    $discount  = (float) ($sub->monthly_discount ?? 0);

    $addons = $sub->relationLoaded('addons') ? $sub->addons : $sub->addons()->get();
    $payments = $sub->relationLoaded('payments') ? $sub->payments : $sub->payments()->get();
    $serviceCredits = $sub->relationLoaded('serviceCredits') ? $sub->serviceCredits : $sub->serviceCredits()->get();

    $addonsTotal = (float) $addons
        ->filter(fn ($addon) => Carbon::parse($addon->credit_month)->startOfMonth()->equalTo($billMonth))
        ->sum('amount');

    $creditDays = (int) $serviceCredits
        ->filter(fn ($credit) => Carbon::parse($credit->credit_month)->startOfMonth()->lessThanOrEqualTo($billMonth))
        ->sum('outage_days');

    $daysInMonth  = $billMonth->daysInMonth;
    $creditAmount = round(($planPrice / $daysInMonth) * $creditDays, 2);

    $currentBill = max(0, $planPrice - $discount + $addonsTotal);

    $prevCharges = (float) $this->lifetimeChargesUntil($sub, $billMonth, $addons, $serviceCredits, $planPrice, $discount);

    $prevPayments = (float) $payments
        ->filter(fn ($payment) => Carbon::parse($payment->payment_date)->lt($billMonth))
        ->sum('amount');

    $previousBalance = round($prevCharges - $prevPayments, 2);

    $paymentsThisMonth = (float) $payments
        ->filter(function ($payment) use ($billMonth) {
            $date = Carbon::parse($payment->payment_date);
            return $date->between($billMonth, $billMonth->copy()->endOfMonth());
        })
        ->sum('amount');

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
  protected function lifetimeChargesUntil(Subscription $sub, Carbon $untilMonth, $addons, $serviceCredits, float $planPrice, float $discount): float
  {
    $start = $sub->start_date->copy()->startOfMonth();
    $end = $untilMonth->copy()->subMonth();

    if ($start->greaterThan($end)) return 0;

    $addonTotals = $addons
        ->groupBy(fn ($addon) => Carbon::parse($addon->credit_month)->startOfMonth()->toDateString())
        ->map(fn ($group) => $group->sum('amount'));

    $creditDaysTotals = $serviceCredits
        ->groupBy(fn ($credit) => Carbon::parse($credit->credit_month)->startOfMonth()->toDateString())
        ->map(fn ($group) => $group->sum('outage_days'));

    $sum = 0.0;
    $cursor = $start->copy();

    while ($cursor->lessThanOrEqualTo($end)) {
      $monthKey = $cursor->toDateString();
      $monthAddons = (float) ($addonTotals[$monthKey] ?? 0);
      $monthOutageDays = (int) ($creditDaysTotals[$monthKey] ?? 0);
      $outageCredit = round(($planPrice / $cursor->daysInMonth) * $monthOutageDays, 2);

      $sum += max(0, $planPrice - $discount + $monthAddons - $outageCredit);
      $cursor->addMonth();
    }

    return round($sum, 2);
  }
}
