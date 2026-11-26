<?php

use App\Http\Controllers\MicrosoftAuthController;
use App\Http\Controllers\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

if (app()->environment(['local', 'development']) && is_file(__DIR__.'/style-guide.php')) {
    require __DIR__.'/style-guide.php';
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

// Inventory registers in sections to preserve its order around Accounts and Catalog.
$inventoryRoutes = require __DIR__.'/inventory.php';

Route::middleware('auth')->group(function () use ($inventoryRoutes) {
    Route::view('/', 'home');

    require __DIR__.'/purchase.php';
    require __DIR__.'/relocation.php';
    $inventoryRoutes['items']();

    Route::get('/profile', [UserController::class, 'profile']);
    Route::post('/profile', [UserController::class, 'saveProfile']);
    Route::get('/users', [UserController::class, 'directory']);
    Route::get('/users/{user}', [UserController::class, 'show'])->whereNumber('user');
    Route::post('/users/{user}/roles', [UserController::class, 'role']);
    Route::post('/users/{user}/disable', [UserController::class, 'disable']);
    Route::post('/users/{user}/enable', [UserController::class, 'enable']);
});

Route::middleware('auth')->group(function () use ($inventoryRoutes) {
    require __DIR__.'/reconcile.php';
    $inventoryRoutes['itemMaintenance']();
    require __DIR__.'/catalog.php';
    $inventoryRoutes['browse']();
});

require __DIR__.'/events.php';

// Unknown page links follow the same sign-in flow before displaying the safe 404.
Route::fallback(fn () => abort(404))->middleware('auth');
