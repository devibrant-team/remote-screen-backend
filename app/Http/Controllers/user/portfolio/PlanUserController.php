<?php

namespace App\Http\Controllers\user\portfolio;

use App\Http\Controllers\Controller;
use App\Models\Employee\Plan;
use App\Models\UserPlan;
use Illuminate\Http\Request;
use Carbon\Carbon;

class PlanUserController extends Controller
{

    public function store(Request $request){

        $request->validate([
            'plan_id'=>'required',
            'purchased_at' => 'required',
            'payment_type'=>'required'
        ]);

        $user = auth()->user();

        if (!$user || !$request->user()->tokenCan('user_portfolio')) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $plan=Plan::where('id',$request->plan_id)->first();
        $price= $plan->price - (($plan->price*$plan->offer)/100);
        
        if($price !== $request->purchased_at ){
            return response()->json([
            'message' => "Some thing went wronge from your side"
        ], 403);
        }
        
     
        $expire_date = Carbon::now()->addYears($plan->plan_time);
        $user_plan=UserPlan::create([
            'user_id'=>$user->id,
            'plan_id'=>$request->plan_id,
            'purchased_at'=>$request->purchased_at,
            'num_screen'=>$plan->screen_number,
            'storage'=>$plan->storage,
            'expire_date'=>$expire_date,
        ]);

         return response()->json([
        'success' => 'true'
    ]);



    }

}
