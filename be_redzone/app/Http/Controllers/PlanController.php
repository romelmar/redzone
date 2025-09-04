<?php

namespace App\Http\Controllers;

use App\Models\Plan;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;


class PlanController extends Controller
{
    public function index()
    {
        $plans = Plan::all();

        return response()->json($plans);
    }
   
    public function dataOnly()
    {
        $plans = Plan::all();
        return response()->json($plans);
    }

    public function create()
    {
        return Inertia::render('Plans/Create');
    }


    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required',
            // 'email' => 'nullable|email|unique:subscribers',
        ]);

        Plan::create($request->all());

        return redirect()->route('plans.index');
    }

    public function show(Plan $plan)
    {
        return view('plans.show', compact('plan'));
    }

    public function edit(Plan $plan)
    {
        return view('plans.edit', compact('plan'));
    }

    public function update(Request $request, Plan $plan)
    {
        $plan->update($request->all());

        return redirect()->route('plans.index');
    }

    public function destroy(Plan $plan)
    {
        // Implementation for deleting a plan
    }
}
