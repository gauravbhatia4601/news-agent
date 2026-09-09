<?php

use App\Http\Controllers\Api\Admin\AdminStoryController;
use App\Http\Controllers\Api\Admin\AiInvocationController;
use App\Http\Controllers\Api\Admin\ArticleController as AdminArticleController;
use App\Http\Controllers\Api\Admin\AuditLogController;
use App\Http\Controllers\Api\Admin\AuthController as AdminAuthController;
use App\Http\Controllers\Api\Admin\CategoryController as AdminCategoryController;
use App\Http\Controllers\Api\Admin\DashboardController;
use App\Http\Controllers\Api\Admin\DiscoveryController;
use App\Http\Controllers\Api\Admin\GenerationController;
use App\Http\Controllers\Api\Admin\NewsletterSubscriberAdminController;
use App\Http\Controllers\Api\Admin\SettingsController;
use App\Http\Controllers\Api\Admin\TopicController as AdminTopicController;
use App\Http\Controllers\Api\Admin\UserController;
use App\Http\Controllers\Api\ArticleController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\NewsletterSubscriberController;
use App\Http\Controllers\Api\StoryController;
use App\Services\MarketDataService;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware('throttle:public-api')->group(function () {
    Route::get('/categories', [CategoryController::class, 'index'])->name('categories.index');

    Route::get('/articles', [ArticleController::class, 'index'])->name('articles.index');
    Route::get('/articles/featured', [ArticleController::class, 'featured'])->name('articles.featured');
    Route::get('/articles/search', [ArticleController::class, 'search'])->name('articles.search')->middleware('throttle:search');
    Route::get('/articles/popular', [ArticleController::class, 'popular'])->name('articles.popular');
    Route::get('/articles/hot', [ArticleController::class, 'hot'])->name('articles.hot');
    Route::get('/articles/trending', [ArticleController::class, 'trending'])->name('articles.trending');
    Route::get('/articles/headlines', [ArticleController::class, 'headlines'])->name('articles.headlines');
    Route::get('/articles/{slug}', [ArticleController::class, 'show'])->name('articles.show');
    Route::get('/articles/{slug}/related', [ArticleController::class, 'related'])->name('articles.related');

    Route::get('/stories', [StoryController::class, 'index'])->name('stories.index');
    Route::get('/stories/{slug}', [StoryController::class, 'show'])->name('stories.show');
    Route::get('/stories/{slug}/timeline', [StoryController::class, 'timeline'])->name('stories.timeline');

    Route::post('/newsletter/subscribe', [NewsletterSubscriberController::class, 'subscribe'])->middleware('throttle:newsletter');
    Route::post('/newsletter/unsubscribe', [NewsletterSubscriberController::class, 'unsubscribe'])->middleware('throttle:newsletter');

    Route::get('/market', function () {
        return response()->json(['data' => app(MarketDataService::class)->getMarketData()]);
    });
});

Route::prefix('v1/admin')->group(function () {
    Route::post('/auth/login', [AdminAuthController::class, 'login'])->middleware('throttle:login');

    Route::middleware(['auth:sanctum', 'admin'])->group(function () {
        Route::post('/auth/logout', [AdminAuthController::class, 'logout']);
        Route::get('/auth/me', [AdminAuthController::class, 'me']);

        Route::get('/dashboard', DashboardController::class);

        Route::apiResource('articles', AdminArticleController::class)->except(['store'])->names([
            'index' => 'admin.articles.index',
            'show' => 'admin.articles.show',
            'update' => 'admin.articles.update',
            'destroy' => 'admin.articles.destroy',
        ]);
        Route::post('/articles/batch', [AdminArticleController::class, 'batch'])->name('admin.articles.batch')->middleware('throttle:admin-actions');
        Route::post('/articles/{id}/regenerate', [AdminArticleController::class, 'regenerate'])->name('admin.articles.regenerate')->middleware('throttle:admin-actions');

        Route::apiResource('topics', AdminTopicController::class)->except(['store', 'update'])->names([
            'index' => 'admin.topics.index',
            'show' => 'admin.topics.show',
            'destroy' => 'admin.topics.destroy',
        ]);
        Route::post('/topics/batch', [AdminTopicController::class, 'batch'])->name('admin.topics.batch')->middleware('throttle:admin-actions');
        Route::post('/topics/{id}/retry', [AdminTopicController::class, 'retry'])->name('admin.topics.retry')->middleware('throttle:admin-actions');
        Route::post('/topics/{id}/dispatch', [AdminTopicController::class, 'dispatch'])->name('admin.topics.dispatch')->middleware('throttle:admin-actions');

        Route::apiResource('stories', AdminStoryController::class)->names([
            'index' => 'admin.stories.index',
            'store' => 'admin.stories.store',
            'show' => 'admin.stories.show',
            'update' => 'admin.stories.update',
            'destroy' => 'admin.stories.destroy',
        ]);
        Route::post('/stories/batch', [AdminStoryController::class, 'batch'])->name('admin.stories.batch')->middleware('throttle:admin-actions');
        Route::post('/stories/{id}/activate', [AdminStoryController::class, 'activate'])->name('admin.stories.activate')->middleware('throttle:admin-actions');
        Route::post('/stories/{id}/conclude', [AdminStoryController::class, 'conclude'])->name('admin.stories.conclude')->middleware('throttle:admin-actions');
        Route::post('/stories/{id}/trigger-monitor', [AdminStoryController::class, 'triggerMonitor'])->name('admin.stories.trigger-monitor')->middleware('throttle:admin-actions');

        Route::apiResource('categories', AdminCategoryController::class)->names([
            'index' => 'admin.categories.index',
            'store' => 'admin.categories.store',
            'show' => 'admin.categories.show',
            'update' => 'admin.categories.update',
            'destroy' => 'admin.categories.destroy',
        ]);
        Route::post('/categories/reorder', [AdminCategoryController::class, 'reorder'])->name('admin.categories.reorder');

        Route::post('/discovery/trigger', [DiscoveryController::class, 'trigger'])->middleware('throttle:admin-actions');
        Route::post('/discovery/retry-failed', [DiscoveryController::class, 'retryFailed'])->middleware('throttle:admin-actions');

        Route::get('/generation/queue', [GenerationController::class, 'queueStatus']);
        Route::get('/generation/queue-history', [GenerationController::class, 'queueHistory']);
        Route::post('/generation/sitemap', [GenerationController::class, 'regenerateSitemap'])->middleware('throttle:admin-actions');
        Route::get('/generation/sitemaps', [GenerationController::class, 'listSitemaps']);
        Route::get('/generation/sitemaps/{name}', [GenerationController::class, 'showSitemap']);
        Route::get('/generation/stats', [GenerationController::class, 'stats']);

        Route::apiResource('users', UserController::class)->names([
            'index' => 'admin.users.index',
            'store' => 'admin.users.store',
            'show' => 'admin.users.show',
            'update' => 'admin.users.update',
            'destroy' => 'admin.users.destroy',
        ]);

        Route::get('/audit', [AuditLogController::class, 'index']);

        Route::get('/ai-invocations', [AiInvocationController::class, 'index']);

        Route::get('/health', function (): \Illuminate\Http\JsonResponse {
            $checks = [];
            $allOk = true;

            try {
                \DB::select('SELECT 1');
                $checks['database'] = 'ok';
            } catch (\Throwable $e) {
                \Log::warning('Health check: database error.', ['error' => $e->getMessage()]);
                $checks['database'] = 'unavailable';
                if (config('app.debug')) {
                    $checks['database_detail'] = $e->getMessage();
                }
                $allOk = false;
            }

            try {
                \Cache::store('redis')->put('health-check', 1, 10);
                \Cache::store('redis')->get('health-check');
                $checks['redis'] = 'ok';
            } catch (\Throwable $e) {
                \Log::warning('Health check: redis error.', ['error' => $e->getMessage()]);
                $checks['redis'] = 'unavailable';
                if (config('app.debug')) {
                    $checks['redis_detail'] = $e->getMessage();
                }
                $allOk = false;
            }

            $queueSize = \Queue::size(config('queue.default', 'default'));
            $checks['queue_size'] = $queueSize;
            $checks['queue'] = $queueSize > 500 ? 'backlogged' : 'ok';

            $heartbeat = \Cache::get('news-engine:worker-heartbeat');
            $checks['worker_heartbeat'] = $heartbeat ? 'ok' : 'no heartbeat';

            return response()->json([
                'status' => $allOk ? 'healthy' : 'degraded',
                'checks' => $checks,
                'timestamp' => now()->toIso8601String(),
            ], $allOk ? 200 : 503);
        });

        Route::get('/settings', [SettingsController::class, 'index']);
        Route::put('/settings', [SettingsController::class, 'update'])->middleware('throttle:admin-actions');

        Route::get('/newsletter/subscribers', [NewsletterSubscriberAdminController::class, 'index']);
        Route::delete('/newsletter/subscribers/{id}', [NewsletterSubscriberAdminController::class, 'destroy']);
    });
});
