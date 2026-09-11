<?php

// app/Services/BillingService.php
namespace App\Services;

use App\Models\Subscription;
use Carbon\Carbon;

class BillingService
{
    private function hasRecurringCharge(Subscription $sub, Carbon $month): bool
    {
        $monthEnd = $month->copy()->endOfMonth();
        if ($sub->start_date->gt($monthEnd) || ($sub->end_date && $sub->end_date->lt($month))) return false;

        $events = $sub->events->whereIn('type', ['activate', 'deactivate'])->sortBy('event_at')->values();
        if (!$sub->active) {
            $cutoff = $sub->deactivated_at ?? $sub->end_date;
            if ($cutoff) {
                $events = $events->push(new \App\Models\SubscriptionEvent(['type' => 'deactivate', 'event_at' => $cutoff]))->sortBy('event_at');
            } elseif (!$events->contains('type', 'deactivate')) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'deactivated_at' => "Subscription #{$sub->id} is inactive but has no disconnection date. Record the actual date before calculating billing.",
                ]);
            }
        }
        $activeFrom = $sub->start_date;
        foreach ($events as $event) {
            if ($event->type === 'deactivate') {
                if ($activeFrom && $activeFrom->lte($monthEnd) && $event->event_at->gte($month)) return true;
                $activeFrom = null;
            } elseif ($activeFrom === null) {
                $activeFrom = $event->event_at;
            }
        }
        return $activeFrom !== null && $activeFrom->lte($monthEnd);
    }
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

    $rate = $sub->rateForMonth($billMonth);
    $planPrice = $rate['price'];
    $discount = $rate['discount'];
    if (!$this->hasRecurringCharge($sub, $billMonth)) {
        $planPrice = 0;
        $discount = 0;
    }

    $addons = $sub->relationLoaded('addons') ? $sub->addons : $sub->addons()->get();
    $payments = $sub->relationLoaded('payments') ? $sub->payments : $sub->payments()->get();
    $serviceCredits = $sub->relationLoaded('serviceCredits') ? $sub->serviceCredits : $sub->serviceCredits()->get();

    $addonsTotal = (float) $addons
        ->filter(fn ($addon) => Carbon::parse($addon->credit_month)->startOfMonth()->equalTo($billMonth))
        ->sum('amount');

    $creditDays = (int) $serviceCredits
        ->filter(fn ($credit) => Carbon::parse($credit->credit_month)->startOfMonth()->equalTo($billMonth))
        ->sum('outage_days');

    $creditAmount = round((float) $serviceCredits
        ->filter(fn ($credit) => Carbon::parse($credit->credit_month)->startOfMonth()->equalTo($billMonth))
        ->sum('amount'), 2);

    $currentBill = max(0, $planPrice - $discount + $addonsTotal);

    $prevCharges = (float) $this->lifetimeChargesUntil($sub, $billMonth, $addons, $serviceCredits);

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
  protected function lifetimeChargesUntil(Subscription $sub, Carbon $untilMonth, $addons, $serviceCredits): float
  {
    $start = $sub->start_date->copy()->startOfMonth();
    $end = $untilMonth->copy()->subMonth();

    if ($start->greaterThan($end)) return 0;

    $addonTotals = $addons
        ->groupBy(fn ($addon) => Carbon::parse($addon->credit_month)->startOfMonth()->toDateString())
        ->map(fn ($group) => $group->sum('amount'));

    $creditTotals = $serviceCredits
        ->groupBy(fn ($credit) => Carbon::parse($credit->credit_month)->startOfMonth()->toDateString())
        ->map(fn ($group) => $group->sum('amount'));

    $sum = 0.0;
    $cursor = $start->copy();

    while ($cursor->lessThanOrEqualTo($end)) {
      $monthKey = $cursor->toDateString();
      $monthAddons = (float) ($addonTotals[$monthKey] ?? 0);
      $outageCredit = (float) ($creditTotals[$monthKey] ?? 0);
      $rate = $sub->rateForMonth($cursor);
      $planPrice = $rate['price'];
      $discount = $rate['discount'];
      if (!$this->hasRecurringCharge($sub, $cursor)) {
          $planPrice = 0;
          $discount = 0;
      }
      $sum += max(0, $planPrice - $discount + $monthAddons) - $outageCredit;
      $cursor->addMonth();
    }

    return round($sum, 2);
  }
}
