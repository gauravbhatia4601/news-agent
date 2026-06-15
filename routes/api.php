<?php

use App\Http\Controllers\Api\Admin\ArticleController as AdminArticleController;
use App\Http\Controllers\Api\Admin\AuditLogController;
use App\Http\Controllers\Api\Admin\AuthController as AdminAuthController;
use App\Http\Controllers\Api\Admin\CategoryController as AdminCategoryController;
use App\Http\Controllers\Api\Admin\DashboardController;
use App\Http\Controllers\Api\Admin\DiscoveryController;
use App\Http\Controllers\Api\Admin\GenerationController;
use App\Http\Controllers\Api\Admin\TopicController as AdminTopicController;
use App\Http\Controllers\Api\Admin\UserController;
use App\Http\Controllers\Api\ArticleController;
use App\Http\Controllers\Api\CategoryController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::get('/categories', [CategoryController::class, 'index'])->name('categories.index');

    Route::get('/articles', [ArticleController::class, 'index'])->name('articles.index');
    Route::get('/articles/featured', [ArticleController::class, 'featured'])->name('articles.featured');
    Route::get('/articles/search', [ArticleController::class, 'search'])->name('articles.search');
    Route::get('/articles/popular', [ArticleController::class, 'popular'])->name('articles.popular');
    Route::get('/articles/hot', [ArticleController::class, 'hot'])->name('articles.hot');
    Route::get('/articles/trending', [ArticleController::class, 'trending'])->name('articles.trending');
    Route::get('/articles/headlines', [ArticleController::class, 'headlines'])->name('articles.headlines');
    Route::get('/articles/{slug}', [ArticleController::class, 'show'])->name('articles.show');
    Route::get('/articles/{slug}/related', [ArticleController::class, 'related'])->name('articles.related');
});

Route::prefix('v1/admin')->group(function () {
    Route::post('/auth/login', [AdminAuthController::class, 'login']);

    Route::middleware(['auth:sanctum', 'admin'])->group(function () {
        Route::post('/auth/logout', [AdminAuthController::class, 'logout']);
        Route::get('/auth/me', [AdminAuthController::class, 'me']);

        Route::get('/dashboard', DashboardController::class);

        Route::apiResource('articles', AdminArticleController::class)->except(['store']);
        Route::post('/articles/batch', [AdminArticleController::class, 'batch']);
        Route::post('/articles/{id}/regenerate', [AdminArticleController::class, 'regenerate']);

        Route::apiResource('topics', AdminTopicController::class)->except(['store', 'update']);
        Route::post('/topics/batch', [AdminTopicController::class, 'batch']);
        Route::post('/topics/{id}/retry', [AdminTopicController::class, 'retry']);
        Route::post('/topics/{id}/dispatch', [AdminTopicController::class, 'dispatch']);

        Route::apiResource('categories', AdminCategoryController::class);
        Route::post('/categories/reorder', [AdminCategoryController::class, 'reorder']);

        Route::post('/discovery/trigger', [DiscoveryController::class, 'trigger']);
        Route::post('/discovery/retry-failed', [DiscoveryController::class, 'retryFailed']);

        Route::get('/generation/queue', [GenerationController::class, 'queueStatus']);
        Route::post('/generation/sitemap', [GenerationController::class, 'regenerateSitemap']);
        Route::get('/generation/stats', [GenerationController::class, 'stats']);

        Route::get('/users/roles', [UserController::class, 'roles']);
        Route::apiResource('users', UserController::class);

        Route::get('/audit', [AuditLogController::class, 'index']);
    });
});