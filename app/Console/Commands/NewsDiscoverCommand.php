<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\News\Services\NewsArticleGenerationService;
use App\News\Services\NewsDiscoveryService;
use Illuminate\Console\Command;

class NewsDiscoverCommand extends Command
{
    protected $signature = 'news:discover
                            {--limit= : Topics per category (1-20)}
                            {--fresh-hours= : Freshness window in hours (1-48)}
                            {--sources-per-topic= : Max sources per topic (2-6)}
                            {--queue : Dispatch article generation as queue jobs}';

    protected $description = 'Discover and optionally generate news articles';

    public function handle(
        NewsDiscoveryService $service,
        NewsArticleGenerationService $generationService
    ): int {
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
        $useQueue = $this->option('queue');

        $india = Category::where('slug', 'india')->first();

        if (! $india) {
            $this->warn('India parent category not found.');
            return self::FAILURE;
        }

        $stateCategories = $india->children()
            ->orderBy('display_order')
            ->get(['id', 'slug', 'name']);

        $locations = $stateCategories->map(fn (Category $cat) => [
            'slug' => $cat->slug,
            'name' => $cat->name,
            'category_id' => $cat->id,
        ])->all();

        if ($locations === []) {
            $this->warn('No state subcategories found under India.');
            return self::FAILURE;
        }

        $this->info("Discovery: {$limit} topics/state, {$sourcesPerTopic} sources/topic, {$freshHours}h window, over " . count($locations) . " states/UTs.");
        $this->newLine();

        try {
            $topics = $service->discover($locations, $limit, $freshHours, $sourcesPerTopic);
        } catch (\Throwable $e) {
            $this->error('Discovery failed: ' . $e->getMessage());
            return self::FAILURE;
        }

        $signatures = [];

        foreach ($topics as $topic) {
            $signatures[] = $topic->signature;
            $locationName = $stateCategories->firstWhere('id', $topic->locationCategoryId)?->name ?? 'Unknown';
            $this->line("  [{$locationName}] [{$topic->category}] {$topic->name}");
        }

        $this->newLine();
        $this->line("Discovered: " . count($signatures) . " topics");

        if (count($signatures) === 0) {
            $this->warn('No new multi-source topics found.');
            return self::SUCCESS;
        }

        if (! (bool) config('news-engine.generation.enabled', true)) {
            $this->warn('Generation disabled by config.');
            return self::SUCCESS;
        }

        if ($useQueue) {
            $this->info('Dispatching generation jobs to queue...');
            foreach ($signatures as $sig) {
                \App\Jobs\GenerateArticle::dispatch($sig);
            }
            $this->line('  Dispatched: ' . count($signatures) . ' jobs');
        } else {
            $this->info('Generating articles inline...');
            $stats = $generationService->generateForDiscoveredTopics($signatures);
            $this->line('  Generated: ' . $stats['generated']);
            $this->line('  Failed: ' . $stats['failed']);
        }

        $this->info('Done.');

        return self::SUCCESS;
    }
}
