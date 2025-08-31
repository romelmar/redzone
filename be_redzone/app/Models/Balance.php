<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Payment;

class Balance extends Model
{
    protected $fillable = ['subscriber_id', 'billing_month', 'balance_amount'];

    public function subscriber()
    {
        return $this->belongsTo(Subscriber::class);
    }

    // Method to compute the balance dynamically
    public function computeBalance()
    {
        // Retrieve payments made by the subscriber on the billing month
        $payments = Payment::where('subscriber_id', $this->subscriber_id)
            ->where('billing_month', $this->billing_month)
            ->sum('amount');

        // Retrieve the subscription billing amount
        $subscriptionBillingAmount = Subscription::where('subscriber_id', $this->subscriber_id)
            ->whereMonth('next_billing_date', $this->billing_month)
            ->sum('plan_price'); // Assuming there's a column 'plan_price' in the subscriptions table

        // Compute the balance by subtracting payments from the subscription billing amount
        $balance = $subscriptionBillingAmount - $payments;

        // Update the balance_amount column in the balances table
        $this->update(['balance_amount' => $balance]);

        return $balance;
    }
}
