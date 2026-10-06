<?php

use App\Http\Controllers\MicrosoftAuthController;
use App\Http\Controllers\PlannedPageController;
use App\Http\Controllers\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/baseline', function (Request $request) {
    return view('welcome', $request->attributes->get('baseline'));
});

Route::view('/login', 'login')->name('login');
Route::get('/auth/microsoft/redirect', [MicrosoftAuthController::class, 'redirect']);
Route::get('/auth/microsoft/callback', [MicrosoftAuthController::class, 'callback']);
Route::post('/logout', [MicrosoftAuthController::class, 'logout']);
Route::post('/auth/microsoft/logout', [MicrosoftAuthController::class, 'microsoftLogout']);
Route::get('/signed-out', function (Request $request) {
    return view('signed-out', [
        'siteLogout' => $request->session()->get('site_logout', false),
        'microsoftLogout' => $request->session()->pull('microsoft_logout_requested', false),
    ]);
});
Route::middleware('auth')->group(function () {
    Route::view('/', 'home');
    foreach (['catalog', 'inventory', 'purchases', 'relocations', 'events'] as $page) {
        Route::get('/'.$page, [PlannedPageController::class, 'show'])->defaults('page', $page);
    }
    foreach (['item-history', 'location-counts'] as $page) {
        Route::get('/inventory/'.$page, [PlannedPageController::class, 'show'])->defaults('page', $page);
    }
    Route::view('/profile', 'profile');
    Route::post('/profile', [UserController::class, 'saveProfile']);
    Route::get('/users', [UserController::class, 'directory']);
    Route::post('/users/{user}/roles', [UserController::class, 'role']);
    Route::post('/users/{user}/disable', [UserController::class, 'disable']);
});
