<?php 

namespace App\Services;

use App\Models\Subscription;
use Carbon\Carbon;

class BillingService
{
    public function computeFor(Subscription $sub, Carbon $billMonth): array
    {
        $planPrice = $sub->plan->price;
        $discount  = $sub->monthly_discount;

        $addonsTotal = $sub->addons()
            ->whereDate('bill_month', $billMonth)
            ->sum('amount');

        $outageDays = (int) $sub->serviceCredits()
            ->whereDate('bill_month', $billMonth)
            ->sum('outage_days');

        $daysInMonth   = $billMonth->daysInMonth;
        $outageCredit  = round(($planPrice / $daysInMonth) * $outageDays, 2);
        $msf           = $planPrice;
        $currentBill   = max(0, $msf - $discount + $addonsTotal - $outageCredit);

        $prevCharges  = $this->lifetimeChargesUntil($sub, $billMonth->copy()->startOfMonth());
        $prevPayments = $sub->payments()
                            ->whereDate('paid_at', '<', $billMonth)
                            ->sum('amount');

        $previousBalance = round($prevCharges - $prevPayments, 2);
        $totalAmountDue  = round($previousBalance + $currentBill, 2);

        return [
            'previous_balance' => $previousBalance,
            'msf'              => $msf,
            'discount'         => $discount,
            'addons_total'     => $addonsTotal,
            'outage_days'      => $outageDays,
            'outage_credit'    => $outageCredit,
            'current_bill'     => $currentBill,
            'total_due'        => $totalAmountDue,
            'due_date'         => $sub->dueDateForMonth($billMonth),
        ];
    }

    protected function lifetimeChargesUntil(Subscription $sub, Carbon $untilMonth): float
    {
        $start = $sub->start_date->copy()->startOfMonth();
        $end   = $untilMonth->copy()->subMonth();

        if ($start->greaterThan($end)) return 0;

        $sum    = 0.0;
        $cursor = $start->copy();

        while ($cursor->lessThanOrEqualTo($end)) {
            $plan        = $sub->plan->price;
            $discount    = $sub->monthly_discount;
            $addons      = $sub->addons()->whereDate('bill_month', $cursor)->sum('amount');
            $outageDays  = (int) $sub->serviceCredits()->whereDate('bill_month', $cursor)->sum('outage_days');
            $outageCredit = round(($plan / $cursor->daysInMonth) * $outageDays, 2);

            $sum += max(0, $plan - $discount + $addons - $outageCredit);
            $cursor->addMonth();
        }

        return round($sum, 2);
    }
}
