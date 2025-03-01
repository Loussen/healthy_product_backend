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
Route::get('login', [AuthController::class, 'login'])->name('login');

Route::controller(AuthController::class)->name('auth.')->group(function(){
    Route::post('register', 'register')->name('register');
    Route::post('verify-otp', 'verifyOtp')->name('verifyOtp');
    Route::post('login', 'login')->name('login');
    Route::get('google', 'redirectToGoogle');
    Route::get('google/callback', 'handleGoogleCallback');
});

Route::middleware('auth:sanctum')->controller(AuthController::class)->group(function() {
    Route::get('validate-token', 'validateToken');

    Route::post('logout', 'logout');
});

Route::middleware('external.api')->name('main.')->controller(MainController::class)->group(function(){
    Route::get('categories', 'categories')->name('categories');
});

Route::get('/test-openai', function () {
    $image = base64_encode(file_get_contents(public_path('images/image.png')));
    $category = 'general';
    $language = 'en';

    $openai = OpenAI::client(env('OPENAI_API_KEY'));

    $response = $openai->chat()->create([
        'model' => env('OPENAI_MODEL'),
        'messages' => [
            ['role' => 'system', 'content' => "Bu bir yiyecek analiz sistemidir. Kategori: $category, Dil: $language"],
            ['role' => 'user', 'content' => [['type' => 'image', 'image' => "data:image/png;base64,$image"]]]
        ],
        'max_tokens' => 500,
    ]);

    return response()->json($response->choices[0]->message->content);


});

