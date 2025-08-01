<?php

namespace App\Http\Controllers\user\portfolio;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Http\Request;

class AuthController extends Controller
{

       public function signup(Request $request)
{
     $request->validate([
        'name' => 'required',
        'email' => 'required|email',
        'password' => 'required',
 
        
    ]);
      $user = User::create([
        'name' => $request->name,
        'email' => $request->email,
        'password' => Hash::make($request->password),
    ]);


    $token = $user->createToken('userToken', ['user_portfolio'])->plainTextToken;

    return response()->json([
        'success' => 'true',
        'token' => $token,
        'user'=>$user
    ]);




}


      public function login(Request $request)
{
    $request->validate([
        'email' => 'required|email',
        'password' => 'required',
    ]);
    

    $user = User::where('email', $request->email)->first();

    if (! $user || ! Hash::check($request->password, $user->password)) {
        return response()->json([
            'message' => 'Invalid credentials'
        ], 401);
    }

// $expiryTime = Carbon::now()->addHours(8); 
   $token = $user->createToken('userToken', ['user_portfolio'])->plainTextToken;


    // $token = $user->createToken('authToken')->plainTextToken;

    return response()->json([
        'success'=>'true',
        'token' => $token,
    ]);
}




}
