<?php

namespace App\Http\Controllers;

use App\Models\Subscriber;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class SubscriberController extends Controller
{
    public function index()
    {

        $subscribers = Subscriber::all();
        Log::info($subscribers);
        return Inertia::render('Subscribers/Index', ['subscribers' => $subscribers]);
    }

    public function create()
    {
        return Inertia::render('Subscribers/Create');
    }

    public function store(Request $request)
    {
        // $request->validate([
        //     'name' => 'required',
        //     'email' => 'required|email',
        //     'phone' => 'required',
        //     'address' => 'required',
        // ]);
        $request->validate([
            'name' => 'required',
            // 'email' => 'nullable|email|unique:subscribers',
        ]);

        Subscriber::create($request->all());

        return redirect()->route('subscribers.index');
    }

    public function show(Subscriber $subscriber)
    {
        $subscriptions = $subscriber->subscriptions()->with('plan', 'payments')->get();

        // Calculate balance for each subscription
        foreach ($subscriptions as $subscription) {
            // Set the timezone to Asia/Manila and remove the time component
            $startDate = Carbon::parse($subscription->start_date)->timezone('Asia/Manila')->startOfDay();
            $currentDate = Carbon::now()->timezone('Asia/Manila')->startOfDay();

            // Initialize the total payable amount
            $totalPayable = 0;
            $billingDate = $startDate->copy()->addMonth(); // Start billing a month after the start date

            // Iterate month by month from the billing date to the current date
            while ($billingDate <= $currentDate) {
                $totalPayable += $subscription->plan->price;
                $billingDate->addMonth();
            }

            // Calculate total payments made for this subscription
            $totalPayments = $subscription->payments->sum('amount');

            // Compute the balance
            $balance = $totalPayable - $totalPayments;

            // Attach the balance to the subscription object
            $subscription->balance = $balance;
        }

        return Inertia::render('Subscribers/Show', [
            'subscriber' => $subscriber,
            'subscriptions' => $subscriptions,
        ]);
    }


    // public function subscribersWithDues()
    // {
    //     $subscribers = Subscriber::with(['subscriptions.payments', 'subscriptions.plan'])
    //         ->get()
    //         ->map(function ($subscriber) {
    //             $subscriber->subscriptions = $subscriber->subscriptions->map(function ($subscription) {
    //                 $subscription->monthlyDetails = collect();
    //                 $start_date = Carbon::parse($subscription->start_date)->addMonth(); // Start calculating from one month after start date
    //                 $current_date = Carbon::now();
    //                 $totalPaid = $subscription->payments->sum('amount');
    //                 $balance = $totalPaid;
    //                 $hasDueBalance = false;

    //                 while ($start_date->lessThanOrEqualTo($current_date)) {
    //                     $month_due = $subscription->plan->price;
    //                     $balance -= $month_due;
    //                     $month_balance = $balance > 0 ? 0 : abs($balance);

    //                     $subscription->monthlyDetails->push([
    //                         'month' => $start_date->format('Y-m'),
    //                         'due' => $month_due,
    //                         'balance' => $month_balance
    //                     ]);

    //                     if ($month_balance > 0) {
    //                         $hasDueBalance = true;
    //                     }

    //                     $start_date->addMonth();
    //                 }

    //                 // Attach the flag to the subscription
    //                 $subscription->hasDueBalance = $hasDueBalance;

    //                 return $subscription;
    //             });

    //             // Filter subscriptions to only include those with due balances
    //             $subscriber->subscriptions = $subscriber->subscriptions->filter(function ($subscription) {
    //                 return $subscription->hasDueBalance;
    //             });

    //             return $subscriber;
    //         })
    //         ->filter(function ($subscriber) {
    //             // Filter subscribers to only include those with subscriptions with due balances
    //             return $subscriber->subscriptions->isNotEmpty();
    //         })
    //         ->values(); // Ensure collection is converted to an array

    //     // Log the subscribers to check the data format
    //     Log::info('Subscribers with dues:', $subscribers->toArray());

    //     return Inertia::render('Subscribers/WithDues', [
    //         'subscribers' => $subscribers,
    //     ]);
    // }


    public function subscribersWithDues()
    {
        $subscribers = Subscriber::with(['subscriptions.payments', 'subscriptions.plan'])
            ->get()
            ->map(function ($subscriber) {
                // Filter only active subscriptions
                $subscriber->subscriptions = $subscriber->subscriptions->filter(function ($subscription) {
                    return $subscription->status === 'active';
                })->map(function ($subscription) {
                    $subscription->monthlyDetails = collect();
                    $start_date = Carbon::parse($subscription->start_date)->addMonth(); // Start calculating from one month after start date
                    $current_date = Carbon::now();
                    $totalPaid = $subscription->payments->sum('amount');
                    $balance = $totalPaid;
                    $hasDueBalance = false;

                    while ($start_date->lessThanOrEqualTo($current_date)) {
                        $month_due = $subscription->plan->price;
                        $balance -= $month_due;
                        $month_balance = $balance > 0 ? 0 : abs($balance);

                        $subscription->monthlyDetails->push([
                            'month' => $start_date->format('Y-m'),
                            'due' => $month_due,
                            'balance' => $month_balance
                        ]);

                        if ($month_balance > 0) {
                            $hasDueBalance = true;
                        }

                        $start_date->addMonth();
                    }

                    // Attach the flag to the subscription
                    $subscription->hasDueBalance = $hasDueBalance;

                    return $subscription;
                });

                // Filter subscriptions to only include those with due balances
                $subscriber->subscriptions = $subscriber->subscriptions->filter(function ($subscription) {
                    return $subscription->hasDueBalance;
                });

                return $subscriber;
            })
            ->filter(function ($subscriber) {
                // Filter subscribers to only include those with subscriptions with due balances
                return $subscriber->subscriptions->isNotEmpty();
            })
            ->values(); // Ensure collection is converted to an array

        // Log the subscribers to check the data format
        Log::info('Subscribers with dues:', $subscribers->toArray());

        return Inertia::render('Subscribers/WithDues', [
            'subscribers' => $subscribers,
        ]);
    }

    public function getSubscribersWithDues()
    {
        $subscribers = Subscriber::with(['subscriptions.payments', 'subscriptions.plan'])
            ->get()
            ->map(function ($subscriber) {
                $subscriber->subscriptions = $subscriber->subscriptions->map(function ($subscription) {
                    $subscription->monthlyDetails = collect();
                    $start_date = Carbon::parse($subscription->start_date)->addMonth();
                    $current_date = Carbon::now();
                    $totalPaid = $subscription->payments->sum('amount');
                    $balance = $totalPaid;
                    $hasDueBalance = false;

                    while ($start_date->lessThanOrEqualTo($current_date)) {
                        $month_due = $subscription->plan->price;
                        $balance -= $month_due;
                        $month_balance = $balance > 0 ? 0 : abs($balance);

                        $subscription->monthlyDetails->push([
                            'month' => $start_date->format('Y-m'),
                            'due' => $month_due,
                            'balance' => $month_balance
                        ]);

                        if ($month_balance > 0) {
                            $hasDueBalance = true;
                        }

                        $start_date->addMonth();
                    }

                    $subscription->hasDueBalance = $hasDueBalance;
                    return $subscription;
                });

                $subscriber->subscriptions = $subscriber->subscriptions->filter(fn($s) => $s->hasDueBalance);
                return $subscriber;
            })
            ->filter(fn($s) => $s->subscriptions->isNotEmpty())
            ->values();

        return response()->json($subscribers);
    }



    public function edit(Subscriber $subscriber)
    {
        return Inertia::render('Subscribers/Edit', ['subscriber' => $subscriber]);
    }

    public function update(Request $request, Subscriber $subscriber)
    {
        // $request->validate([
        //     'name' => 'required',
        //     'email' => 'required|email',
        //     'phone' => 'required',
        //     'address' => 'required',
        // ]);

        Log::info($request->all());
        $subscriber->update($request->all());

        return redirect()->route('subscribers.index');
    }

    public function destroy(Subscriber $subscriber)
    {
        $subscriber->delete();

        return redirect()->route('subscribers.index');
    }
}
