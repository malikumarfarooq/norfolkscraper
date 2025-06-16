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
    Route::get('/fetch/progress/{batchId}', [ParcelFetchController::class, 'checkProgress'])->name('parcels.fetch.progress');
    Route::get('/fetch/errors/{batchId}', [ParcelFetchController::class, 'getBatchErrors'])->name('parcels.fetch.errors');

    Route::get('/export-csv', [ParcelFetchController::class, 'exportCsv'])->name('export.csv');
    Route::get('/export-by-sale-groups', [ParcelFetchController::class, 'exportBySaleGroups'])
        ->name('parcels.export.by-sale-groups');
});
//Route::get('/complaints/{gpin}', [PropertyDetailsController::class, 'complaints']);
