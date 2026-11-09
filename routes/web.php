<?php

use App\Http\Controllers\Auth\SessionController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

if (! app()->isProduction()) {
    Route::inertia('design/tokens', 'DesignTokens')->name('design.tokens');
}

Route::middleware('guest')->group(function () {
    Route::inertia('login', 'auth/Login')->name('login');
});

Route::middleware('auth')->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');
    Route::post('logout', [SessionController::class, 'destroy'])->name('logout');
});
