<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\InventorySearchGuideController;

if (app()->environment(['local', 'development'])) {
    $scope = app()->environment('local') ? 'local' : 'hosted';
    Route::prefix('style-guide')
        ->name('style-guide.'.$scope.'.')->middleware('auth')->group(function () use ($scope) {
            Route::view('/', 'style-guide.index')->name('index');
            Route::view('/presentation', 'style-guide.presentation')->name('presentation');
            Route::view('/table-sections', 'style-guide.table-sections')->name('table-sections');
            Route::view('/forms', 'style-guide.forms')->name('forms');
            Route::get('/browser-memory-demo', [\App\Http\Controllers\BrowserMemoryDemoController::class, 'index'])->name('browser-memory-demo');
            Route::get('/browser-memory-demo/data', [\App\Http\Controllers\BrowserMemoryDemoController::class, 'data'])->name('browser-memory-demo.data');
            Route::get('/inventory-search', [InventorySearchGuideController::class, 'index'])->name('inventory-search');
            Route::get('/inventory-search/results', [InventorySearchGuideController::class, 'results'])->name('inventory-search.results');
            Route::view('/tab-form', 'style-guide.tabbed-form')->name('tabbed-form');
            Route::view('/notifications', 'style-guide.notifications')->name('notifications');
            Route::view('/orphaned', 'style-guide.details-and-states')->name('details-and-states');
            Route::view('/examples', 'style-guide.examples')->name('examples');
            Route::view('/vite-demo', 'style-guide.vite-demo')->name('vite-demo');
            Route::view('/workflow', 'style-guide.workflow')->name('workflow');
            Route::view('/print', 'style-guide.print')->name('print');
            if ($scope === 'local') {
                Route::view('/wrapper', 'style-guide.wrapper')->name('wrapper');
            }
        });
}
