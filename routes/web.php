<?php

use App\Http\Controllers\InventoryController;
use App\Http\Controllers\MicrosoftAuthController;
use App\Http\Controllers\PlannedPageController;
use App\Http\Controllers\PurchaseController;
use App\Http\Controllers\RelocationController;
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
    Route::get('/purchase', [PurchaseController::class, 'index']);
    Route::get('/purchase/new', [PurchaseController::class, 'create'])->block(120, 10);
    Route::get('/purchase/work/{token}', [PurchaseController::class, 'work'])->block(120, 10);
    Route::post('/purchase/work/{token}', [PurchaseController::class, 'save'])->block(120, 10);
    Route::post('/purchase/work/{token}/invoice', [PurchaseController::class, 'invoice'])->block(120, 10);
    Route::post('/purchase/work/{token}/note', [PurchaseController::class, 'note'])->block(120, 10);
    Route::post('/purchase/work/{token}/transition', [PurchaseController::class, 'transition'])->block(120, 10);
    Route::get('/purchase/{id}', [PurchaseController::class, 'show'])->whereNumber('id')->block(120, 10);
    Route::get('/relocation', [RelocationController::class, 'index']);
    Route::get('/relocation/new', [RelocationController::class, 'create'])->block(120, 10);
    Route::get('/relocation/work/{token}', [RelocationController::class, 'work'])->block(120, 10);
    Route::get('/relocation/work/{token}/print', [RelocationController::class, 'print'])->block(120, 10);
    Route::post('/relocation/work/{token}', [RelocationController::class, 'save'])->block(120, 10);
    Route::get('/relocation/work/{token}/search', [RelocationController::class, 'search'])->block(120, 10);
    Route::get('/relocation/work/{token}/balances', [RelocationController::class, 'balances'])->block(120, 10);
    Route::post('/relocation/work/{token}/accept', [RelocationController::class, 'accept'])->block(120, 10);
    Route::post('/relocation/work/{token}/fulfillment', [RelocationController::class, 'saveFulfillment'])->block(120, 10);
    Route::post('/relocation/work/{token}/basic', [RelocationController::class, 'saveAcceptedBasic'])->block(120, 10);
    Route::post('/relocation/work/{token}/tracking', [RelocationController::class, 'saveTracking'])->block(120, 10);
    Route::post('/relocation/work/{token}/ship', [RelocationController::class, 'ship'])->block(120, 10);
    Route::post('/relocation/work/{token}/receive', [RelocationController::class, 'receive'])->block(120, 10);
    Route::post('/relocation/work/{token}/reconciliation', [RelocationController::class, 'saveReconciliation'])->block(120, 10);
    Route::post('/relocation/work/{token}/complete', [RelocationController::class, 'complete'])->block(120, 10);
    Route::get('/relocation/{id}', [RelocationController::class, 'show'])->whereNumber('id')->block(120, 10);
    foreach (['purchases', 'relocations'] as $page) {
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
    Route::get('/profile', [UserController::class, 'profile']);
    Route::post('/profile', [UserController::class, 'saveProfile']);
    Route::get('/users', [UserController::class, 'directory']);
    Route::get('/users/{user}', [UserController::class, 'show'])->whereNumber('user');
    Route::post('/users/{user}/roles', [UserController::class, 'role']);
    Route::post('/users/{user}/disable', [UserController::class, 'disable']);
    Route::post('/users/{user}/enable', [UserController::class, 'enable']);
});

require __DIR__.'/phase-five.php';

require __DIR__.'/phase-six.php';

require __DIR__.'/phase-eight.php';

// Unknown page links follow the same sign-in flow before displaying the safe 404.
Route::fallback(fn () => abort(404))->middleware('auth');
