<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\Subscription;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Carbon\Carbon;

class PaymentController extends Controller
{
    
    public function store(Request $request, Subscription $subscription)
    {
        // Validate the request
        $request->validate([
            'amount' => 'required|numeric|min:0',
            'payment_date' => 'required|date'
        ]);

        // Create a new payment
        $payment = new Payment();
        $payment->subscription_id = $subscription->id;
        $payment->amount = $request->amount;
        $payment->payment_date = Carbon::parse($request->payment_date)->timezone('Asia/Manila')->startOfDay();
        $payment->save();

        // Recalculate the balance
        $this->recalculateBalance($subscription);

        return redirect()->route('subscribers.show', $subscription->subscriber_id)->with('success', 'Payment made successfully');
    }

    public function history($subscriptionId)
    {
        $subscription = Subscription::with('subscriber', 'plan', 'payments')->findOrFail($subscriptionId);
        return Inertia::render('Payments/History', [
            'subscription' => $subscription,
            'subscriberName' => $subscription->subscriber->name,
            'planName' => $subscription->plan->name,
            'payments' => $subscription->payments,
        ]);
    }

    private function recalculateBalance(Subscription $subscription)
    {
        // Get the start date and current date
        $startDate = Carbon::parse($subscription->start_date)->timezone('Asia/Manila')->startOfDay();
        $currentDate = Carbon::now()->timezone('Asia/Manila')->startOfDay();

        // Initialize the total payable amount
        $totalPayable = 0;
        $billingDate = $startDate->copy();

        // Iterate month by month from the start date to the current date
        while ($billingDate <= $currentDate) {
            $totalPayable += $subscription->plan->price;
            $billingDate->addMonth();
        }

        // Calculate total payments made for this subscription
        $totalPayments = $subscription->payments->sum('amount');

        // Compute the balance
        $balance = $totalPayable - $totalPayments;

        // Update the balance in the subscription model
        $subscription->balance = $balance;
        $subscription->save();
    }

    public function destroy(Payment $payment)
    {
        // Delete the payment
        $payment->delete();

        // Return a response, you can redirect or return a JSON response as needed
        return redirect()->back()->with('success', 'Payment deleted successfully.');
    }
}
