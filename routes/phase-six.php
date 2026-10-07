<?php

use App\Http\Controllers\FulfillmentController;
use App\Http\Controllers\PurchaseRequestController;
use App\Http\Controllers\RelocationRequestController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    foreach (['purchase' => 'purchases', 'relocation' => 'relocations'] as $kind => $path) {
        Route::get('/'.$path.'/{id}/fulfillment/{mode}', [FulfillmentController::class, 'edit'])->defaults('kind', $kind)->whereNumber('id')->block(120, 10);
        Route::post('/'.$path.'/{id}/fulfillment/{mode}', [FulfillmentController::class, 'update'])->defaults('kind', $kind)->whereNumber('id')->block(120, 10);
        Route::get('/'.$path.'/{id}/fulfillment/{mode}/review/{token}', [FulfillmentController::class, 'review'])->defaults('kind', $kind)->whereNumber('id')->block(120, 10);
        Route::post('/'.$path.'/{id}/fulfillment/{mode}/confirm/{token}', [FulfillmentController::class, 'confirm'])->defaults('kind', $kind)->whereNumber('id')->block(120, 10);
    }
    Route::post('/relocations/{id}/tracking', [FulfillmentController::class, 'tracking'])->whereNumber('id')->block(120, 10);
    Route::post('/purchases/{id}/fulfillment-transition', [FulfillmentController::class, 'purchaseTransition'])->whereNumber('id')->block(120, 10);
    Route::get('/purchases', [PurchaseRequestController::class, 'index'])->block(120, 10);
    Route::get('/purchases/new', [PurchaseRequestController::class, 'create'])->block(120, 10);
    Route::post('/purchases', [PurchaseRequestController::class, 'store'])->block(120, 10);
    Route::get('/purchases/{id}', [PurchaseRequestController::class, 'show'])->whereNumber('id')->block(120, 10);
    Route::get('/purchases/{id}/edit', [PurchaseRequestController::class, 'edit'])->whereNumber('id')->block(120, 10);
    Route::post('/purchases/{id}', [PurchaseRequestController::class, 'save'])->whereNumber('id')->block(120, 10);
    Route::post('/purchases/{id}/notes', [PurchaseRequestController::class, 'note'])->whereNumber('id')->block(120, 10);
    Route::post('/purchases/{id}/transition', [PurchaseRequestController::class, 'transition'])->whereNumber('id')->block(120, 10);
    Route::get('/purchases/{id}/preparation', [PurchaseRequestController::class, 'preparation'])->whereNumber('id')->block(120, 10);
    Route::post('/purchases/{id}/preparation', [PurchaseRequestController::class, 'prepare'])->whereNumber('id')->block(120, 10);
    Route::get('/relocations', [RelocationRequestController::class, 'index'])->block(120, 10);
    Route::get('/relocations/new', [RelocationRequestController::class, 'create'])->block(120, 10);
    Route::get('/relocations/work/{token}', [RelocationRequestController::class, 'work'])->block(120, 10);
    Route::post('/relocations/work/{token}', [RelocationRequestController::class, 'updateWork'])->block(120, 10);
    Route::get('/relocations/work/{token}/review', [RelocationRequestController::class, 'review'])->block(120, 10);
    Route::post('/relocations/work/{token}/submit', [RelocationRequestController::class, 'submit'])->block(120, 10);
    Route::get('/relocations/{id}', [RelocationRequestController::class, 'show'])->whereNumber('id')->block(120, 10);
    Route::get('/relocations/{id}/edit', [RelocationRequestController::class, 'edit'])->whereNumber('id')->block(120, 10);
    Route::post('/relocations/{id}/copy', [RelocationRequestController::class, 'copy'])->whereNumber('id')->block(120, 10);
    Route::post('/relocations/{id}/transition', [RelocationRequestController::class, 'transition'])->whereNumber('id')->block(120, 10);
    Route::post('/relocations/{id}/title', [RelocationRequestController::class, 'title'])->whereNumber('id')->block(120, 10);
    Route::post('/relocations/{id}/fulfillment', [RelocationRequestController::class, 'fulfillment'])->whereNumber('id')->block(120, 10);
    Route::get('/relocations/{id}/worksheet', [RelocationRequestController::class, 'worksheet'])->whereNumber('id')->block(120, 10);
});
