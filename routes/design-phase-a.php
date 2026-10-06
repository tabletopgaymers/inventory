<?php

use Illuminate\Support\Facades\Route;

if (app()->environment(['local', 'development'])) {
    foreach (['local' => 'tg-inventory-app.test', 'hosted' => 'dev-inventory.tabletopgaymers.org'] as $scope => $host) {
        Route::domain($host)->prefix('design/phase-a')
            ->name('design.phase-a.'.$scope.'.')->middleware('auth')->group(function () {
                Route::view('/overview', 'design.phase-a.index')->name('index');
                Route::view('/elements', 'design.phase-a.elements')->name('elements');
                Route::view('/examples', 'design.phase-a.examples')->name('examples');
            });
    }
}
