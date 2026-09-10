<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\Events\JobQueued;
use Illuminate\Queue\Events\JobReleasedAfterException;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(
            \App\Repositories\Contracts\ArticleRepositoryInterface::class,
            \App\Repositories\ArticleRepository::class
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('login', fn ($request) => [
            Limit::perMinute(5)->by($request->ip()),
        ]);

        RateLimiter::for('newsletter', fn ($request) => [
            Limit::perMinute(5)->by($request->ip()),
        ]);

        // A browsing user fires 15-25 calls per page view legitimately, so the
        // public-api limiter is sized to absorb a few page views per minute per IP.
        RateLimiter::for('public-api', fn ($request) => [
            Limit::perMinute(240)->by($request->ip()),
        ]);

        // Search hits an index-backed query, but typeahead still multiplies fast:
        // a dedicated, tighter limiter per client.
        RateLimiter::for('search', fn ($request) => [
            Limit::perMinute(30)->by($request->ip()),
        ]);

        RateLimiter::for('admin-actions', fn ($request) => [
            Limit::perMinute(30)->by($request->user()?->id ?: $request->ip()),
        ]);

        Event::listen(JobQueued::class, \App\Listeners\LogQueueJobs::class);
        Event::listen(JobProcessing::class, \App\Listeners\LogQueueJobs::class);
        Event::listen(JobProcessed::class, \App\Listeners\LogQueueJobs::class);
        Event::listen(JobFailed::class, \App\Listeners\LogQueueJobs::class);
        Event::listen(JobExceptionOccurred::class, \App\Listeners\LogQueueJobs::class);
        Event::listen(JobReleasedAfterException::class, \App\Listeners\LogQueueJobs::class);
    }
}
