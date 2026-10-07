<?php

use App\Http\Controllers\EventController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    Route::get('/events', [EventController::class, 'index'])->block(120, 10);
    Route::get('/events/new', [EventController::class, 'form'])->block(120, 10);
    Route::post('/events/new', [EventController::class, 'update'])->block(120, 10);
    Route::get('/events/{id}', [EventController::class, 'show'])->whereNumber('id')->block(120, 10);
    Route::get('/events/{id}/report', [EventController::class, 'report'])->whereNumber('id')->block(120, 10);
    Route::get('/events/{id}/work/{mode}', [EventController::class, 'form'])->whereNumber('id')->block(120, 10);
    Route::post('/events/{id}/work/{mode}', [EventController::class, 'update'])->whereNumber('id')->block(120, 10);
    Route::get('/events/{id}/work/{mode}/review/{token}', [EventController::class, 'review'])->whereNumber('id')->block(120, 10);
    Route::post('/events/{id}/work/{mode}/confirm/{token}', [EventController::class, 'confirm'])->whereNumber('id')->block(120, 10);
});
