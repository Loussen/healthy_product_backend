<?php

use App\Http\Controllers\Api\MainController;
use App\Http\Controllers\Api\TelegramBotController;
use App\Services\DebugWithTelegramService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use Google\Cloud\Vision\V1\Client\ImageAnnotatorClient;

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
    Route::post('forget-password', 'forgetPassword');
    Route::post('reset-password', 'resetPassword');
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
    Route::get('check-app-version', 'checkAppVersion')->name('checkAppVersion');
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

    Route::post('subscriptions/verify', 'verifySubscription')->name('verifySubscription');

    Route::post('scan-new', 'scanNew')->name('scanNew');

    Route::post('store-device-token','storeDeviceToken')->name('storeDeviceToken');

    Route::get('get-push-notifications','getPushNotifications')->name('getPushNotifications');
    Route::get('get-push-notification/{notification_id}','getPushNotification')->name('getPushNotification');
    Route::post('update-status-push-notification','updateStatusPushNotification')->name('updateStatusPushNotification');
    Route::get('get-unread-notification-count','getUnreadNotificationCount')->name('getUnreadNotificationCount');

    Route::post('purchase/product', 'verifyPurchase')->name('verifyPurchase');

    Route::get('get-order-history', 'getOrderHistory')->name('getOrderHistory');
});

Route::post('google/subscriptions/webhook', [MainController::class, 'webhookGoogleSubscription'])->name('webhookGoogleSubscription');
Route::get('check-sub', [MainController::class, 'checkPayment'])->name('checkPayment');

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

Route::get('/vision-test', function () {
    try {
        // Google Cloud kimlik doğrulama
        putenv('GOOGLE_APPLICATION_CREDENTIALS=' . storage_path('app/vital-scan-vscan-f84aa6680e43.json'));

        // Client oluştur
        $imageAnnotator = new \Google\Cloud\Vision\V1\Client\ImageAnnotatorClient();

        // Resim hazırla
        $imageUrl = 'https://contrademn.com/wp-content/uploads/2022/12/eng_pl_Alpen-Gold-Milk-Chocolate-Strawberry-90-g-18598_2-scaled-400x400.jpg';
        $imageContent = file_get_contents($imageUrl);

        // Image nesnesini oluştur
        $image = new \Google\Cloud\Vision\V1\Image();
        $image->setContent($imageContent);

        // Feature nesnesi oluştur - TEXT_DETECTION özelliğini belirt
        $feature = new \Google\Cloud\Vision\V1\Feature();
        $feature->setType(\Google\Cloud\Vision\V1\Feature\Type::TEXT_DETECTION);

        // AnnotateImageRequest oluştur
        $request = new \Google\Cloud\Vision\V1\AnnotateImageRequest();
        $request->setImage($image);
        $request->setFeatures([$feature]);

        // BatchAnnotateImagesRequest oluştur
        $batchRequest = new \Google\Cloud\Vision\V1\BatchAnnotateImagesRequest();
        $batchRequest->setRequests([$request]);

        // İsteği gönder
        $response = $imageAnnotator->batchAnnotateImages($batchRequest);

        // Yanıtı işle
        $annotations = $response->getResponses()[0];
        $textAnnotations = $annotations->getTextAnnotations();

        $imageAnnotator->close();

        // Sonucu göster
        if (count($textAnnotations) > 0) {
            $text = $textAnnotations[0]->getDescription();
            return "Bulunan metin: $text";
        } else {
            return "Metin bulunamadı";
        }

    } catch (\Exception $e) {
        return "Hata: " . $e->getMessage();
    }
});

Route::name('telegram.')->prefix('telegram')->controller(TelegramBotController::class)->group(function(){
    Route::get('test', 'test')->name('test');
    Route::get('webhook', 'handleWebhook')->name('webhook');
});
