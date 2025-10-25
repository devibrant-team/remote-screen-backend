<?php

namespace App\Http\Controllers\User\dashboard;

use App\Http\Controllers\Controller;
use App\Models\Ratio;
use Illuminate\Http\Request;

class RatioController extends Controller
{
    public function getRatio(Request $request){

         $user = auth()->user();
    if (!$user || !$request->user()->tokenCan('user_dashboard')) {
        return response()->json(['error' => 'Unauthorized'], 401);
    }

    $ratio = Ratio::whereIn('user_id', [$user->id, 1])->get();


        $formattedData = $ratio->map(function ($playlist) {
        return [
            'id' => $playlist->id,
            'ratio' => $playlist->ratio,
            'numerator' => $playlist->numerator,
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

    public function store(Request $request){

        $request->validate([
            'width' => 'nullable|decimal:0,0',
            'height' => 'nullable|decimal:0,0',
            'numerator' => 'required|decimal:0,0',
            'denominator' => 'required|decimal:0,0',
        ]);

         $user = auth()->user();
    if (!$user || !$request->user()->tokenCan('user_dashboard')) {
        return response()->json(['error' => 'Unauthorized'], 401);
    }

    $ratio=Ratio::create([
        'width'=>$request->width,
        'height'=>$request->height,
        'ratio' => "{$request->numerator}:{$request->denominator}",
        'numerator'=>$request->numerator,
        'denominator'=>$request->denominator,
        'user_id' => $user->id,
    ]);

    return response()->json(['success'=>true]);

    }

   public function update(Request $request, $id)
{
    $request->validate([
        'width' => 'nullable|decimal:0,0',
        'height' => 'nullable|decimal:0,0',
        'numerator' => 'required|decimal:0,0',
        'denominator' => 'required|decimal:0,0',
    ]);

    $user = auth()->user();
    if (!$user || !$request->user()->tokenCan('user_dashboard')) {
        return response()->json(['error' => 'Unauthorized'], 401);
    }

    $ratio = Ratio::where('id', $id)
                  ->where('user_id', $user->id)
                  ->firstOrFail();

    $ratio->update([
        'width'       => $request->width,
        'height'      => $request->height, // 🔹 fixed typo (was heigtht)
        'ratio'       => "{$request->numerator}:{$request->denominator}",
        'numerator'   => $request->numerator,
        'denominator' => $request->denominator,
    ]);

    return response()->json([
        'success' => true,
    ]);
}



    
}
