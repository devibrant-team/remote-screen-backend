<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Models\Screens;
use App\Models\UserScreens;
use Illuminate\Http\Request;

class ScreenController extends Controller
{
    public function userScreens(Request $request,$id){

  $user = auth()->user();

    if (!$user || !$request->user()->tokenCan('Admin')) {
        return response()->json(['error' => 'Unauthorized1234'], 401);
    }



$userScreen = UserScreens::where('user_id',$id)->with('screen:id,name,ratio_id','screen.ratio:id,ratio')->get();

           $formattedUsers = $userScreen->map(function ($user) {
        return [
            'id' => $user->id,
            'name' => $user->screen->name,
            'ratio' => $user->screen->ratio->ratio,
            'extra' => $user->is_extra,
            'type' => $user->type,
        ];
    });

    return response()->json([
        'success' => true,
        'screens' => $formattedUsers,
      
    ]);
   
    
    }

public function screenStatic(){

    $android=UserScreens::where('type','Android')->count();

    $androidStick=UserScreens::where('type','Android Stick')->count();
    
    $windows=UserScreens::where('type','Windows')->count();

    $total=$android+$androidStick+$windows;

   return response()->json([
        'success' => true,
        'total' => $total,
        'android' => $android,
        'android_stick' => $androidStick,
        'windows' => $windows,
        
    ]);

}


public function screenStatus(){



    $active=Screens::where('status',1)->count();
    $notactive=Screens::where('status',0)->count();

   return response()->json([
        'success' => true,
        'active' => $active,
        'not_active' => $notactive,
       
        
    ]);

}



}
