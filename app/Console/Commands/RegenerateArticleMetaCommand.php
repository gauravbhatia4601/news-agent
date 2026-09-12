<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\News\Support\MetaClamp;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RegenerateArticleMetaCommand extends Command
{
    protected $signature = 'news:regenerate-article-meta
                            {--apply : Write changes to DB (default is dry-run)}
                            {--chunk=200 : Number of articles per batch}';

    protected $description = 'Regenerate meta_title and meta_description for all published articles, clamped to SEO limits. Processes in memory-safe batches.';

    private const META_TITLE_MAX = 60;

    private const META_DESC_MIN = 120;

    private const META_DESC_MAX = 155;

    private const BATCH_SIZE = 200;

    // Stats buckets
    private int $titleFixed = 0;

    private int $descFixed = 0;

    private int $bothFixed = 0;

    private int $collisionsResolved = 0;

    private int $total = 0;

    /** @var array<int, array{slug: string, field: string, before: string, after: string}> */
    private array $samples = [];

    /** @var array<string, true> Descriptions already assigned (collision detection). */
    private array $usedDescriptions = [];

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $chunkSize = max(50, (int) $this->option('chunk'));

        $this->info($apply ? 'APPLY mode — writing changes to DB.' : 'DRY-RUN mode — no DB writes. Use --apply to write.');
        $this->info("Batch size: {$chunkSize}");

        // Never accumulate the query log across ~18k rows.
        DB::disableQueryLog();

        $query = DB::table('news_articles')
            ->where('status', 'published')
            ->select('id', 'slug', 'title', 'content', 'meta_title', 'meta_description')
            ->orderBy('id');

        $query->chunkById($chunkSize, function ($articles) use ($apply): void {
            foreach ($articles as $article) {
                $this->processArticle($article, $apply);
            }

            // Free the chunk's model/row memory immediately; the collision set
            // is kept intentionally (~155 bytes per description).
            unset($articles);
            gc_collect_cycles();
        }, 'id');

        $this->report();

        return self::SUCCESS;
    }

    private function processArticle(object $article, bool $apply): void
    {
        $originalTitle = (string) ($article->meta_title ?? '');
        $originalDesc = (string) ($article->meta_description ?? '');

        $newTitle = MetaClamp::clampTitle((string) $article->title);

        // Build description from content first sentence, clamped to 120-155 range.
        $firstSentence = MetaClamp::extractFirstSentence((string) $article->content);
        $newDesc = MetaClamp::clamp($firstSentence, self::META_DESC_MAX);

        // If clamped description is too short (< 120), fall back to title-based description.
        if (mb_strlen($newDesc) < self::META_DESC_MIN) {
            $titleBased = $article->title.'. Read the full story on The Neural Journal for in-depth analysis and coverage.';
            $newDesc = MetaClamp::clamp($titleBased, self::META_DESC_MAX);
        }

        // Collision check: if another article already has this exact description, use the title instead.
        if (isset($this->usedDescriptions[$newDesc])) {
            $this->collisionsResolved++;
            $newDesc = MetaClamp::clamp(
                $article->title.'. The Neural Journal covers this story with detailed reporting and analysis.',
                self::META_DESC_MAX,
            );

            if (count($this->samples) < 5) {
                $this->samples[] = ['slug' => $article->slug, 'field' => 'collision', 'before' => mb_substr($originalDesc, 0, 80), 'after' => mb_substr($newDesc, 0, 80)];
            }
        }

        $this->usedDescriptions[$newDesc] = true;

        $titleChanged = $newTitle !== $originalTitle;
        $descChanged = $newDesc !== $originalDesc;

        if ($titleChanged && $descChanged) {
            $this->bothFixed++;
        } elseif ($titleChanged) {
            $this->titleFixed++;
        } elseif ($descChanged) {
            $this->descFixed++;
        }

        if (($titleChanged || $descChanged) && count($this->samples) < 5) {
            if ($titleChanged) {
                $this->samples[] = ['slug' => $article->slug, 'field' => 'title', 'before' => mb_substr($originalTitle, 0, 80), 'after' => mb_substr($newTitle, 0, 80)];
            }
            if ($descChanged && count($this->samples) < 5) {
                $this->samples[] = ['slug' => $article->slug, 'field' => 'description', 'before' => mb_substr($originalDesc, 0, 80), 'after' => mb_substr($newDesc, 0, 80)];
            }
        }

        if ($apply && ($titleChanged || $descChanged)) {
            // Targeted update through the query builder — no Eloquent model
            // hydration, no model events, no dirty-tracking overhead.
            DB::table('news_articles')
                ->where('id', $article->id)
                ->update(array_filter([
                    'meta_title' => $titleChanged ? $newTitle : null,
                    'meta_description' => $descChanged ? $newDesc : null,
                    'updated_at' => now(),
                ], fn ($v) => $v !== null));
        }

        $this->total++;

        // Free the current row's heaviest fields (content is several KB).
        unset($article);
    }

    private function report(): void
    {
        $this->newLine();
        $this->info("Total published articles: {$this->total}");
        $this->info("Title only fixed:        {$this->titleFixed}");
        $this->info("Description only fixed:  {$this->descFixed}");
        $this->info("Both fixed:              {$this->bothFixed}");
        $this->info("Collisions resolved:     {$this->collisionsResolved}");
        $this->info('Total changes:           '.($this->titleFixed + $this->descFixed + $this->bothFixed));

        if ($this->samples !== []) {
            $this->newLine();
            $this->info('Samples (before → after):');
            foreach ($this->samples as $i => $s) {
                $n = $i + 1;
                $this->line("  {$n}. [{$s['field']}] {$s['slug']}");
                $this->line("     before: {$s['before']}");
                $this->line("     after:  {$s['after']}");
            }
        }
    }
}
