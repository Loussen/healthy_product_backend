<?php

use App\Http\Controllers\Api\MainController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

//Route::post('register', [AuthController::class, 'register']);
//Route::post('login', [AuthController::class, 'login']);

Route::controller(AuthController::class)->name('auth.')->group(function(){
    Route::post('register', 'register')->name('register');
    Route::post('verify-otp', 'verifyOtp')->name('verifyOtp');
    Route::post('login', 'login')->name('login');
});

Route::middleware('auth:sanctum')->prefix('v1')->controller(AuthController::class)->group(function() {
    Route::post('logout', 'logout');
});

Route::middleware('external.api')->prefix('v1')->controller(MainController::class)->group(function(){

});

