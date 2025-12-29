<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DashboardController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

Route::get('/', function () {
    return redirect()->route('dashboard');
});

Route::middleware(['auth:sanctum', 'verified'])->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
});

// API Routes for POS
Route::middleware(['auth:sanctum'])->prefix('api')->group(function () {
    Route::get('/products/search', [\App\Http\Controllers\Api\ProductApiController::class, 'search']);
    Route::get('/products/low-stock', [\App\Http\Controllers\Api\ProductApiController::class, 'lowStock']);
    Route::apiResource('products', \App\Http\Controllers\Api\ProductApiController::class);

    Route::apiResource('sales', \App\Http\Controllers\Api\SaleApiController::class)->except(['update', 'destroy']);
    Route::post('/sales/{sale}/cancel', [\App\Http\Controllers\Api\SaleApiController::class, 'cancel']);
    Route::get('/sales/today', [\App\Http\Controllers\Api\SaleApiController::class, 'today']);

    Route::apiResource('purchases', \App\Http\Controllers\Api\PurchaseApiController::class);

    Route::get('/dashboard/stats', [\App\Http\Controllers\Api\ReportApiController::class, 'stats']);
    Route::get('/dashboard/chart', [\App\Http\Controllers\Api\ReportApiController::class, 'chart']);
});
