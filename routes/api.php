<?php

use App\Http\Controllers\Api\ContentController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\ScanController;
use App\Http\Controllers\Api\SubscriptionController;
use App\Http\Controllers\Api\TelegramBotController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;

Route::get('login', fn() => response()->json(['success' => false, 'message' => 'Unauthenticated'], 401))->name('login');

// Auth (public)
Route::controller(AuthController::class)->name('auth.')->group(function () {
    Route::post('register', 'register')->name('register');
    Route::post('verify-otp', 'verifyOtp')->name('verifyOtp');
    Route::post('login', 'login')->name('login');
    Route::post('/google/sign_in', 'signInWithGoogle');
    Route::post('/apple/sign_in', 'signInWithApple');
    Route::post('forget-password', 'forgetPassword');
    Route::post('reset-password', 'resetPassword');
});

Route::post('resend-otp', [AuthController::class, 'resendOtp'])->name('resendOtp')->middleware('external.api');

// Auth (authenticated)
Route::middleware('auth:sanctum')->controller(AuthController::class)->group(function () {
    Route::get('validate-token', 'validateToken');
    Route::post('logout', 'logout');
});

// Content (public, external API token)
Route::middleware('external.api')->name('content.')->controller(ContentController::class)->group(function () {
    Route::get('categories', 'categories')->name('categories');
    Route::get('page/{page}', 'getPage')
        ->where(['page' => '^(((?=(?!admin))(?=(?!\/)).))*$'])
        ->name('getPage');
    Route::get('packages', 'packages')->name('packages');
    Route::get('get-languages', 'getLanguages')->name('getLanguages');
    Route::get('get-countries', 'getCountries')->name('getCountries');
    Route::get('check-app-version', 'checkAppVersion')->name('checkAppVersion');
});

// Authenticated routes
Route::middleware('auth:sanctum')->group(function () {

    // Customer
    Route::name('customer.')->controller(CustomerController::class)->group(function () {
        Route::get('customer', 'customer')->name('profile');
        Route::post('change-password', 'changePassword')->name('changePassword');
        Route::delete('delete-account', 'deleteAccount')->name('deleteAccount');
        Route::post('set-default-category', 'setDefaultCategory')->name('setDefaultCategory');
        Route::post('set-default-language', 'setDefaultLanguage')->name('setDefaultLanguage');
        Route::post('set-default-country', 'setDefaultCountry')->name('setDefaultCountry');
        Route::post('store-device-token', 'storeDeviceToken')->name('storeDeviceToken');
        Route::post('bug-report', 'bugReport')->name('bugReport');
        Route::post('contact-us', 'contactUs')->name('contactUs');
    });

    // Scan
    Route::name('scan.')->controller(ScanController::class)->group(function () {
        Route::get('scan-test', 'scanTest')->name('test');
        Route::post('scan', 'scan')->name('scan');
        Route::post('analyze-product', 'scan')->name('analyzeProduct');
        Route::get('get-scan-history', 'getScanHistory')->name('history');
        Route::get('get-scan-result/{scan_id}', 'getScanResult')->name('result');
        Route::post('favorite-scans', 'toggleFavorite')->name('toggleFavorite');
    });

    // Subscription & Payments
    Route::name('subscription.')->controller(SubscriptionController::class)->group(function () {
        Route::post('subscriptions/verify', 'verifySubscription')->name('verify');
        Route::post('purchase/product', 'verifyPurchase')->name('purchase');
        Route::get('get-order-history', 'getOrderHistory')->name('orderHistory');
    });

    // Notifications
    Route::name('notification.')->controller(NotificationController::class)->group(function () {
        Route::get('get-push-notifications', 'getPushNotifications')->name('list');
        Route::get('get-push-notification/{notification_id}', 'getPushNotification')->name('show');
        Route::post('update-status-push-notification', 'updateStatusPushNotification')->name('updateStatus');
        Route::get('get-unread-notification-count', 'getUnreadNotificationCount')->name('unreadCount');
    });
});

// Webhooks (no auth)
Route::post('google/subscriptions/webhook', [SubscriptionController::class, 'webhookGoogleSubscription'])->name('webhookGoogleSubscription');

// Telegram
Route::name('telegram.')->prefix('telegram')->controller(TelegramBotController::class)->group(function () {
    Route::post('webhook', 'handleWebhook')->name('webhook');
    Route::get('test', 'test')->name('test');
});
