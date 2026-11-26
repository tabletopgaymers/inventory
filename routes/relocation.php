<?php

use App\Http\Controllers\RelocationController;
use Illuminate\Support\Facades\Route;

Route::get('/relocation', [RelocationController::class, 'index']);
Route::get('/relocation/new', [RelocationController::class, 'create'])->block(120, 10);
Route::get('/relocation/work/{token}', [RelocationController::class, 'work'])->block(120, 10);
Route::get('/relocation/work/{token}/print', [RelocationController::class, 'print'])->block(120, 10);
Route::post('/relocation/work/{token}', [RelocationController::class, 'save'])->block(120, 10);
Route::get('/relocation/work/{token}/search', [RelocationController::class, 'search'])->block(120, 10);
Route::get('/relocation/work/{token}/balances', [RelocationController::class, 'balances'])->block(120, 10);
Route::post('/relocation/work/{token}/accept', [RelocationController::class, 'accept'])->block(120, 10);
Route::post('/relocation/work/{token}/fulfillment', [RelocationController::class, 'saveFulfillment'])->block(120, 10);
Route::post('/relocation/work/{token}/basic', [RelocationController::class, 'saveAcceptedBasic'])->block(120, 10);
Route::post('/relocation/work/{token}/tracking', [RelocationController::class, 'saveTracking'])->block(120, 10);
Route::post('/relocation/work/{token}/ship', [RelocationController::class, 'ship'])->block(120, 10);
Route::post('/relocation/work/{token}/receive', [RelocationController::class, 'receive'])->block(120, 10);
Route::post('/relocation/work/{token}/reconciliation', [RelocationController::class, 'saveReconciliation'])->block(120, 10);
Route::post('/relocation/work/{token}/complete', [RelocationController::class, 'complete'])->block(120, 10);
Route::get('/relocation/{id}', [RelocationController::class, 'show'])->whereNumber('id')->block(120, 10);
