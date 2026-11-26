<?php

use App\Http\Controllers\CatalogController;
use App\Http\Controllers\InventoryBrowseController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\ItemCostController;
use Illuminate\Support\Facades\Route;

return [
    'items' => static function (): void {
        Route::get('/inventory/item-history', [InventoryController::class, 'index']);
        Route::get('/inventory/items/{item}', [InventoryController::class, 'show'])->whereNumber('item');
        Route::get('/inventory/items/{item}/edit', [InventoryController::class, 'edit'])->whereNumber('item');
        Route::post('/inventory/items/{item}/preview', [InventoryController::class, 'preview'])->whereNumber('item');
        Route::get('/inventory/items/{item}/review/{operation}', [InventoryController::class, 'review'])->whereNumber('item');
        Route::post('/inventory/items/{item}/review/{operation}', [InventoryController::class, 'save'])->whereNumber('item');
        Route::get('/inventory/adjustments/{adjustment}', [InventoryController::class, 'adjustment'])->whereNumber('adjustment');
        Route::get('/inventory/adjustments/{adjustment}/result', [InventoryController::class, 'result'])->whereNumber('adjustment');
    },
    'itemMaintenance' => static function (): void {
        Route::get('/inventory/items/{item}/cost', [ItemCostController::class, 'edit'])->whereNumber('item');
        Route::get('/inventory/items/create', [CatalogController::class, 'itemForm']);
        Route::post('/inventory/items/create', [CatalogController::class, 'itemSave']);
        Route::get('/inventory/items/{record}/details/edit', [CatalogController::class, 'itemForm'])->whereNumber('record');
        Route::post('/inventory/items/{record}/details/edit', [CatalogController::class, 'itemSave'])->whereNumber('record');
        Route::post('/inventory/items/{record}/details/lifecycle', [CatalogController::class, 'itemLifecycle'])->whereNumber('record');
        Route::get('/inventory/items/{record}/details/archive', [CatalogController::class, 'itemArchive'])->whereNumber('record');

        // Keep the Catalog cost aliases with the other item costing routes.
        Route::get('/catalog/items/{item}/cost', [ItemCostController::class, 'edit'])->whereNumber('item');
        Route::post('/catalog/items/{item}/cost', [ItemCostController::class, 'save'])->whereNumber('item');
        Route::post('/inventory/items/{item}/cost', [ItemCostController::class, 'save'])->whereNumber('item');
        Route::get('/inventory/cost-adjustments/{entry}', [ItemCostController::class, 'show'])->whereNumber('entry');
    },
    'browse' => static function (): void {
        Route::get('/inventory', [InventoryBrowseController::class, 'index']);
        Route::post('/inventory/search', [InventoryBrowseController::class, 'submit']);
        Route::post('/inventory/load-list', [InventoryBrowseController::class, 'loadList'])->block(120, 10);
        Route::post('/inventory/preferences', [InventoryBrowseController::class, 'pending'])->block(120, 10);
        Route::get('/inventory/results/{token}', [InventoryBrowseController::class, 'results'])->block(120, 10);
        Route::get('/inventory/results/{token}/csv', [InventoryBrowseController::class, 'export']);
        Route::get('/inventory/locations/{location}', [InventoryBrowseController::class, 'location'])->whereNumber('location');
        Route::get('/inventory/classification/{kind}/{record}', [InventoryBrowseController::class, 'classification'])->whereNumber('record');
    },
];
