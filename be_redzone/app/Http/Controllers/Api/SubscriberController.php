<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;

use App\Models\Subscriber;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class SubscriberController extends Controller
{
    // public function index()
    // {
    //     return response()->json(Subscriber::latest()->get());
    // }

public function index(Request $request)
{
    $search = $request->get('search');
    $perPage = $request->get('per_page', 20);

    $query = Subscriber::query();

    if ($search) {
        $query->where(function ($q) use ($search) {
            $q->where('name', 'like', "%{$search}%")
              ->orWhere('email', 'like', "%{$search}%")
              ->orWhere('phone', 'like', "%{$search}%");
        });
    }

    $subscribers = $query
        ->withCount('subscriptions')
        ->orderBy('name')
        ->paginate($perPage);

    return response()->json($subscribers);
}

public function search(Request $request)
{
    $query = $request->get('query', '');

    return Subscriber::where('name', 'like', "%{$query}%")
        ->orderBy('name')
        ->limit(20)                // limit for autocomplete
        ->get(['id', 'name']);
}



    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:50',
            'address' => 'nullable|string',
        ]);



        $subscriber = Subscriber::create($data);

        return response()->json(['message' => 'Subscriber created successfully', 'subscriber' => $subscriber]);
    }

    public function show(Subscriber $subscriber)
    {
        return response()->json($subscriber);
    }

    public function update(Request $request, Subscriber $subscriber)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:subscribers,email,' . $subscriber->id,
            'phone' => 'nullable|string|max:50',
            'account_number' => 'nullable|string|max:50',
            'address' => 'nullable|string',
        ]);

        $subscriber->update($data);

        return response()->json(['message' => 'Subscriber updated successfully', 'subscriber' => $subscriber]);
    }

    public function destroy(Subscriber $subscriber)
    {
        $subscriber->delete();

        return response()->json(['message' => 'Subscriber deleted successfully']);
    }

    
}
