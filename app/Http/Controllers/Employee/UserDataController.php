<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserPlan;
use Illuminate\Http\Request;

class UserDataController extends Controller
{


   public function getusersplan(Request $request)
{
    $request->validate([
        'plan_id' => 'required|integer|min:0',
        'join' => 'nullable'
    ]);

    $user = auth()->user();

    if (!$user || !$request->user()->tokenCan('Admin')) {
        return response()->json(['error' => 'Unauthorized1234'], 401);
    }

    $userPlan = [];

    if ($request->join) {
        [$year, $month] = explode('-', $request->join);
    }

    if ($request->plan_id == '0') {
        if ($request->join) {
            if($year){

                $userPlan = UserPlan::with('plan:id,name', 'user:id,name,email')
                    ->whereYear('created_at', $year)
                    ->paginate(1);
                }
                else{
                $userPlan = UserPlan::with('plan:id,name', 'user:id,name,email')
                    ->whereMonth('created_at', $month)
                    ->paginate(1);

            }
        } else {
            $userPlan = UserPlan::with('plan:id,name', 'user:id,name,email')
                ->paginate(1);
        }
    } else {
        if ($request->join) {
            if($year){

                $userPlan = UserPlan::where('plan_id', $request->plan_id)
                    ->with('plan:id,name', 'user:id,name,email')
                    ->whereYear('created_at', $year)
                    ->paginate(1);
                }else{
                $userPlan = UserPlan::where('plan_id', $request->plan_id)
                    ->with('plan:id,name', 'user:id,name,email')
                    ->whereMonth('created_at', $month)
                    ->paginate(1);

            }
        } else {
            $userPlan = UserPlan::where('plan_id', $request->plan_id)
                ->with('plan:id,name', 'user:id,name,email')
                ->paginate(1);
        }
    }

    $formattedUsers = $userPlan->map(function ($user) {
        return [
            'id' => $user->user->id,
            'name' => $user->user->name,
            'email' => $user->user->email,
            'plan_name' => $user->plan->name,
            'used_storage' => $user->plan->used_storage,
          'joined' => $user->created_at->format('Y-m-d'),
            'expire' => $user->expire_date,
        ];
    });

    return response()->json([
        'success' => true,
        'users' => $formattedUsers,
        'current_page' => $userPlan->currentPage(),
        'last_page' => $userPlan->lastPage(),
        'total' => $userPlan->total(),
        'per_page' => $userPlan->perPage(),
    ]);
}




public function search(Request $request)
{
    $request->validate([
        'query' => 'required|string'
    ]);

    $user=auth()->user();

       if (!$user || !$request->user()->tokenCan('Admin')) {
    return response()->json(['error' => 'Unauthorized1234'], 401);
}

    $query = $request->input('query');

   $users = User::where(function ($q) use ($query) {
    $q->where('email', 'like', $query . '%')
      ->orWhere('name', 'like', $query . '%');
})->get();

    $formattedUsers = $users->map(function ($user) {
    return [
        'id' => $user->id,
        'name' => $user->name,
        'email' => $user->email,
    ];
});

    return response()->json([
        'success' => true,
        'user' => $formattedUsers
    ]);
}


}
