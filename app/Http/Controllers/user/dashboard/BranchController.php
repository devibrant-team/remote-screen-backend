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


    public function update(Request $request, $id)
{
    $request->validate([
        'name' => 'required|string|max:255',
    ]);

    $user = auth()->user();

    if (!$user || !$request->user()->tokenCan('user_dashboard')) {
        return response()->json(['error' => 'Unauthorized'], 401);
    }

    // Make sure the branch belongs to the authenticated user
    $branch = Branches::where('id', $id)
        ->where('user_id', $user->id)
        ->firstOrFail();

    $branch->update([
        'name' => $request->name,
    ]);

    return response()->json([
        'success' => true,
    ]);
}


}
