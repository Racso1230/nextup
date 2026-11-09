<?php

use App\Http\Controllers\Auth\SessionController;
use App\Http\Controllers\Auth\SteamLoginController;
use App\Http\Controllers\LibraryImportController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

if (! app()->isProduction()) {
    Route::inertia('design/tokens', 'DesignTokens')->name('design.tokens');
}

Route::middleware('guest')->group(function () {
    Route::inertia('login', 'auth/Login')->name('login');

    Route::middleware('throttle:10,1')->group(function () {
        Route::get('auth/steam', [SteamLoginController::class, 'redirect'])->name('auth.steam');
        Route::get('auth/steam/callback', [SteamLoginController::class, 'callback'])->name('auth.steam.callback');
    });
});

Route::middleware('auth')->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');
    Route::post('library/import', [LibraryImportController::class, 'store'])
        ->middleware('throttle:3,1')
        ->name('library.import');
    Route::post('logout', [SessionController::class, 'destroy'])->name('logout');
});
