<?php

use App\News\Services\NewsArticleGenerationService;
use App\News\Repositories\NewsTopicRepository;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Str;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('news:retry {--max-retries=3}', function (
    NewsArticleGenerationService $generationService,
    NewsTopicRepository $repository,
) {
    $maxRetries = max(1, min((int) $this->option('max-retries'), 10));

    $this->info("Retrying failed topics (max {$maxRetries} retries)...");

    $signatures = $repository->retryFailed($maxRetries);

    if ($signatures === []) {
        $this->warn('No eligible failed topics to retry.');
        return;
    }

    $this->line('Retryable topics: ' . count($signatures));

    if ((bool) config('news-engine.generation.enabled', true)) {
        $stats = $generationService->generateForDiscoveredTopics($signatures);
        $this->line('  Generated: ' . $stats['generated']);
        $this->line('  Failed: ' . $stats['failed']);
    } else {
        $this->warn('Generation disabled by config.');
    }

    $this->info('Done.');
})->purpose('Retry generation for failed topics within retry limit');

Schedule::command('news:discover --queue')->everyThirtyMinutes();
Schedule::command('news:sitemap-generate')->everyThirtyMinutes()
    ->withoutOverlapping(600)
    ->runInBackground();
Schedule::command('news:recompute-rankings')->everyFifteenMinutes()
    ->withoutOverlapping(120)
    ->runInBackground();
