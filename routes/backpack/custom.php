<?php

use Illuminate\Support\Facades\Route;

// --------------------------
// Custom Backpack Routes
// --------------------------
// This route file is loaded automatically by Backpack\CRUD.
// Routes you generate using Backpack\Generators will be placed here.

Route::group([
    'prefix' => config('backpack.base.route_prefix', 'admin'),
    'middleware' => array_merge(
        (array) config('backpack.base.web_middleware', 'web'),
        (array) config('backpack.base.middleware_key', 'admin')
    ),
    'namespace' => 'App\Http\Controllers\Admin',
], function () { // custom admin routes
    Route::crud('user', 'UserCrudController');
    Route::crud('customers', 'CustomersCrudController');
    Route::crud('otp', 'OtpCrudController');
    Route::crud('products', 'ProductsCrudController');
    Route::crud('categories', 'CategoriesCrudController');
    Route::crud('scan-results', 'ScanResultsCrudController');
    Route::crud('page', 'PageCrudController');
    Route::crud('bug-reports', 'BugReportsCrudController');
    Route::crud('contact-us', 'ContactUsCrudController');
    Route::crud('packages', 'PackagesCrudController');
    Route::crud('customer-packages', 'CustomerPackagesCrudController');
    Route::crud('customer-favorites', 'CustomerFavoritesCrudController');
    Route::crud('countries', 'CountriesCrudController');
    Route::crud('subscription', 'SubscriptionCrudController');
}); // this should be the absolute last line of this file

/**
 * DO NOT ADD ANYTHING HERE.
 */
