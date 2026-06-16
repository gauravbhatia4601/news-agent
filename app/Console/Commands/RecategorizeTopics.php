<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\NewsTopic;
use App\News\Services\TopicCategoryDetectionService;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class RecategorizeTopics extends Command
{
    protected $signature = 'news:recategorize-topics
                            {--topic-id= : Recategorize a single topic by ID}
                            {--dry-run : Show proposed changes without saving}';

    protected $description = 'Re-run topic category detection on existing topics and update category_id.';

    public function handle(TopicCategoryDetectionService $detector): int
    {
        $singleId = $this->option('topic-id');
        $dryRun = $this->option('dry-run');

        $query = NewsTopic::query();
        if ($singleId) {
            $query->where('id', $singleId);
        }

        $total = $query->clone()->count();
        $this->info("Topics to process: {$total}");

        $changed = 0;
        $unchanged = 0;

        $query->chunkById(100, function ($topics) use ($detector, $dryRun, &$changed, &$unchanged) {
            foreach ($topics as $topic) {
                $sources = $topic->sources->map(fn ($s) => [
                    'headline' => $s->headline,
                    'summary' => $s->summary,
                ])->all();

                $detected = $detector->detect($topic->topic_name, $sources);
                $newCategoryId = $detected['id'];
                $newCategoryName = $detected['name'];

                $oldCategory = $topic->categoryRelation;
                $oldName = $oldCategory?->name ?? 'Uncategorized';

                if ($newCategoryId === null) {
                    $this->line("  [SKIP] {$topic->id}: no category detected (was {$oldName})");
                    $unchanged++;
                    continue;
                }

                if ((int) $topic->category_id === (int) $newCategoryId) {
                    $unchanged++;
                    continue;
                }

                $this->line("  [CHANGE] {$topic->id}: {$oldName} → {$newCategoryName} | {$topic->topic_name}");

                if (! $dryRun) {
                    $topic->update([
                        'category_id' => $newCategoryId,
                        'category' => $newCategoryName,
                    ]);
                }

                $changed++;
            }
        });

        $this->info("Done. Changed: {$changed}, Unchanged: {$unchanged}");

        if ($dryRun) {
            $this->warn('Dry run: no changes saved.');
        }

        return self::SUCCESS;
    }
}
