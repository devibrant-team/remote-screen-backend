<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Http\Requests\Employee\PlanRequest;
use App\Models\Employee\Plan;
use Illuminate\Http\Request;

class PlanController extends Controller
{

    public function index()
    {
        $plan = Plan::all();
        return response()->json(['plans' => $plan]);
    }
    // every plan have user
    public function planwithuser()
    {
        $user = auth()->user();

        if (!$user || !$user->tokenCan('Admin')) {
            return response()->json(['error' => 'Unauthorized1234'], 401);
        }
        $plans = Plan::withCount('userPlans') // this gives us user_plans_count
            ->get(['id', 'name']);

        return response()->json([
            'plans' => $plans->map(function ($plan) {
                return [
                    'id' => $plan->id,
                    'name' => $plan->name,
                    'user_count' => $plan->user_plans_count
                ];
            })
        ]);
    }


    public function store(PlanRequest $request)
    {

        $data = $request->validated();
        $data['access_num'] = 2;
        // ✅ force access_num = 2

        $user = auth()->user();

        if (!$user || !$request->user()->tokenCan('Admin')) {
            return response()->json(['error' => 'Unauthorized1234'], 401);
        }

        $plan = Plan::create($data);

        return response()->json(['success' => true, 'plan' => $plan]);
    }

    public function update(PlanRequest $request, $id)
    {

        $data = $request->validated();

        $plan = Plan::findOrFail($id);

        if (!$plan) {

            return response()->json(['error' => 'This plan does not exist']);
        }
        $user = auth()->user();

        if (!$user || !$request->user()->tokenCan('admin')) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $updatedplan = Plan::update($data);

        return response()->json(['success' => true, 'plan' => $updatedplan]);
    }
}
