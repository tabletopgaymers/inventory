<?php

use App\Http\Controllers\CatalogController;
use App\Http\Controllers\InventoryBrowseController;
use App\Http\Controllers\ItemCostController;
use App\Http\Controllers\LocationCountController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    Route::get('/inventory/location-counts', [LocationCountController::class, 'index']);
    Route::post('/inventory/location-counts', [LocationCountController::class, 'search']);
    Route::get('/inventory/location-counts/results/{operation}', [LocationCountController::class, 'result'])->whereNumber('operation');
    Route::get('/inventory/location-counts/{token}/worksheet', [LocationCountController::class, 'worksheet']);
    Route::get('/inventory/location-counts/{token}/enter', [LocationCountController::class, 'enter']);
    Route::post('/inventory/location-counts/{token}/review', [LocationCountController::class, 'review']);
    Route::get('/inventory/location-counts/{token}/review', [LocationCountController::class, 'reviewed']);
    Route::post('/inventory/location-counts/{token}/save', [LocationCountController::class, 'save']);
    Route::get('/inventory/items/{item}/cost', [ItemCostController::class, 'edit'])->whereNumber('item');
    Route::post('/inventory/items/{item}/cost', [ItemCostController::class, 'save'])->whereNumber('item');
    Route::get('/inventory/cost-adjustments/{entry}', [ItemCostController::class, 'show'])->whereNumber('entry');
    Route::get('/catalog', [CatalogController::class, 'index']);
    Route::get('/catalog/{kind}', [CatalogController::class, 'index']);
    Route::get('/catalog/{kind}/create', [CatalogController::class, 'form']);
    Route::post('/catalog/{kind}/create', [CatalogController::class, 'save']);
    Route::get('/catalog/{kind}/{record}/edit', [CatalogController::class, 'form'])->whereNumber('record');
    Route::post('/catalog/{kind}/{record}/edit', [CatalogController::class, 'save'])->whereNumber('record');
    Route::get('/catalog/{kind}/{record}/archive', [CatalogController::class, 'archive'])->whereNumber('record');
    Route::post('/catalog/{kind}/{record}/lifecycle', [CatalogController::class, 'lifecycle'])->whereNumber('record');
    Route::get('/inventory', [InventoryBrowseController::class, 'index']);
    Route::post('/inventory/search', [InventoryBrowseController::class, 'submit']);
    Route::post('/inventory/preferences', [InventoryBrowseController::class, 'pending']);
    Route::get('/inventory/results/{token}', [InventoryBrowseController::class, 'results']);
    Route::get('/inventory/results/{token}/csv', [InventoryBrowseController::class, 'export']);
    Route::get('/inventory/locations/{location}', [InventoryBrowseController::class, 'location'])->whereNumber('location');
    Route::get('/inventory/classification/{kind}/{record}', [InventoryBrowseController::class, 'classification'])->whereNumber('record');
});
