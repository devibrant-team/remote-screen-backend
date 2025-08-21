<?php

namespace App\Http\Controllers\User\dashboard;

use App\Http\Controllers\Controller;
use App\Models\Branches;
use Illuminate\Http\Request;

class BranchController extends Controller
{

    public function index(Request $request){
         $user = auth()->user();

    if (!$user || !$request->user()->tokenCan('user_dashboard')) {
        return response()->json(['error' => 'Unauthorized'], 401);
    }

    $branches =Branches::where('user_id',$user->id)->get();
    return response()->json(['success' => true, 'branches' => $branches]);

    }


    public function store(Request $request ){
        $request->validate([
            'name'=>'required'
        ]);
    
        $user = auth()->user();

    if (!$user || !$request->user()->tokenCan('user_dashboard')) {
        return response()->json(['error' => 'Unauthorized'], 401);
    }
    $branch=Branches::create([
        'name'=>$request->name,
        'user_id'=>$user->id
    ]);
    return response()->json(['success' => true, 'branch' => $branch]);
        
    }
}
