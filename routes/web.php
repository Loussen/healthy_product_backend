<?php

use App\Http\Controllers\Front\BlogController;
use App\Http\Controllers\Front\MainController;
use Illuminate\Support\Facades\Route;

$locales = implode('|', array_keys(config('services.locales')));

Route::group([
    'prefix' => '{locale}',
    'where' => ['locale' => $locales],
    'middleware' => 'locale'
], function () {
    Route::get('/', [MainController::class, 'dashboard'])->name('home');
    Route::get('/blog', [BlogController::class, 'index'])->name('blog.index');
    Route::get('/blog/{slug}', [BlogController::class, 'show'])->name('blog.show');
    Route::get('/{slug}', [MainController::class, 'page'])->name('page');
});

// Root locale'siz çağrı için de_DE:
Route::get('/', [MainController::class, 'dashboard']);

