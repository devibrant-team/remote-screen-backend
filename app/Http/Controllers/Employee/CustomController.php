<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Http\Requests\Employee\CustomRequest;
use App\Models\Employee\Custom;
use Illuminate\Http\Request;

class CustomController extends Controller
{

    public function index (){
        $plan= Custom::all();
        return response()->json(["custom"=>$plan]);
    }
    
 public function store(CustomRequest $request){

     $data = $request->validated();
 
      
        $user=auth()->user();

       if (!$user || !$request->user()->tokenCan('admin')) {
    return response()->json(['error' => 'Unauthorized'], 401);
}

      $plan= Custom::create($data);
  
        return response()->json(['success' => true , 'custom' =>$plan]);


    }



 public function update(CustomRequest $request,$id){

     $data = $request->validated();
 
     $plan = Plan::findOrFail($id);

     if(!$plan){

         return response()->json(['error' => 'This plan does not exist'] );
     }
     $user=auth()->user();
     
     if (!$user || !$request->user()->tokenCan('admin')) {
         return response()->json(['error' => 'Unauthorized'], 401);
}

      $updatedplan= Plan::update($data);
  
        return response()->json(['success' => true , 'plan' =>$updatedplan]);


    }

}
