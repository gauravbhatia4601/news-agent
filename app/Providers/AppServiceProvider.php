<?php

namespace App\Providers;

use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\Events\JobQueued;
use Illuminate\Queue\Events\JobReleasedAfterException;
use Illuminate\Support\Facades\Event;
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
        Event::listen(JobQueued::class, \App\Listeners\LogQueueJobs::class);
        Event::listen(JobProcessing::class, \App\Listeners\LogQueueJobs::class);
        Event::listen(JobProcessed::class, \App\Listeners\LogQueueJobs::class);
        Event::listen(JobFailed::class, \App\Listeners\LogQueueJobs::class);
        Event::listen(JobExceptionOccurred::class, \App\Listeners\LogQueueJobs::class);
        Event::listen(JobReleasedAfterException::class, \App\Listeners\LogQueueJobs::class);
    }
}
