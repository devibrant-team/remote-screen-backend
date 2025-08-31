<?php

namespace App\Http\Controllers\User\dashboard;

use App\Events\ScreenLinked;
use App\Http\Controllers\Controller;
use App\Models\Branches;
use App\Models\Ratio;
use App\Models\Screens;
use App\Models\UserScreens;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ScreenManagmentController extends Controller
{


    public function getRatio(Request $request){

         $user = auth()->user();
    if (!$user || !$request->user()->tokenCan('user_dashboard')) {
        return response()->json(['error' => 'Unauthorized'], 401);
    }

    $ratio = Ratio::where('user_id', $user->id)->get();

        $formattedData = $ratio->map(function ($playlist) {
        return [
            'id' => $playlist->id,
            'ratio' => $playlist->ratio,
            'numerator ' => $playlist->numerator,
            'denominator' => $playlist->denominator,
            'width' => $playlist->width,
            'height' => $playlist->height,
        ];
    });


   return response()->json([
        'success' => true,
        'ratio' => $formattedData,
    ]);

    }

    public function postBranch(Request $request){
    
        $request->validate([
            'name'=>'required',
        ]);     
        
    //     $user = auth()->user();
    // if (!$user || !$request->user()->tokenCan('user_dashboard')) {
    //     return response()->json(['error' => 'Unauthorized'], 401);
    // }
    $branch = Branches::create([
        'name' => $request->name,
        'user_id' => 1,
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


public function createScreen(Request $request)
{
    do {
        // Generate a random 6-digit number
        $code = mt_rand(100000, 999999);
    } while (Screens::where('code', $code)->exists()); // Ensure uniqueness in DB

    $screen = Screens::create([
        'name' => 'newRegistered',
        'code' => $code,
        'is_active' => 0
    ]);

    return response()->json($screen);
}

public function addScreen(Request $request)
{
    $request->validate([
        'code'      => 'required|integer|digits:6',
        'name'      => 'required|string',
        'ratio_id'  => 'nullable|exists:ratio,id',
        'branch_id' => 'nullable|exists:branches,id',
        'group_id'  => 'nullable|exists:groups,id',
    ]);

    $userId = 1; // replace with auth()->id() when ready

    $screen = Screens::where('code', $request->code)->first();
    if (!$screen) {
        return response()->json(['success' => false, 'message' => 'The code is incorrect'], 422);
    }

    $exists = UserScreens::where('user_id', $userId)->where('screen_id', $screen->id)->exists();
    if ($exists) {
        return response()->json(['success' => false, 'message' => 'This Screen is already linked to your account'], 409);
    }

    // If your 'code' column is NOT nullable, remove this line or make the column nullable first
    $screen->update([
        'name'      => $request->name,
        'ratio_id'  => $request->ratio_id,
        'branch_id' => $request->branch_id,
        'code'      => null, // comment this out if the column is NOT NULL
    ]);

    $userScreen = UserScreens::create([
        'screen_id' => $screen->id,
        'user_id'   => $userId,
        'group_id'  => $request->group_id,
        'is_extra'  => 0,
    ]);

    // Build a safe URL without needing a named route
    $nextUrl = url("/screen/{$screen->id}/ready");

    // Try to broadcast; if it fails, log the exact reason, but still return success
    try {
        broadcast(new ScreenLinked(
            originalCode: (string) $request->code,
            screenId: $screen->id,
            nextUrl: $nextUrl
        ))->toOthers();
    } catch (\Throwable $e) {
        Log::error('Broadcast ScreenLinked failed', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);
        // (optional) include a soft warning in the response for debugging the client
        return response()->json([
            'success' => true,
            'data'    => $userScreen,
            'warning' => 'Linked, but realtime broadcast failed. Check logs.',
        ], 200);
    }

    return response()->json(['success' => true, 'data' => $userScreen], 200);
}
  
  public function getusersinglescreens(Request $request){

    $user = auth()->user();

    if (!$user || !$request->user()->tokenCan('user_dashboard')) {
        return response()->json(['error' => 'Unauthorized'], 401);
    }
    
    $screens=UserScreens::where('user_id',$user->id)->with(['screen:id,name,is_active,last_seen_at,ratio_id,branch_id','screen.ratio:id,ratio','screen.branch:id,name'])->get();
if (!$screens) {
    return response()->json([
        'error' => 'No Linked Screens yet'
    ], 401);
}
    
     $formattedData = $screens->map(function ($playlist) {
        return [
            'id' => $playlist->id,
          	'screenId' => $playlist->screen->id,
            'screenName' => $playlist->screen->name,
            'ratio' =>optional(optional($playlist->screen)->ratio)->ratio,
            'branchName' => optional(optional($playlist->screen)->branch)->name,
   			'active'=>$playlist->screen->is_active,
          	'lastSeen'=>$playlist->screen->last_seen_at,
        ];
    });
    return response()->json([
                'success' => true,
                'screens' => $formattedData,
            ]);
    
  
  }
  
  
    
}
