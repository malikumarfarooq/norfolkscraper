<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\PropertyDetailsController;

// Home-search page
Route::get('/', [SearchController::class, 'index'])->name('home');
Route::post('/suggestions', [SearchController::class, 'suggestions'])->name('suggestions');

// Property-details page
Route::get('/property-details/{id}', [PropertyDetailsController::class, 'show'])->name('property.details');

Route::get('/property-details/{id}/export', [PropertyDetailsController::class, 'export'])
    ->name('property.export');
