<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\InventorySearchGuideController;

if (app()->environment(['local', 'development'])) {
    $scope = app()->environment('local') ? 'local' : 'hosted';
    Route::prefix('style-guide')
        ->name('design.phase-a.'.$scope.'.')->middleware('auth')->group(function () use ($scope) {
            Route::view('/', 'design.phase-a.index')->name('index');
            Route::view('/presentation', 'design.phase-a.presentation')->name('presentation');
            Route::view('/table-sections', 'design.phase-a.table-sections')->name('table-sections');
            Route::view('/forms', 'design.phase-a.forms')->name('forms');
            Route::get('/inventory-search', [InventorySearchGuideController::class, 'index'])->name('inventory-search');
            Route::get('/inventory-search/results', [InventorySearchGuideController::class, 'results'])->name('inventory-search.results');
            Route::view('/tab-form', 'design.phase-a.tab-form')->name('tab-form');
            Route::view('/notifications', 'design.phase-a.notifications')->name('notifications');
            Route::view('/orphaned', 'design.phase-a.orphaned')->name('orphaned');
            Route::view('/examples', 'design.phase-a.examples')->name('examples');
            if ($scope === 'local') {
                Route::view('/wrapper', 'design.phase-a.wrapper')->name('wrapper');
            }
        });
}
