<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CollectionAssignment;
use Illuminate\Http\Request;

class CollectionAssignmentController extends Controller
{
    public function index(Request $request)
    {
        $query = CollectionAssignment::with(['subscription.subscriber', 'subscription.plan']);

        if ($request->filled('assignment_date')) {
            $query->whereDate('assignment_date', $request->assignment_date);
        }

        if ($request->filled('collector_name')) {
            $query->where('collector_name', $request->collector_name);
        }

        return response()->json(
            $query->latest()->paginate($request->get('per_page', 10))
        );
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'subscription_ids'   => ['required', 'array', 'min:1'],
            'subscription_ids.*' => ['required', 'exists:subscriptions,id'],
            'assignment_date'    => ['required', 'date'],
            'collector_name'     => ['required', 'string', 'max:255'],
            'notes'              => ['nullable', 'string'],
        ]);

        $created = collect($validated['subscription_ids'])->map(function ($subscriptionId) use ($validated) {
            return CollectionAssignment::updateOrCreate(
                [
                    'subscription_id' => $subscriptionId,
                    'assignment_date' => $validated['assignment_date'],
                ],
                [
                    'collector_name' => $validated['collector_name'],
                    'notes' => $validated['notes'] ?? null,
                ]
            );
        });

        return response()->json([
            'message' => 'Collector assigned successfully.',
            'count' => $created->count(),
            'data' => CollectionAssignment::with(['subscription.subscriber', 'subscription.plan'])
                ->whereIn('id', $created->pluck('id'))
                ->get(),
        ], 201);
    }

    public function destroy(CollectionAssignment $collectionAssignment)
    {
        $collectionAssignment->delete();

        return response()->json([
            'message' => 'Collection assignment removed successfully.',
        ]);
    }

    public function bulkReassign(Request $request)
    {
        $validated = $request->validate([
            'assignment_ids'   => ['required', 'array', 'min:1'],
            'assignment_ids.*' => ['required', 'exists:collection_assignments,id'],
            'collector_name'   => ['required', 'string', 'max:255'],
            'notes'            => ['nullable', 'string'],
        ]);

        $updated = CollectionAssignment::whereIn('id', $validated['assignment_ids'])
            ->get()
            ->each(function ($assignment) use ($validated) {
                $assignment->update([
                    'collector_name' => $validated['collector_name'],
                    'notes' => $validated['notes'] ?? $assignment->notes,
                ]);
            });

        return response()->json([
            'message' => 'Assignments reassigned successfully.',
            'count' => count($validated['assignment_ids']),
        ]);
    }

    public function bulkDelete(Request $request)
    {
        $validated = $request->validate([
            'assignment_ids'   => ['required', 'array', 'min:1'],
            'assignment_ids.*' => ['required', 'exists:collection_assignments,id'],
        ]);

        CollectionAssignment::whereIn('id', $validated['assignment_ids'])->delete();

        return response()->json([
            'message' => 'Assignments removed successfully.',
            'count' => count($validated['assignment_ids']),
        ]);
    }

    public function assignFromCollectionSheet(Request $request)
    {
        $validated = $request->validate([
            'subscription_ids' => ['required', 'array', 'min:1'],
            'subscription_ids.*' => ['exists:subscriptions,id'],
            'collector_name' => ['required', 'string'],
            'assignment_date' => ['required', 'date'],
            'notes' => ['nullable', 'string']
        ]);

        foreach ($validated['subscription_ids'] as $subId) {

            CollectionAssignment::updateOrCreate(
                [
                    'subscription_id' => $subId,
                    'assignment_date' => $validated['assignment_date']
                ],
                [
                    'collector_name' => $validated['collector_name'],
                    'notes' => $validated['notes'] ?? null
                ]
            );
        }

        return response()->json([
            'message' => 'Collector assigned successfully.'
        ]);
    }


}
