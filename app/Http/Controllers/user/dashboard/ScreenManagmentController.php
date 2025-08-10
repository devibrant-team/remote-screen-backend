<?php

namespace App\Http\Controllers\User\dashboard;

use App\Http\Controllers\Controller;
use App\Models\Branches;
use App\Models\Ratio;
use App\Models\Screens;
use App\Models\UserScreens;
use Illuminate\Http\Request;

class ScreenManagmentController extends Controller
{


    public function getRatio(Request $request){

         $user = auth()->user();
    if (!$user || !$request->user()->tokenCan('user_dashboard')) {
        return response()->json(['error' => 'Unauthorized'], 401);
    }

    $ratio = Ratio::where('user_id', $user->id)->get();

   return response()->json([
        'success' => true,
        'ratio' => $ratio,
    ]);

    }

    public function postBranch(Request $request){
    
        $request->validate([
            'name'=>'required',
        ]);     
        
        $user = auth()->user();
    if (!$user || !$request->user()->tokenCan('user_dashboard')) {
        return response()->json(['error' => 'Unauthorized'], 401);
    }
    $branch = Branches::create([
        'name' => $request->name,
        'user_id' => $user->id,
    ]);
   
    return response()->json([
        'success' => true,
        'branch' => $branch,
    ]);

    }

    public function getBranch (Request $request){

        $user = auth()->user();

    if (!$user || !$request->user()->tokenCan('user_dashboard')) {
        return response()->json(['error' => 'Unauthorized'], 401);
    }
    
    $getuserBranch= Branches::where('user_id',$user->id)->get();

    return response()->json([
        'success' => true,
        'branch' => $getuserBranch,
    ]);

    }

    public function addScreen (Request $request){
        
        $request->validate([
            'code'=>'required|integer',
            'name' => 'required',
            'ratio_id'=> 'required|exists:ratio,id',
            'branch_id' => 'required|exists:branches,id'
        ] );


     $user = auth()->user();

    if (!$user || !$request->user()->tokenCan('user_dashboard')) {
        return response()->json(['error' => 'Unauthorized'], 401);
    }

    $codeExists = Screens::where('code',$request->code)->first();

    if (!$codeExists){
         return response()->json([
        'success' => false,
        'message' => 'The code is incorrect',
    ]);
  }

   $codeExists->update([
    'name' => $request->name,
    'ratio_id' => $request->ratio_id,
    'branch_id' => $request->branch_id,
 ]);

$userScreen = UserScreens::create([
    'screen_id' => $codeExists->id,
    'user_id'=>$user->id,
 ]);


    }
}
