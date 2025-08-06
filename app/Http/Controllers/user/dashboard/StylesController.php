<?php

namespace App\Http\Controllers\User\dashboard;

use App\Http\Controllers\Controller;
use App\Models\PlaylistStyle;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StylesController extends Controller {

    public function getPlayListStyle( Request $request ) {

        // $user = auth()->user();

        //  if ( !$user || !$request->user()->tokenCan( 'user_dashboard' ) ) {
        //         return response()->json( [ 'error' => 'Unauthorized' ], 401 );
        //     }

        $playListStyle = PlaylistStyle::all();
        return response()->json( [ 'playListStyle' => $playListStyle ] );
    }

    public function getInteractive() {
        $interactive = DB::table( 'interactives' )
        ->whereIn( 'type', [ 'interactive_1', 'interactive_2' ] )
        ->get();

        return response()->json( [
            'interactive' => $interactive
        ] );
    }
}
