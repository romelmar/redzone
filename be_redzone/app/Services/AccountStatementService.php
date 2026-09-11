<?php
namespace App\Services;

use App\Models\Subscription;
use Carbon\Carbon;

class AccountStatementService
{
    public function build(Subscription $subscription, Carbon $from, Carbon $to): array
    {
        $subscription->loadMissing('subscriber', 'plan', 'rates', 'events', 'addons', 'payments', 'serviceCredits');
        $billing = app(BillingService::class);
        $firstMonth = $from->copy()->startOfMonth();
        $opening = (int) round($billing->computeFor($subscription, $firstMonth)['previous_balance'] * 100);
        $entries = [];
        $add = function ($date, $reference, $description, $debit, $credit) use (&$entries) {
            $entries[] = ['date' => $date, 'reference' => $reference, 'description' => $description,
                'debit_cents' => (int) round($debit * 100), 'credit_cents' => (int) round($credit * 100)];
        };
        for ($month = $firstMonth->copy(); $month->lte($to); $month->addMonth()) {
            $calc = $billing->computeFor($subscription, $month);
            if ($calc['current_bill'] != 0) {
                $add($month->toDateString(), 'BILL-'.$subscription->id.'-'.$month->format('Y-m'),
                    $month->format('F Y').' charges (fee '.number_format($calc['msf'], 2).', discount '.number_format($calc['discount'], 2).', add-ons '.number_format($calc['addons_total'], 2).')', $calc['current_bill'], 0);
            }
        }
        foreach ($subscription->serviceCredits as $credit) {
            $date = Carbon::parse($credit->credit_month)->startOfMonth();
            if ($date->betweenIncluded($firstMonth, $to)) $add($date->toDateString(), 'CREDIT-'.$credit->id, $credit->reason ?: 'Service credit', 0, $credit->amount);
        }
        foreach ($subscription->payments as $payment) {
            if ($payment->payment_date->betweenIncluded($firstMonth, $to)) $add($payment->payment_date->toDateString(), 'PAY-'.$payment->id,
                ucfirst($payment->payment_type ?: 'Payment').($payment->payment_method ? ' / '.$payment->payment_method : ''), 0, $payment->amount);
        }
        usort($entries, fn ($a, $b) => [$a['date'], $a['reference']] <=> [$b['date'], $b['reference']]);
        $rows = []; $debits = 0; $credits = 0; $balance = $opening;
        foreach ($entries as $entry) {
            $balance += $entry['debit_cents'] - $entry['credit_cents'];
            if ($entry['date'] < $from->toDateString()) { $opening = $balance; continue; }
            $debits += $entry['debit_cents']; $credits += $entry['credit_cents'];
            $rows[] = ['date' => $entry['date'], 'reference' => $entry['reference'], 'description' => $entry['description'],
                'debit' => $entry['debit_cents'] / 100, 'credit' => $entry['credit_cents'] / 100, 'balance' => $balance / 100];
        }
        return ['subscriber' => $subscription->subscriber?->name, 'subscriber_id' => $subscription->subscriber_id,
            'subscription_id' => $subscription->id, 'email' => $subscription->subscriber?->email, 'plan' => $subscription->plan?->name,
            'from' => $from->toDateString(), 'to' => $to->toDateString(), 'opening_balance' => $opening / 100,
            'total_charges' => $debits / 100, 'total_credits' => $credits / 100, 'closing_balance' => $balance / 100, 'entries' => $rows];
    }
}
