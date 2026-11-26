<?php

use App\Http\Controllers\ReconcileController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::prefix('reconcile')->name('reconcile.')->group(function () {
    Route::get('/', [ReconcileController::class, 'index'])->name('index');
    Route::post('/', [ReconcileController::class, 'search'])->name('search');
    Route::post('/load-list', [ReconcileController::class, 'loadList'])->name('load-list');
    Route::post('/{token}/list-location', [ReconcileController::class, 'listLocation'])->name('list-location');
    Route::get('/results/{operation}', [ReconcileController::class, 'result'])->whereNumber('operation')->name('result');
    Route::get('/{token}/worksheet', [ReconcileController::class, 'worksheet'])->name('worksheet');
    Route::get('/{token}/enter', [ReconcileController::class, 'enter'])->name('enter');
    Route::post('/{token}/review', [ReconcileController::class, 'review'])->name('review.submit');
    Route::get('/{token}/review', [ReconcileController::class, 'reviewed'])->name('review');
    Route::post('/{token}/save', [ReconcileController::class, 'save'])->name('save');
});

Route::prefix('inventory/location-counts')->group(function () {
    $redirectTo = static function (string $name): Closure {
        return static function (Request $request) use ($name) {
            $target = route($name, $request->route()->parameters());
            $query = (string) $request->server('QUERY_STRING', '');

            // An older form may redirect back here with errors and input to display.
            $request->session()->reflash();

            return redirect($target.($query === '' ? '' : '?'.$query));
        };
    };

    Route::get('/', $redirectTo('reconcile.index'));
    Route::post('/', [ReconcileController::class, 'search']);
    Route::post('/load-list', [ReconcileController::class, 'loadList']);
    Route::post('/{token}/list-location', [ReconcileController::class, 'listLocation']);
    Route::get('/results/{operation}', $redirectTo('reconcile.result'))->whereNumber('operation');
    Route::get('/{token}/worksheet', $redirectTo('reconcile.worksheet'));
    Route::get('/{token}/enter', $redirectTo('reconcile.enter'));
    Route::post('/{token}/review', [ReconcileController::class, 'review']);
    Route::get('/{token}/review', $redirectTo('reconcile.review'));
    Route::post('/{token}/save', [ReconcileController::class, 'save']);
});
