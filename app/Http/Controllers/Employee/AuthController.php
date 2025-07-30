<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Models\Employee\Employee;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;
class AuthController extends Controller
{

 public function index(){
     return response()->json([
            'message' => 'Invalid credentials'
        ]);
 }

     public function login(Request $request)
{
    $request->validate([
        'email' => 'required|email',
        'password' => 'required',
    ]);
    

    $user = Employee::where('email', $request->email)->first();

    if (! $user || ! Hash::check($request->password, $user->password)) {
        return response()->json([
            'message' => 'Invalid credentials'
        ], 401);
    }

$expiryTime = Carbon::now()->addHours(8); 
$token = $user->createToken('employeeToken', ['Admin'], $expiryTime)->plainTextToken;


    // $token = $user->createToken('authToken')->plainTextToken;

    return response()->json([
        'success'=>'true',
        'token' => $token,
    ]);
}

public function logout(Request $request)
{
    $user = auth('sanctum')->user(); // Use sanctum guard explicitly if needed

    if ($user && $user->currentAccessToken()) {
        $user->currentAccessToken()->delete();
    }

    return response()->json([
        'success' => true,
        'message' => 'Logged out successfully',
    ]);
}

}
