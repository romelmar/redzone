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
        'collector_name',

    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'active' => 'boolean',
        'deactivated_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::created(function (self $subscription) {
            $subscription->recordRate($subscription->start_date->copy()->startOfMonth());
        });
        static::updated(function (self $subscription) {
            if ($subscription->wasChanged(['plan_id', 'monthly_discount'])) {
                $subscription->unsetRelation('plan');
                $subscription->recordRate(now()->startOfMonth()->addMonth());
            }
        });
    }

    public function rates()
    {
        return $this->hasMany(SubscriptionRate::class);
    }

    public function recordRate(Carbon $effectiveFrom): void
    {
        $effectiveFrom = $effectiveFrom->max($this->start_date->copy()->startOfMonth());
        $this->rates()->updateOrCreate(['effective_from' => $effectiveFrom->toDateString()], [
            'price' => $this->plan->price,
            'discount' => $this->monthly_discount ?? 0,
        ]);
        $this->unsetRelation('rates');
    }

    public function rateForMonth(Carbon $month): array
    {
        $rate = $this->rates->filter(fn ($rate) => $rate->effective_from->lte($month->copy()->startOfMonth()))
            ->sortByDesc('effective_from')->first();

        return [
            'price' => (float) ($rate?->price ?? $this->plan?->price ?? 0),
            'discount' => (float) ($rate?->discount ?? $this->monthly_discount ?? 0),
        ];
    }



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
        return $month->copy()->day(min($day, $month->daysInMonth));
    }

    public function billingPeriodStartForMonth(Carbon $month): Carbon
    {
        $day = min((int) $this->start_date->day, $month->daysInMonth);
        return $month->copy()->day($day);
    }

    public function billingPeriodEndForMonth(Carbon $month): Carbon
    {
        return $this->billingPeriodStartForMonth($month->copy()->startOfMonth()->addMonth())->subDay();
    }

    public function billingPeriodForMonth(Carbon $month): array
    {
        $start = $this->billingPeriodStartForMonth($month);

        return [
            'start' => $start,
            'end' => $this->billingPeriodEndForMonth($month),
        ];
    }

    public function billingMonthCount(Carbon $month): int
    {
        $start = $this->start_date->copy()->startOfMonth();
        $current = $month->copy()->startOfMonth();

        if ($current->lt($start)) {
            return 0;
        }

        return $start->diffInMonths($current) + 1;
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

    public function events()
    {
        return $this->hasMany(\App\Models\SubscriptionEvent::class)->latest('event_at');
    }

    public function collectionAssignments()
    {
        return $this->hasMany(\App\Models\CollectionAssignment::class);
    }
}
