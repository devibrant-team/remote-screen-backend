<?php

use App\Http\Controllers\Employee\AuthController;
use App\Http\Controllers\Employee\CustomController;
use App\Http\Controllers\Employee\PlanController;
use App\Http\Controllers\Employee\ScreenController;
use App\Http\Controllers\Employee\UserDataController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout']);

//plan

Route::get('/getplan', [PlanController::class, 'index']);
Route::middleware('auth:sanctum')->post('/insertplan', [PlanController::class, 'store']);

// custom
Route::get('/getcustom', [CustomController::class, 'index']);

// user 
Route::middleware('auth:sanctum')->get('/getusersearch', [UserDataController::class, 'search']);
Route::middleware('auth:sanctum')->get('/usersplan', [UserDataController::class, 'getusersplan']);


Route::middleware('auth:sanctum')->get('/userscreen/{id}', [ScreenController::class, 'userScreens']);

