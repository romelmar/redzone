<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use App\Models\Payment;
use App\Models\Addon;
use App\Models\ServiceCredit;
use Illuminate\Http\Request;

class SubscriptionHistoryController extends Controller
{
    public function index(Subscription $subscription)
    {
        $events = [];

        // ─────────────────────────────────────────────
        // 1. Subscription Created
        // ─────────────────────────────────────────────
        $events[] = [
            'date'        => $subscription->created_at->format('Y-m-d'),
            'type'        => 'created',
            'title'       => 'Subscription Created',
            'description' => "Subscriber enrolled in plan: {$subscription->plan->name}",
        ];

        // ─────────────────────────────────────────────
        // 2. Plan Updates (if plan_id changed)
        // ─────────────────────────────────────────────
        if ($subscription->wasChanged('plan_id')) {
            $oldPlan = $subscription->getOriginal('plan_id');
            $newPlan = $subscription->plan_id;

            $events[] = [
                'date'        => now()->format('Y-m-d'),
                'type'        => 'plan_change',
                'title'       => 'Plan Updated',
                'description' => "Changed plan from ID #{$oldPlan} to ID #{$newPlan}.",
            ];
        }

        // ─────────────────────────────────────────────
        // 3. Payments
        // ─────────────────────────────────────────────
        foreach ($subscription->payments as $p) {
            $events[] = [
                'date'        => $p->payment_date ? $p->payment_date->format('Y-m-d') : $p->created_at->format('Y-m-d'),
                'type'        => 'payment',
                'title'       => 'Payment Received',
                'description' => "Paid ₱" . number_format($p->amount, 2),
            ];
        }

        // ─────────────────────────────────────────────
        // 4. Add-ons
        // ─────────────────────────────────────────────
        foreach ($subscription->addons as $a) {
            $events[] = [
                'date'        => $a->created_at->format('Y-m-d'),
                'type'        => 'addon',
                'title'       => 'Add-On Applied',
                'description' => "{$a->name} (₱" . number_format($a->amount, 2) . ")",
            ];
        }

        // ─────────────────────────────────────────────
        // 5. Service Credits (outage refunds)
        // ─────────────────────────────────────────────
        foreach ($subscription->serviceCredits as $c) {
            $events[] = [
                'date'        => $c->created_at->format('Y-m-d'),
                'type'        => 'credit',
                'title'       => 'Service Credit Applied',
                'description' => "{$c->days} day(s) refunded • ₱" . number_format($c->amount, 2),
            ];
        }

        // ─────────────────────────────────────────────
        // 6. Activation / Deactivation (optional)
        // ─────────────────────────────────────────────
        if ($subscription->wasChanged('active')) {
            $status = $subscription->active ? "Activated" : "Deactivated";

            $events[] = [
                'date'        => now()->format('Y-m-d'),
                'type'        => 'status',
                'title'       => "Subscription {$status}",
                'description' => "Subscription was {$status}.",
            ];
        }

        // ─────────────────────────────────────────────
        // Sort newest first
        // ─────────────────────────────────────────────
        usort($events, function ($a, $b) {
            return strtotime($b['date']) <=> strtotime($a['date']);
        });

        return response()->json($events);
    }
}
