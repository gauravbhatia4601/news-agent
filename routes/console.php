<?php

use App\News\Services\NewsDiscoveryService;
use App\News\Services\NewsArticleGenerationService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Str;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('news:discover {--limit=} {--fresh-hours=} {--sources-per-topic=}', function (
    NewsDiscoveryService $service,
    NewsArticleGenerationService $generationService
) {
    $categories = config('news-engine.categories', []);
    $defaultLimit = max(1, (int) config('news-engine.discovery.default_limit', 5));
    $defaultFreshHours = max(1, (int) config('news-engine.discovery.default_fresh_hours', 12));
    $defaultSourcesPerTopic = max(2, (int) config('news-engine.discovery.default_sources_per_topic', 3));

    $limit = $this->option('limit') !== null
        ? max(1, min((int) $this->option('limit'), 20))
        : $defaultLimit;
    $freshHours = $this->option('fresh-hours') !== null
        ? max(1, min((int) $this->option('fresh-hours'), 48))
        : $defaultFreshHours;
    $sourcesPerTopic = $this->option('sources-per-topic') !== null
        ? max(2, min((int) $this->option('sources-per-topic'), 6))
        : $defaultSourcesPerTopic;

    $this->info(
        "News discovery started. Limit/category: {$limit}. Sources/topic: {$sourcesPerTopic}. Fresh window: {$freshHours}h"
    );
    $this->newLine();

    try {
        $results = $service->discover($categories, $limit, $freshHours, $sourcesPerTopic);
    } catch (\Throwable $e) {
        $this->error('Discovery failed: '.$e->getMessage());

        return;
    }

    $discoveredTopicSignatures = [];

    foreach ($categories as $category) {
        $this->info("Category: {$category}");
        $topics = $results[$category] ?? [];

        if ($topics === []) {
            $this->warn('  No new multi-source topics found.');
            $this->newLine();
            continue;
        }

        foreach ($topics as $topicIndex => $topic) {
            $discoveredTopicSignatures[] = $topic->signature;
            $this->line('  '.($topicIndex + 1).'. Topic: '.$topic->name);

            foreach ($topic->sources as $sourceIndex => $source) {
                $this->line('     ['.($sourceIndex + 1).'] '.$source->sourceName.' - '.Str::limit($source->headline, 140));
                $this->line('         '.Str::limit($source->summary, 180));
                $this->line('         URL: '.$source->sourceUrl);
                $this->line('         Published: '.($source->publishedAt?->toRfc7231String() ?? 'N/A'));
            }
        }

        $this->newLine();
    }

    if ((bool) config('news-engine.generation.enabled', true)) {
        $this->info('Article generation started for newly discovered topics...');
        \Log::info($discoveredTopicSignatures);
        $generationStats = $generationService->generateForDiscoveredTopics($discoveredTopicSignatures);

        $this->line('  Generated: '.$generationStats['generated']);
        $this->line('  Failed: '.$generationStats['failed']);
        $this->newLine();
    } else {
        $this->warn('Article generation is disabled by configuration.');
        $this->newLine();
    }

    $this->info('News discovery finished.');
})->purpose('Discover and persist multi-source RSS topics with generation status tracking');

Schedule::command('news:discover')->everyThirtyMinutes();
