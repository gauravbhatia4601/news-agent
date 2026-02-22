<?php

use App\Http\Controllers\Api\ArticleController;
use App\Http\Controllers\Api\CategoryController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::get('/categories', [CategoryController::class, 'index'])->name('categories.index');
    
    Route::get('/articles', [ArticleController::class, 'index'])->name('articles.index');
    Route::get('/articles/featured', [ArticleController::class, 'featured'])->name('articles.featured');
    Route::get('/articles/search', [ArticleController::class, 'search'])->name('articles.search');
    Route::get('/articles/popular', [ArticleController::class, 'popular'])->name('articles.popular');
    Route::get('/articles/headlines', [ArticleController::class, 'headlines'])->name('articles.headlines');
    Route::get('/articles/{slug}', [ArticleController::class, 'show'])->name('articles.show');
    Route::get('/articles/{slug}/related', [ArticleController::class, 'related'])->name('articles.related');
});
