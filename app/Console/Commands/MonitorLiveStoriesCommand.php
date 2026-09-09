<?php

namespace App\Console\Commands;

use App\Models\Story;
use App\News\Services\MonitorLiveStoriesService;
use Illuminate\Console\Command;

class MonitorLiveStoriesCommand extends Command
{
    protected $signature = 'news:monitor-stories
                            {--story-id= : Monitor a single story by ID}
                            {--limit= : Override monitor per-run limit}';

    protected $description = 'Monitor active live stories: discover fresh updates, judge for new developments, dispatch generation.';

    public function handle(MonitorLiveStoriesService $monitorService): int
    {
        if (! (bool) config('news-engine.live_stories.enabled', true)) {
            $this->warn('Live stories disabled by config.');

            return self::SUCCESS;
        }

        $storyId = $this->option('story-id');

        if ($storyId !== null && $storyId !== '') {
            $story = Story::find((int) $storyId);
            if ($story === null) {
                $this->error("Story {$storyId} not found.");

                return self::FAILURE;
            }

            $this->line("Monitoring story: {$story->title}");
            $result = $monitorService->runCycle($story);
            $this->printSummary([['story' => $story, 'result' => $result]]);

            return self::SUCCESS;
        }

        $limit = (int) ($this->option('limit') ?: config('news-engine.live_stories.monitor_per_run_limit', 20));
        $intervalMinutes = (int) config('news-engine.live_stories.monitor_interval_minutes', 10);

        // Load active stories ordered by last_monitored_at (nulls first).
        $stories = Story::active()
            ->orderByRaw('last_monitored_at IS NULL DESC, last_monitored_at ASC')
            ->limit($limit)
            ->get();

        if ($stories->isEmpty()) {
            $this->info('No active stories to monitor.');

            return self::SUCCESS;
        }

        $results = [];
        $skipped = 0;

        foreach ($stories as $story) {
            // Skip if monitored within interval - 2 minutes.
            if ($story->last_monitored_at !== null) {
                $minInterval = max(1, $intervalMinutes - 2);
                if ($story->last_monitored_at->gt(now()->subMinutes($minInterval))) {
                    $skipped++;

                    continue;
                }
            }

            $this->line("Monitoring: {$story->title} [{$story->urgency}]");

            try {
                $result = $monitorService->runCycle($story);
                $results[] = ['story' => $story, 'result' => $result];
            } catch (\Throwable $e) {
                $this->error("  Failed: {$e->getMessage()}");
                $results[] = ['story' => $story, 'result' => ['discovered' => 0, 'judged_new' => 0, 'dispatched' => 0, 'concluded' => false]];
            }
        }

        if ($skipped > 0) {
            $this->line("Skipped {$skipped} stories (monitored too recently).");
        }

        $this->printSummary($results);

        return self::SUCCESS;
    }

    /**
     * @param  array<int, array{story: Story, result: array{discovered: int, judged_new: int, dispatched: int, concluded: bool}}>  $results
     */
    private function printSummary(array $results): void
    {
        if ($results === []) {
            return;
        }

        $this->newLine();
        $this->info('Monitor cycle summary:');

        $rows = array_map(fn ($r) => [
            $r['story']->title,
            $r['story']->urgency,
            $r['result']['discovered'],
            $r['result']['judged_new'],
            $r['result']['dispatched'],
            $r['result']['concluded'] ? 'yes' : 'no',
        ], $results);

        $this->table(['Story', 'Urgency', 'Discovered', 'New', 'Dispatched', 'Concluded'], $rows);
    }
}
