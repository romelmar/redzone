<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Subscription;
use App\Services\BillingService;
use Carbon\Carbon;

class CollectionController extends Controller
{
    public function collectionSheet(Request $request, BillingService $billing)
    {
        $type = $request->get('type', 'due');
        $search = $request->get('search');
        $perPage = (int) $request->get('per_page', 10);

        $billMonth = now()->startOfMonth();

        $subscriptions = Subscription::with(['subscriber', 'plan'])
            ->when($type === 'disconnected', fn ($q) => $q->where('active', false))
            ->when($type !== 'disconnected', fn ($q) => $q->where('active', true))
            ->when($search, function ($q) use ($search) {
                $q->where(function ($qq) use ($search) {
                    $qq->whereHas('subscriber', fn ($s) =>
                        $s->where('name', 'like', "%{$search}%")
                          ->orWhere('email', 'like', "%{$search}%")
                    )->orWhereHas('plan', fn ($p) =>
                        $p->where('name', 'like', "%{$search}%")
                    );
                });
            })
            ->paginate($perPage);

        $subscriptions->getCollection()->transform(function ($sub) use ($billing, $billMonth) {

            $sub->load(['addons', 'payments', 'serviceCredits']);

            $calc = $billing->computeFor($sub, $billMonth);

            $dueDate = $sub->dueDateForMonth($billMonth);

            $daysOverdue = now()->gt($dueDate)
                ? $dueDate->diffInDays(now())
                : 0;

            $collectionType = 'due';

            if (!$sub->active) {
                $collectionType = 'disconnected';
            } elseif ($daysOverdue > 0) {
                $collectionType = 'overdue';
            }

            return [
                'subscription_id' => $sub->id,
                'subscriber_name' => $sub->subscriber?->name,
                'subscriber_email' => $sub->subscriber?->email,
                'subscriber_phone' => $sub->subscriber?->phone,
                'subscriber_address' => $sub->subscriber?->address,

                'plan_name' => $sub->plan?->name,
                'plan_speed' => $sub->plan?->speed,

                'due_date' => $dueDate?->toDateString(),
                'days_overdue' => $daysOverdue,
                'collection_type' => $collectionType,

                'total_due' => (float) ($calc['total_due'] ?? 0),
            ];
        });

        return response()->json($subscriptions);
    }
}