<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Http\Requests\Employee\CustomRequest;
use App\Models\Employee\Custom;
use Illuminate\Http\Request;

class CustomController extends Controller
{

    public function index()
    {
        $plan = Custom::all();
        return response()->json(["custom" => $plan]);
    }

    public function store(CustomRequest $request)
    {

        $data = $request->validated();


        $user = auth()->user();

        if (!$user || !$request->user()->tokenCan('Admin')) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $plan = Custom::create($data);

        return response()->json(['success' => true, 'custom' => $plan]);
    }



    public function update(Request $request, $id)
    {
        // Fix 1: Correct validate method
        $data = $request->validate([
            'price' => 'required|numeric'
        ]);

        // Fix 2: findOrFail throws 404, no need to check manually
        $custom = Custom::findOrFail($id);

        // Fix 3: Properly check token
        $user = auth()->user();

        if (!$user || !$request->user()->tokenCan('Admin')) {
            return response()->json(['error' => '123456'], 401);
        }

        // Update the custom price
        $custom->update([
            'price' => $data['price'],
        ]);

        return response()->json([
            'success' => true,
            'custom' => $custom
        ]);
    }
}
