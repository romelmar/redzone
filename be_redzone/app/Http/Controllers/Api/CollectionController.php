<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CollectionAssignment;
use App\Models\Subscription;
use App\Services\BillingService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class CollectionController extends Controller
{
    public function collectionSheet(Request $request, BillingService $billing)
    {
        $type = $request->get('type', 'due'); // due | overdue | disconnected
        $search = trim((string) $request->get('search', ''));
        $collectorName = trim((string) $request->get('collector_name', ''));
        $assignmentDate = $request->get('assignment_date', now()->toDateString());
        $assignmentStatus = $request->get('assignment_status'); // assigned | unassigned | null
        $perPage = (int) $request->get('per_page', 10);

        $assignmentDateCarbon = Carbon::parse($assignmentDate);
        $billMonth = $assignmentDateCarbon->copy()->startOfMonth();

        $query = Subscription::query()
            ->with([
                'subscriber',
                'plan',
                'addons',
                'payments',
                'serviceCredits',
                'collectionAssignments' => function ($q) use ($assignmentDate) {
                    $q->whereDate('assignment_date', $assignmentDate);
                },
            ])
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($qq) use ($search) {
                    $qq->whereHas('subscriber', function ($s) use ($search) {
                        $s->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%")
                            ->orWhere('phone', 'like', "%{$search}%")
                            ->orWhere('address', 'like', "%{$search}%");
                    })->orWhereHas('plan', function ($p) use ($search) {
                        $p->where('name', 'like', "%{$search}%");
                    });
                });
            });

        $paginator = $query->paginate($perPage);

        $rows = collect($paginator->items())->map(function ($sub) use (
            $billing,
            $billMonth,
            $assignmentDateCarbon
        ) {
            $calc = $billing->computeFor($sub, $billMonth);

            $dueDate = $sub->dueDateForMonth($billMonth);

            $daysOverdue = $assignmentDateCarbon->startOfDay()->gt($dueDate->copy()->startOfDay())
                ? $dueDate->diffInDays($assignmentDateCarbon)
                : 0;

            $collectionType = 'due';

            if (!$sub->active) {
                $collectionType = 'disconnected';
            } elseif ($daysOverdue > 0) {
                $collectionType = 'overdue';
            }

            $assignment = $sub->collectionAssignments->first();

            return [
                'subscription_id'    => $sub->id,
                'assignment_id'      => $assignment?->id,
                'assignment_date'    => $assignment?->assignment_date?->toDateString(),
                'collector_name'     => $assignment?->collector_name,
                'notes'              => $assignment?->notes,

                'assignment_status'  => $assignment ? 'assigned' : 'unassigned',

                'subscriber_name'    => $sub->subscriber?->name,
                'subscriber_email'   => $sub->subscriber?->email,
                'subscriber_phone'   => $sub->subscriber?->phone,
                'subscriber_address' => $sub->subscriber?->address,

                'plan_name'          => $sub->plan?->name,
                'plan_speed'         => $sub->plan?->speed,

                'due_date'           => $dueDate?->toDateString(),
                'days_overdue'       => $daysOverdue,
                'collection_type'    => $collectionType,
                'active'             => (bool) $sub->active,

                'previous_balance'   => (float) ($calc['previous_balance'] ?? 0),
                'current_bill'       => (float) ($calc['current_bill'] ?? 0),
                'total_due'          => (float) ($calc['total_due'] ?? 0),
            ];
        });

        // Filter by due / overdue / disconnected
        if ($type === 'due') {
            $rows = $rows->filter(
                fn($r) =>
                $r['collection_type'] === 'due' && $r['total_due'] > 0
            );
        } elseif ($type === 'overdue') {
            $rows = $rows->filter(
                fn($r) =>
                $r['collection_type'] === 'overdue' && $r['total_due'] > 0
            );
        } elseif ($type === 'disconnected') {
            $rows = $rows->filter(
                fn($r) =>
                $r['collection_type'] === 'disconnected'
            );
        }

        // Optional collector filter
        if ($collectorName !== '') {
            $rows = $rows->filter(
                fn($r) =>
                str_contains(strtolower($r['collector_name'] ?? ''), strtolower($collectorName))
            );
        }

        // Optional assigned / unassigned filter
        if ($assignmentStatus === 'assigned') {
            $rows = $rows->filter(fn($r) => $r['assignment_status'] === 'assigned');
        } elseif ($assignmentStatus === 'unassigned') {
            $rows = $rows->filter(fn($r) => $r['assignment_status'] === 'unassigned');
        }

        $rows = $rows->values();

        // Replace paginator collection
        $paginator->setCollection($rows);

        return response()->json($paginator);
    }



    public function printCollectionSheet(Request $request, BillingService $billing)
    {
        $date = $request->get('assignment_date', now()->toDateString());
        $collector = $request->get('collector_name');

        $billMonth = Carbon::parse($date)->startOfMonth();

        Log::info($request->all());

        $assignments = CollectionAssignment::with([
            'subscription.subscriber',
            'subscription.plan',
            'subscription.addons',
            'subscription.payments',
            'subscription.serviceCredits'
        ])
            ->whereDate('assignment_date', $date)
            ->when($collector, fn($q) => $q->where('collector_name', $collector))
            ->get();

        $rows = $assignments->map(function ($assignment) use ($billing, $billMonth) {

            $sub = $assignment->subscription;

            if (!$sub) return null;

            $calc = $billing->computeFor($sub, $billMonth);

            return [
                'subscriber' => $sub->subscriber?->name,
                'phone' => $sub->subscriber?->phone,
                'address' => $sub->subscriber?->address,
                'plan' => $sub->plan?->name,
                'speed' => $sub->plan?->speed,
                'amount_due' => $calc['total_due']

            ];
        })->filter()->values();

        $pdf = Pdf::loadView('pdf.collection-sheet', [
            'rows' => $rows,
            'collector' => $collector,
            'date' => $date
        ])->setPaper('a4');

        return $pdf->download("collection-sheet-$date.pdf");
    }

    public function removeAssignment(CollectionAssignment $assignment)
    {
        $assignment->delete();

        return response()->json([
            'message' => 'Collector assignment removed successfully.'
        ]);
    }
}
