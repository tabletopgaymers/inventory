<?php

use App\Http\Controllers\InventoryController;
use App\Http\Controllers\MicrosoftAuthController;
use App\Http\Controllers\PlannedPageController;
use App\Http\Controllers\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

if (app()->environment(['local', 'development']) && is_file(__DIR__.'/design-phase-a.php')) {
    require __DIR__.'/design-phase-a.php';
}

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
    foreach (['purchases', 'relocations', 'events'] as $page) {
        Route::get('/'.$page, [PlannedPageController::class, 'show'])->defaults('page', $page);
    }
    foreach (['location-counts'] as $page) {
        Route::get('/inventory/'.$page, [PlannedPageController::class, 'show'])->defaults('page', $page);
    }
    Route::get('/inventory/item-history', [InventoryController::class, 'index']);
    Route::get('/inventory/items/{item}', [InventoryController::class, 'show'])->whereNumber('item');
    Route::get('/inventory/items/{item}/edit', [InventoryController::class, 'edit'])->whereNumber('item');
    Route::post('/inventory/items/{item}/preview', [InventoryController::class, 'preview'])->whereNumber('item');
    Route::get('/inventory/items/{item}/review/{operation}', [InventoryController::class, 'review'])->whereNumber('item');
    Route::post('/inventory/items/{item}/review/{operation}', [InventoryController::class, 'save'])->whereNumber('item');
    Route::get('/inventory/adjustments/{adjustment}', [InventoryController::class, 'adjustment'])->whereNumber('adjustment');
    Route::get('/inventory/adjustments/{adjustment}/result', [InventoryController::class, 'result'])->whereNumber('adjustment');
    Route::view('/profile', 'profile');
    Route::post('/profile', [UserController::class, 'saveProfile']);
    Route::get('/users', [UserController::class, 'directory']);
    Route::post('/users/{user}/roles', [UserController::class, 'role']);
    Route::post('/users/{user}/disable', [UserController::class, 'disable']);
});

require __DIR__.'/phase-five.php';
