<?php

use Illuminate\Support\Facades\Route;

if (app()->environment(['local', 'development'])) {
    $scope = app()->environment('local') ? 'local' : 'hosted';
    Route::prefix('design/phase-a')
        ->name('design.phase-a.'.$scope.'.')->middleware('auth')->group(function () use ($scope) {
            Route::view('/overview', 'design.phase-a.index')->name('index');
            Route::redirect('/elements', '/design/phase-a/presentation')->name('elements');
            Route::redirect('/tables', '/design/phase-a/presentation#table-panel')->name('tables');
            Route::view('/presentation', 'design.phase-a.presentation')->name('presentation');
            Route::view('/forms', 'design.phase-a.forms')->name('forms');
            Route::view('/notifications', 'design.phase-a.notifications')->name('notifications');
            Route::view('/orphaned', 'design.phase-a.orphaned')->name('orphaned');
            Route::view('/examples', 'design.phase-a.examples')->name('examples');
            if ($scope === 'local') {
                Route::view('/wrapper', 'design.phase-a.wrapper')->name('wrapper');
            }
        });
}
