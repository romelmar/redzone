<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use Illuminate\Http\Request;
use App\Models\Subscription;
use App\Models\SubscriptionHistory;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;


class SubscriptionController extends Controller
{
    public function index()
    {

        return Subscription::with(['subscriber', 'plan'])->get();
        // return response()->json($subscriptions);
    }

    // public function store(Request $request)
    // {
    //     $request->validate([
    //         'subscriber_id' => 'required',
    //         'plan_id' => 'required',
    //         'start_date' => 'required|date',
    //         'next_billing_date' => 'required|date',
    //         'status' => 'required|in:active,inactive',
    //     ]);

    //     $subscription = Subscription::create($request->all());

    //     return redirect()->route('subscribers.show', ['subscriber' => $request->subscriber_id]);
    // }
    public function store(Request $request)
    {
        $request->validate([
            'subscriber_id' => 'required',
            'plan_id' => 'required',
            'start_date' => 'required|date',
            'next_billing_date' => 'required|date',
            'status' => 'required|in:active,inactive',
            // 'payment_amount' => 'required|numeric|min:0', // Add this if you want to handle payment amount
        ]);

        // Create the subscription
        // $subscription = Subscription::create($request->only(['subscriber_id', 'plan_id', 'start_date', 'next_billing_date', 'status']));
        $subscription = Subscription::create($request->all());
        // // After creating the subscription, handle the payment
        // Payment::create([
        //     'subscription_id' => $subscription->id,
        //     'amount' => $request->payment_amount, // Payment amount from request
        //     'payment_type' => 'payment', // Set the payment type to 'payment'
        //     'note' => '',
        //     'payment_date' => now(),
        // ]);



        return redirect()->route('subscribers.show', ['subscriber' => $request->subscriber_id]);
    }


    public function show(Subscription $subscription)
    {
        // $subscription->load(['subscriber', 'plan']);
        // return response()->json($subscription);

        return $subscription->load(['subscriber', 'plan']);
    }

    public function downloadSoa(Subscription $subscription)
    {
        // Load related data
        $subscription->load('plan', 'subscriber', 'payments');

        $pdf = Pdf::loadView('pdf.soa', [
            'subscription' => $subscription
        ]);

        $filename = "SOA-{$subscription->id}.pdf";

        return $pdf->download($filename);
    }

    public function update(Request $request, Subscription $subscription)
    {
        $request->validate([
            'id' => 'required|unique:subscriptions',
            'subscriber_id' => 'required',
            'plan_id' => 'required',
            'start_date' => 'required|date',
            'next_billing_date' => 'required|date',
            'status' => 'required|in:active,inactive',
        ]);

        $subscription->update($request->all());

        return response()->json($subscription, 200);
    }

    public function deactivate(Subscription $subscription)
    {
        $subscription->status = 'inactive';
        $subscription->deactivate_date = now(); // Store the deactivation date
        $subscription->save();

        // Record the deactivation in the history
        SubscriptionHistory::create([
            'subscription_id' => $subscription->id,
            'action' => 'deactivated',
        ]);

        return redirect()->back()->with('success', 'Subscription deactivated successfully.');
    }

    // public function activate(Subscription $subscription)
    // {
    //     $subscription->status = 'active';
    //     $subscription->save();

    //     // Record the activation in the history
    //     SubscriptionHistory::create([
    //         'subscription_id' => $subscription->id,
    //         'action' => 'activated',
    //     ]);

    //     return redirect()->back()->with('success', 'Subscription activated successfully.');
    // }

    // app/Http/Controllers/SubscriptionController.php

    public function activate(Subscription $subscription)
    {
        // Check if there is no remaining due balance
        // $remainingBalance = $subscription->payments->sum('amount') - ($subscription->plan->price * $subscription->monthlyDetails->count());
        // if ($remainingBalance > 0) {
        //     return response()->json(['message' => 'Cannot activate subscription with remaining due balance'], 400);
        // }

        // Calculate the number of days between deactivation and activation
        if ($subscription->deactivate_date) {
            $deactivationDate = Carbon::parse($subscription->deactivate_date);
            $activationDate = now();
            $daysInactive = $deactivationDate->diffInDays($activationDate);

            // Calculate the daily rate
            $dailyRate = $subscription->plan->price / 30;

            // Create a payment entry with the offset
            Payment::create([
                'subscription_id' => $subscription->id,
                'amount' => $daysInactive * $dailyRate, // Deduct the inactive days
                'payment_type' => 'offset', // Set the payment type to offset
                'note' => 'offset for ' . $daysInactive . ' days of inactivity',
                'payment_date' => now(),
            ]);
        }

        // Clear the deactivate_date and activate the subscription
        $subscription->status = 'active';
        $subscription->deactivate_date = null;
        $subscription->save();

        // Record the activation in the history
        SubscriptionHistory::create([
            'subscription_id' => $subscription->id,
            'action' => 'activated',
        ]);

        return redirect()->back()->with('success', 'Subscription activated successfully.');
    }



    public function history(Subscription $subscription)
    {
        $history = $subscription->histories()->orderBy('created_at', 'desc')->get();
        return Inertia::render('Subscription/SubscriptionHistory', ['history' => $history]);
    }

    public function destroy($id)
    {
        // Delete subscription logic
        Subscription::destroy($id);

        // Flash success message
        return redirect()->back()->with('success', 'Subscription deleted successfully.');
    }
}
