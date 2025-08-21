<?php

namespace App\Http\Controllers\user\dashboard;

use App\Http\Controllers\Controller;
use App\Models\AccessLicense;
use App\Models\User;
use App\Models\UserPlan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    

    public function login(Request $request)
{
    // 1. Validate inputs (including license format)
    $request->validate([
        'email'   => 'required|email',
        'password'=> 'required',
        'machineId' => ['nullable', 'string', 'regex:/^[A-Za-z0-9\-]+$/'],
    ]);

    // 2. Check user & password
    $user = User::where('email', $request->email)->first();
    if (! $user || ! Hash::check($request->password, $user->password)) {
        return response()->json(['message' => 'Invalid credentials'], 401);
    }

    // 3. Fetch the user's plan (one record)
    $userPlan = UserPlan::where('user_id', $user->id)->first();
    // if (! $userPlan) {
    //     return response()->json([
    //         'message' => 'Please purchase a plan first at devibrant.com.'
    //     ], 403);
    // }

    // 4. Check existing license for this machine
    $licenseExists = AccessLicense::where('machine', $request->machineId)
                         ->where('user_id', $user->id)
                         ->exists();

    // 5. Count how many devices are already registered
    $licenseCount   = AccessLicense::where('user_id', $user->id)->count();
  

    // // 6A. If this device is already registered, just issue a token
    // if ($licenseExists) {
    //     $token = $user->createToken('userToken', ['user_dashboard'])->plainTextToken;
    //     return response()->json([
    //         'success' => true,
    //         'token'   => $token,
    //         'user'    => $user
    //     ], 200);
    // }

    // // 6B. If they've reached their device limit, block new registrations
    // if ($licenseCount >= 2) {
    //     return response()->json([
    //         'message' => "Device limit 2 reached. Cannot register new device."
    //     ], 403);
    // }

    // // 6C. Otherwise, register this new device and issue a token
    // AccessLicense::create([
    //     'user_id' => $user->id,
    //     'machine' => $request->machineId
    // ]);

    $token = $user->createToken('userToken', ['user_dashboard'])->plainTextToken;
    return response()->json([
        'success' => true,
        'token'   => $token,
        'user'    => $user
    ], 201);
}

}
