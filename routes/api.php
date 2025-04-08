<?php

use App\Http\Controllers\Api\MainController;
use App\Services\DebugWithTelegramService;
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
//    Route::get('google', 'redirectToGoogle');
//    Route::get('google/callback', 'handleGoogleCallback');

    Route::post('/google/sign_in', 'signInWithGoogle');
    Route::post('get-bearer-token', 'getBearerToken');
});

Route::middleware('auth:sanctum')->controller(AuthController::class)->group(function() {
    Route::get('validate-token', 'validateToken');

    Route::post('logout', 'logout');
});

Route::middleware('external.api')->name('main.')->controller(MainController::class)->group(function(){
    Route::get('categories', 'categories')->name('categories');
    Route::get('page/{page}', ['uses' => '\App\Http\Controllers\Api\MainController@getPage'])
        ->where(['page' => '^(((?=(?!admin))(?=(?!\/)).))*$']);

    Route::get('packages', 'packages')->name('packages');
    Route::get('get-languages', 'getLanguages')->name('getLanguages');
    Route::get('get-countries', 'getCountries')->name('getCountries');
});

Route::post('resend-otp', [AuthController::class,'resendOtp'])->name('resendOtp')->middleware('external.api');

Route::middleware('auth:sanctum')->name('main.')->controller(MainController::class)->group(function(){
    Route::get('customer', 'customer')->name('customer');
    Route::post('scan', 'scan')->name('scan');
    Route::post('bug-report', 'bugReport')->name('bugReport');
    Route::post('contact-us', 'contactUs')->name('contactUs');
    Route::get('get-scan-history', 'getScanHistory')->name('getScanHistory');
    Route::post('favorite-scans', 'toggleFavorite')->name('toggleFavorite');
    Route::get('get-scan-result/{scan_id}', 'getScanResult')->name('getScanResult');
    Route::post('set-default-category', 'setDefaultCategory')->name('setDefaultCategory');
    Route::post('set-default-language', 'setDefaultLanguage')->name('setDefaultLanguage');
    Route::post('set-default-country', 'setDefaultCountry')->name('setDefaultCountry');

    Route::post('change-password', 'changePassword')->name('changePassword');
});

Route::get('/test-openai', function () {
    $log = new DebugWithTelegramService();
    $log->debug('error');
    exit;
    $imagePath = public_path('images/image.png');
    if (!file_exists($imagePath)) {
        return response()->json(['error' => 'Resim bulunamadı'], 400);
    }

    $image = base64_encode(file_get_contents($imagePath));
    $category = request()->input('category', 'children');
    $language = request()->input('language', 'ru');

    $openai = OpenAI::client(env('OPENAI_API_KEY'));

    $response = $openai->chat()->create([
        'model' => env('OPENAI_MODEL'),
        'messages' => [
            [
                'role' => 'system',
                'content' => "Bu bir ürün analiz sistemidir. Resimde görülen içerikleri analiz et.
                Kullanıcının belirttiği kategoriye göre sağlık puanını dinamik olarak değiştir.
                Sonucu kesinlikle JSON formatında döndür:
                {
                  \"ingredients\": [\"Liste olarak tüm içerikler\"],
                  \"worst_ingredients\": [\"Sağlık açısından en kötü içerikler\"],
                  \"best_ingredients\": [\"Sağlık açısından en iyi içerikler\"],
                  \"health_score\": \"Yüzde olarak sağlık puanı, kategoriye göre değişebilir\",
                  \"detail_text\": \"Ürün hakkında detaylı bilgi\"
                }"
            ],
            [
                'role' => 'user',
                'content' => [
                    ['type' => 'text', 'text' => "Bu ürünün içeriklerini analiz et ve belirtilen JSON formatında cevap ver.
                    Kategori: $category, Dil: $language"],
                    ['type' => 'image_url', 'image_url' => ["url" => "data:image/png;base64,$image"]]
                ]
            ]
        ],
        'max_tokens' => 1000,
        'response_format' => ['type' => 'json_object'],
    ]);

    $data = json_decode($response->choices[0]->message->content, true);

    return response()->json($data, 200, [], JSON_UNESCAPED_UNICODE);
});

