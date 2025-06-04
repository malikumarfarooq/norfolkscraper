<?php

use App\Http\Controllers\ParcelFetchController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\PropertyDetailsController;
use App\Http\Controllers\SaleHistoryController;

// Home-search page
Route::get('/', [SearchController::class, 'index'])->name('home');
Route::post('/suggestions', [SearchController::class, 'suggestions'])->name('suggestions');

// Property-details page
Route::get('/property-details/{id}', [PropertyDetailsController::class, 'show'])->name('property.details');

Route::get('/property-details/{id}/export', [PropertyDetailsController::class, 'export'])
    ->name('property.export');

Route::get('/sales-history/{id}', [SaleHistoryController::class, 'show'])->name('sale.history');
Route::get('/sales-history/{id}/export', [SaleHistoryController::class, 'export'])->name('sales-history.export');

Route::get('/sale-history/{id}/export-zero', [SaleHistoryController::class, 'exportZeroSales'])
    ->name('sale.history.export.zero');

Route::prefix('parcels')->group(function () {
    Route::get('/fetch', [ParcelFetchController::class, 'index'])->name('parcels.fetch');
    Route::post('/fetch/start', [ParcelFetchController::class, 'startFetching'])->name('parcels.fetch.start');
    Route::post('/fetch/stop', [ParcelFetchController::class, 'stopFetching'])->name('parcels.fetch.stop');
    Route::get('/fetch/progress', [ParcelFetchController::class, 'getProgress'])->name('parcels.fetch.progress');


    Route::get('/parcels/export-by-sale-groups', [ParcelFetchController::class, 'exportBySaleGroups'])
        ->name('parcels.export.by-sale-groups');

    Route::get('/export-csv', [ParcelFetchController::class, 'exportCsv'])->name('export.csv');
    Route::get('/complaints/{gpin}', [PropertyDetailsController::class, 'complaints']);
});


