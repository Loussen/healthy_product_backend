<?php

use App\Http\Controllers\Front\MainController;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Route;

$locales = implode('|', array_keys(config('services.locales')));

Route::group([
    'prefix' => '{locale}',
    'where' => ['locale' => $locales],
    'middleware' => 'locale'
], function () {
    Route::get('/', [MainController::class, 'dashboard'])->name('home');
    Route::get('/{slug}', [MainController::class, 'page'])->name('page');
});

// Root locale'siz çağrı için de_DE:
Route::get('/', [MainController::class, 'dashboard']);

