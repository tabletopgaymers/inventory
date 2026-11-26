<?php

use App\Http\Controllers\InventorySearchGuideController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

if (app()->environment(['local', 'development'])) {
    $scope = app()->environment('local') ? 'local' : 'hosted';
    Route::prefix('style-guide')
        ->name('style-guide.'.$scope.'.')->middleware('auth')->group(function () use ($scope) {
            $redirectTo = static function (string $name): Closure {
                return static function (Request $request) use ($name) {
                    $target = route($name);
                    $query = (string) $request->server('QUERY_STRING', '');

                    $request->session()->reflash();

                    return redirect($target.($query === '' ? '' : '?'.$query));
                };
            };

            Route::view('/', 'style-guide.index')->name('index');
            Route::view('/presentation', 'style-guide.presentation')->name('presentation');
            Route::view('/table-sections', 'style-guide.table-sections')->name('table-sections');
            Route::view('/forms', 'style-guide.forms')->name('forms');
            Route::get('/browser-memory-demo', [\App\Http\Controllers\BrowserMemoryDemoController::class, 'index'])->name('browser-memory-demo');
            Route::get('/browser-memory-demo/data', [\App\Http\Controllers\BrowserMemoryDemoController::class, 'data'])->name('browser-memory-demo.data');
            Route::get('/inventory-search', [InventorySearchGuideController::class, 'index'])->name('inventory-search');
            Route::get('/inventory-search/results', [InventorySearchGuideController::class, 'results'])->name('inventory-search.results');
            Route::view('/tabbed-form', 'style-guide.tabbed-form')->name('tabbed-form');
            Route::view('/notifications', 'style-guide.notifications')->name('notifications');
            Route::view('/details-and-states', 'style-guide.details-and-states')->name('details-and-states');
            Route::view('/examples', 'style-guide.examples')->name('examples');
            Route::view('/vite-demo', 'style-guide.vite-demo')->name('vite-demo');
            Route::view('/workflow', 'style-guide.workflow')->name('workflow');
            Route::view('/print', 'style-guide.print')->name('print');
            if ($scope === 'local') {
                Route::view('/application-frame', 'style-guide.application-frame')->name('application-frame');
                Route::get('/wrapper', $redirectTo('style-guide.local.application-frame'));
            }

            Route::get('/tab-form', $redirectTo('style-guide.'.$scope.'.tabbed-form'));
            Route::get('/orphaned', $redirectTo('style-guide.'.$scope.'.details-and-states'));
        });
}
