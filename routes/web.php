<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SearchController;

Route::get('/', [SearchController::class, 'index'])->name('home');
Route::post('/search/suggestions', [SearchController::class, 'suggestions'])->name('search.suggestions');
