<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CollectionAssignment;
use App\Services\BillingService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;

class CollectionRouteController extends Controller
{
    public function index(Request $request, BillingService $billing)
    {
        $date = $request->get('assignment_date', now()->toDateString());
        $collector = $request->get('collector_name');

        $billMonth = Carbon::parse($date)->startOfMonth();

        $query = CollectionAssignment::with([
            'subscription.subscriber',
            'subscription.plan',
            'subscription.addons',
            'subscription.payments',
            'subscription.serviceCredits'
        ])
        ->whereDate('assignment_date', $date);

        if ($collector) {
            $query->where('collector_name', $collector);
        }

        $assignments = $query->get();

        $rows = $assignments->map(function ($assignment) use ($billing, $billMonth) {

            $sub = $assignment->subscription;

            if (!$sub) return null;

            $calc = $billing->computeFor($sub, $billMonth);

            return [
                'subscriber_name' => $sub->subscriber?->name,
                'phone' => $sub->subscriber?->phone,
                'address' => $sub->subscriber?->address,
                'plan' => $sub->plan?->name,
                'speed' => $sub->plan?->speed,
                'amount_due' => $calc['total_due'],
                'collector' => $assignment->collector_name,
                'notes' => $assignment->notes,
            ];
        })->filter()->values();

        return response()->json($rows);
    }

    public function exportPdf(Request $request, BillingService $billing)
    {
        $date = $request->get('assignment_date', now()->toDateString());
        $collector = $request->get('collector_name');

        $billMonth = Carbon::parse($date)->startOfMonth();

        $query = CollectionAssignment::with([
            'subscription.subscriber',
            'subscription.plan',
            'subscription.addons',
            'subscription.payments',
            'subscription.serviceCredits'
        ])
        ->whereDate('assignment_date', $date);

        if ($collector) {
            $query->where('collector_name', $collector);
        }

        $assignments = $query->get();

        $rows = $assignments->map(function ($assignment) use ($billing, $billMonth) {

            $sub = $assignment->subscription;

            if (!$sub) return null;

            $calc = $billing->computeFor($sub, $billMonth);

            return [
                'subscriber_name' => $sub->subscriber?->name,
                'phone' => $sub->subscriber?->phone,
                'address' => $sub->subscriber?->address,
                'plan' => $sub->plan?->name,
                'speed' => $sub->plan?->speed,
                'amount_due' => $calc['total_due'],
                'notes' => $assignment->notes,
            ];
        })->filter()->values();

        $pdf = Pdf::loadView('pdf.collection-route', [
            'rows' => $rows,
            'collector' => $collector,
            'date' => $date
        ])->setPaper('a4');

        return $pdf->download("collection-route-$date.pdf");
    }
}