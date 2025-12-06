<?php
// app/Models/Subscription.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class Subscription extends Model
{
    // protected $fillable = ['id','subscriber_id', 'plan_id', 'start_date', 'next_billing_date', 'status','balance'];

    protected $fillable = [
        'subscriber_id',
        'plan_id',
        'start_date',
        'end_date',
        'monthly_discount',
        'active',
        'deactivated_at',
        'reactivated_days_passed',
    ];

protected $casts = [
    'start_date' => 'date',
    'end_date' => 'date',
    'active' => 'boolean',
    'deactivated_at' => 'datetime',
];



    public function subscriber()
    {
        return $this->belongsTo(Subscriber::class);
    }

    public function plan()
    {
        return $this->belongsTo(Plan::class);
    }

    public function addons()
    {
        return $this->hasMany(Addon::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function serviceCredits()
    {
        return $this->hasMany(ServiceCredit::class);
    }

    // due date is the day-of-month of start_date for any given month
    public function dueDateForMonth(Carbon $month): Carbon
    {
        $day = (int)$this->start_date->day;
        return (clone $month)->day($day); // $month is first-of-month
    }

    public function discount()
    {
        return $this->hasOne(Discount::class);
    }

    // Method to compute the balance for the subscription
    public function computeBalance()
    {
        $balance = 0;

        // Retrieve payments made for this subscription on the billing month
        $payments = $this->payments()->where('billing_month', $this->next_billing_date->format('Y-m'))->sum('amount');

        // Compute the subscription billing amount
        $subscriptionBillingAmount = $this->plan->price; // Assuming there's a column 'price' in the plans table

        // Compute the balance by subtracting payments from the subscription billing amount
        $balance = $subscriptionBillingAmount - $payments;

        return $balance;
    }


    public function histories()
    {
        return $this->hasMany(SubscriptionHistory::class);
    }
}
