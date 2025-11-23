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
        return response()->json(Subscriber::latest()->get());
    }

     public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:subscribers,email',
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
