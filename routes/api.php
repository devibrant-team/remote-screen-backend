<?php

use App\Http\Controllers\Employee\AuthController;
use App\Http\Controllers\Employee\PlanController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login']);
Route::middleware('auth:sanctum')->post('/logout', [AuthController::class, 'logout']);

//plan

Route::get('/getplan', [PlanController::class, 'index']);
Route::middleware('auth:sanctum')->post('/insertplan', [PlanController::class, 'store']);