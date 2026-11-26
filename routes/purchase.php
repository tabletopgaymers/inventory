<?php

use App\Http\Controllers\PurchaseController;
use Illuminate\Support\Facades\Route;

Route::get('/purchase', [PurchaseController::class, 'index']);
Route::get('/purchase/new', [PurchaseController::class, 'create'])->block(120, 10);
Route::get('/purchase/work/{token}', [PurchaseController::class, 'work'])->block(120, 10);
Route::post('/purchase/work/{token}', [PurchaseController::class, 'save'])->block(120, 10);
Route::get('/purchase/work/{token}/search', [PurchaseController::class, 'search'])->block(120, 10);
Route::post('/purchase/work/{token}/invoice', [PurchaseController::class, 'invoice'])->block(120, 10);
Route::post('/purchase/work/{token}/receive', [PurchaseController::class, 'receive'])->block(120, 10);
Route::post('/purchase/work/{token}/note', [PurchaseController::class, 'note'])->block(120, 10);
Route::post('/purchase/work/{token}/transition', [PurchaseController::class, 'transition'])->block(120, 10);
Route::get('/purchase/{id}', [PurchaseController::class, 'show'])->whereNumber('id')->block(120, 10);
