<?php

use Illuminate\Support\Facades\Route;

if (app()->environment(['local', 'development'])) {
    $scope = app()->environment('local') ? 'local' : 'hosted';
    Route::prefix('design/phase-a')
        ->name('design.phase-a.'.$scope.'.')->middleware('auth')->group(function () use ($scope) {
            Route::view('/overview', 'design.phase-a.index')->name('index');
            Route::view('/elements', 'design.phase-a.elements')->name('elements');
            Route::view('/examples', 'design.phase-a.examples')->name('examples');
            if ($scope === 'local') {
                Route::view('/wrapper', 'design.phase-a.wrapper')->name('wrapper');
            }
        });
}
