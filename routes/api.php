<?php

use App\Http\Controllers\Employee\AdsController;
use App\Http\Controllers\Employee\AuthController;
use App\Http\Controllers\Employee\CustomController;
use App\Http\Controllers\Employee\PlanController;
use App\Http\Controllers\Employee\ScreenController;
use App\Http\Controllers\User\dashboard\MediaController;
use App\Http\Controllers\Employee\UserDataController;
use App\Http\Controllers\User\dashboard\AuthController as DashboardAuthController;
use App\Http\Controllers\User\dashboard\BranchController;
use App\Http\Controllers\User\dashboard\GroupsController;
use App\Http\Controllers\User\dashboard\ScheduleController;
use App\Http\Controllers\User\dashboard\PlayListController;
use App\Http\Controllers\User\dashboard\NormalPlayListController;
use App\Http\Controllers\User\dashboard\RatioController;
use App\Http\Controllers\User\dashboard\ScreenManagmentController;
use App\Http\Controllers\User\dashboard\StylesController;
use App\Http\Controllers\User\portfolio\AuthController as PortfolioAuthController;
use App\Http\Controllers\user\portfolio\PlanUserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout']);

//plan
Route::get('/getplan', [PlanController::class, 'index']);
Route::middleware('auth:sanctum')->get('/getAllPlansWithUserPricing', [PlanController::class, 'getAllPlansWithUserPricing']);
Route::middleware('auth:sanctum')->get('/planName', [PlanController::class, 'planName']);

Route::middleware('auth:sanctum')->post('/insertplan', [PlanController::class, 'store']);
Route::middleware('auth:sanctum')->put('/updateplan/{id}', [PlanController::class, 'update']);


// ads
Route::get('/getads',[AdsController::class,'index']);
Route::middleware('auth:sanctum')->post('/insertads',[AdsController::class,'store']);


// custom
Route::get('/getcustom', [CustomController::class, 'index']);
Route::middleware('auth:sanctum')->put('/updatecustom/{id}', [CustomController::class, 'update']);

// overview
Route::middleware('auth:sanctum')->get('/planoverview', [PlanController::class, 'planwithuser']);
Route::middleware('auth:sanctum')->post('/incomeoverview', [UserDataController::class, 'getPlanOverview']);
Route::middleware('auth:sanctum')->get('/overview', [UserDataController::class, 'index']);
// user
Route::middleware('auth:sanctum')->get('/getusersearch', [UserDataController::class, 'search']);
Route::middleware('auth:sanctum')->get('/usersplan', [UserDataController::class, 'getusersplan']);
Route::middleware('auth:sanctum')->get('/userscreen/{id}', [ScreenController::class, 'userScreens']);


//screen
Route::middleware('auth:sanctum')->get('/screenstatic', [ScreenController::class, 'screenStatic']);
Route::middleware('auth:sanctum')->get('/screenStatus', [ScreenController::class, 'screenStatus']);


Route::post('/screens/{id}/online', function ($id) {
    DB::table('screens')->where('id', $id)->update(['is_active' => 1]);
    return response()->json(['status' => 'online']);
});

Route::post('/screens/{id}/offline', function ($id) {
    DB::table('screens')->where('id', $id)->update(['is_active' => 0]);
    return response()->json(['status' => 'offline']);
});

//portofolio
// user login & signup  portofolio
Route::post('/portofolio/signup', [PortfolioAuthController::class, 'signup']);
Route::post('/portofolio/login', [PortfolioAuthController::class, 'login']);


// plan purchase

Route::middleware('auth:sanctum')->get('/plan_parchase', [PlanUserController::class, 'store']);



// dashboard login
Route::post('/dashboard/login', [DashboardAuthController::class, 'login']);


// getplayList Style

Route::get('/getplaylistStyle', [StylesController::class, 'getPlayListStyle']); //should auth

// grid Style
Route::get('/getgridStyle', [StylesController::class, 'getGridStyle']); //should auth


Route::middleware('auth:sanctum')->get('/getuser/playlistnormal', [NormalPlayListController::class, 'getNormal']); //should auth

Route::middleware('auth:sanctum')->get('/getuser/playlistinteractive', [PlayListController::class, 'getInteractive']); //should auth


// playlist item 
Route::middleware('auth:sanctum')->post('/postinteractive', [PlayListController::class, 'storeInteractive']);

Route::middleware('auth:sanctum')->post('/postNormal', [NormalPlayListController::class, 'storeNormal']);
Route::middleware('auth:sanctum')->post('/updatenormal/{playlistId}', [NormalPlayListController::class, 'updateNormal']);
Route::middleware('auth:sanctum')->post('/updateinteravtive/{playlistId}', [PlayListController::class, 'updateInteractive']);
Route::middleware('auth:sanctum')->get('/getuser/media', [MediaController::class, 'getMedia']); //should auth
Route::middleware('auth:sanctum')->post('/postmedia', [MediaController::class, 'store']); 
Route::middleware('auth:sanctum')->delete('/deletemedia/{id}', [MediaController::class, 'destroy']); 
Route::get('/getscale', [PlayListController::class, 'getscale']); //should auth

Route::middleware('auth:sanctum')->get('/playlistdetails/{id}', [PlaylistController::class, 'show']);
Route::middleware('auth:sanctum')->delete('/deleteplaylist/{id}', [PlaylistController::class, 'destroy']);
// ratio 
Route::post('/create/screen', [ScreenManagmentController::class, 'createScreen'])
    ->middleware('throttle:100,20');
Route::middleware('auth:sanctum')->post('/adduser/screen',[ScreenManagmentController::class,'addScreen']);
Route::middleware('auth:sanctum')->get('/getsinglescreens',[ScreenManagmentController::class,'getusersinglescreens']);


// branch
Route::middleware('auth:sanctum')->post('/insertbranch', [BranchController::class, 'store']);
Route::middleware('auth:sanctum')->get('/getbranch', [BranchController::class, 'index']);
Route::middleware('auth:sanctum')->put('/getbranch', [BranchController::class, 'update']);


// ratio
Route::middleware('auth:sanctum')->get('/getratio', [RatioController::class, 'getRatio']);
Route::middleware('auth:sanctum')->post('/insertratio', [RatioController::class, 'store']);
Route::middleware('auth:sanctum')->put('/updateratio', [RatioController::class, 'update']);

// group
Route::middleware('auth:sanctum')->get('/getgroups', [GroupsController::class, 'index']);
Route::middleware('auth:sanctum')->post('/insertgroup', [GroupsController::class, 'store']);
Route::middleware('auth:sanctum')->put('/updategroup/{id}', [GroupsController::class, 'update']);
Route::middleware('auth:sanctum')->get('/getscreensgroup/{id}', [GroupsController::class, 'getScreensGroup']);

//schedule 



Route::get('/qr-download', [GroupsController::class, 'publicDownload'])
     ->middleware('auth:sanctum');


Route::get('/ping', fn () => 'pong');

Route::middleware('auth:sanctum')->post('/postschedule', [ScheduleController::class, 'store']);


Route::post('/broadcasting/auth', function (Request $request) {
    return response()->json([
        'auth' => $request->input('channel_name') . ':' . uniqid(),
    ]);
});

Route::get('/download/{filename}', function ($filename) {
    $path = 'media/user-a-2/interactive/asdasd/' . $filename;

    if (!Storage::disk('public')->exists($path)) {
        abort(404);
    }

    return response()->download(Storage::disk('public')->path($path));
});


Route::post('/postimage', [MediaController::class, 'imageupload']); 




Route::middleware('auth:sanctum')->get('/dashboard/schedules', [ScheduleController::class, 'index']);




