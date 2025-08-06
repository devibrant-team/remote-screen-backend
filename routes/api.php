<?php

use App\Http\Controllers\Employee\AuthController;
use App\Http\Controllers\Employee\CustomController;
use App\Http\Controllers\Employee\PlanController;
use App\Http\Controllers\Employee\ScreenController;
use App\Http\Controllers\Employee\UserDataController;
use App\Http\Controllers\User\dashboard\AuthController as DashboardAuthController;
use App\Http\Controllers\User\dashboard\StylesController;
use App\Http\Controllers\User\portfolio\AuthController as PortfolioAuthController;
use App\Http\Controllers\user\portfolio\PlanUserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout']);

//plan
Route::get('/getplan', [PlanController::class, 'index']);
Route::middleware('auth:sanctum')->get('/getAllPlansWithUserPricing', [PlanController::class, 'getAllPlansWithUserPricing']);
Route::middleware('auth:sanctum')->get('/planName', [PlanController::class, 'planName']);

Route::middleware('auth:sanctum')->post('/insertplan', [PlanController::class, 'store']);
Route::middleware('auth:sanctum')->put('/updateplan/{id}', [PlanController::class, 'update']);
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
