<?php

use App\Http\Controllers\CatalogController;
use App\Http\Controllers\InventoryBrowseController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    Route::get('/catalog', [CatalogController::class, 'index']);
    Route::get('/catalog/{kind}', [CatalogController::class, 'index']);
    Route::get('/catalog/{kind}/create', [CatalogController::class, 'form']);
    Route::post('/catalog/{kind}/create', [CatalogController::class, 'save']);
    Route::get('/catalog/{kind}/{record}/edit', [CatalogController::class, 'form'])->whereNumber('record');
    Route::post('/catalog/{kind}/{record}/edit', [CatalogController::class, 'save'])->whereNumber('record');
    Route::post('/catalog/{kind}/{record}/lifecycle', [CatalogController::class, 'lifecycle'])->whereNumber('record');
    Route::get('/inventory', [InventoryBrowseController::class, 'index']);
    Route::post('/inventory/search', [InventoryBrowseController::class, 'submit']);
    Route::post('/inventory/preferences', [InventoryBrowseController::class, 'pending']);
    Route::get('/inventory/results/{token}', [InventoryBrowseController::class, 'results']);
    Route::get('/inventory/results/{token}/csv', [InventoryBrowseController::class, 'export']);
    Route::get('/inventory/locations/{location}', [InventoryBrowseController::class, 'location'])->whereNumber('location');
    Route::get('/inventory/classification/{kind}/{record}', [InventoryBrowseController::class, 'classification'])->whereNumber('record');
});
