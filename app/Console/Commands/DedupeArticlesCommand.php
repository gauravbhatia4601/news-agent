<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\News\Support\HeadlineSimilarity;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DedupeArticlesCommand extends Command
{
    protected $signature = 'news:dedupe-articles
                            {--apply : Actually publish changes (default dry-run)}';

    protected $description = 'Draft near-duplicate published articles and conclude twin stories (dry-run by default).';

    /**
     * ponypoint: O(n²) scan over the last 7 days of published articles.
     * Fine at the current ~200/day scale (~1400 rows); revisit if volume
     * grows 10x — a blocked-index or pre-cluster on stemmed tokens would
     * replace the pairwise loop.
     */
    public function handle(): int
    {
        $apply = (bool) $this->option('apply');

        // Load published articles from the last 7 days ordered by published_at
        // desc, joining news_topics for source_count (the representative race).
        $articles = DB::table('news_articles')
            ->leftJoin('news_topics', 'news_topics.id', '=', 'news_articles.topic_id')
            ->where('news_articles.status', 'published')
            ->where('news_articles.published_at', '>=', now()->subDays(7))
            ->orderByDesc('news_articles.published_at')
            ->get([
                'news_articles.id',
                'news_articles.title',
                'news_articles.published_at',
                'news_articles.story_id',
                DB::raw('COALESCE(news_topics.source_count, 0) as source_count'),
            ]);

        // Re-sort for clustering so the representative of each cluster is
        // encountered first: highest source_count, then earliest published_at.
        $clustered = $articles->all();
        usort($clustered, function ($a, $b): int {
            if ($a->source_count !== $b->source_count) {
                return $b->source_count <=> $a->source_count; // desc
            }

            return $a->published_at <=> $b->published_at; // asc
        });

        // --- Near-dup article clustering -----------------------------------
        // An article is a duplicate of an earlier-kept article when
        // HeadlineSimilarity::similar() is true. The first article in each
        // cluster (highest source_count, earliest published_at) is the
        // representative; the rest are drafted.
        $kept = []; // [articleId => true] for representatives already chosen
        $draftIds = [];

        foreach ($clustered as $article) {
            $isDup = false;
            foreach ($clustered as $other) {
                if ($other->id === $article->id) {
                    continue;
                }
                if (! isset($kept[$other->id])) {
                    continue;
                }
                if (HeadlineSimilarity::similar((string) $article->title, (string) $other->title)) {
                    $isDup = true;
                    break;
                }
            }

            if ($isDup) {
                $draftIds[] = $article->id;
            } else {
                $kept[$article->id] = true;
            }
        }

        // --- Twin stories --------------------------------------------------
        // Among ACTIVE stories, if two titles are similar at 0.6, keep the one
        // with more timeline updates (tie: earlier started_at); conclude the
        // other (urgency=concluded, concluded_at=now()).
        $activeStories = DB::table('stories')
            ->where('urgency', '!=', 'concluded')
            ->get(['id', 'title', 'started_at']);

        $updateCounts = DB::table('story_updates')
            ->select('story_id', DB::raw('count(*) as cnt'))
            ->whereIn('story_id', $activeStories->pluck('id')->all())
            ->groupBy('story_id')
            ->pluck('cnt', 'story_id');

        $concludeStoryIds = [];
        foreach ($activeStories as $a) {
            if (in_array($a->id, $concludeStoryIds, true)) {
                continue;
            }
            foreach ($activeStories as $b) {
                if ($a->id >= $b->id) {
                    continue; // each pair once, and skip self
                }
                if (! HeadlineSimilarity::similar((string) $a->title, (string) $b->title, 0.6)) {
                    continue;
                }
                // Decide which to keep: more timeline updates, tie → earlier started_at.
                $aUpdates = (int) ($updateCounts[$a->id] ?? 0);
                $bUpdates = (int) ($updateCounts[$b->id] ?? 0);
                $aStarted = $a->started_at ?? now();
                $bStarted = $b->started_at ?? now();

                $keepA = $aUpdates > $bUpdates
                    || ($aUpdates === $bUpdates && $aStarted <= $bStarted);

                $concludeStoryIds[] = $keepA ? $b->id : $a->id;
            }
        }

        // --- Report ---------------------------------------------------------
        foreach ($draftIds as $id) {
            $this->line("Would draft article id={$id}");
        }
        foreach ($concludeStoryIds as $id) {
            $this->line("Would conclude story id={$id}");
        }

        $this->info(sprintf(
            '%s: %d duplicate%s would be drafted, %d twin stori%s would be concluded.',
            $apply ? 'Applied' : 'Dry-run',
            count($draftIds),
            count($draftIds) === 1 ? '' : 's',
            count($concludeStoryIds),
            count($concludeStoryIds) === 1 ? 'y' : 'es',
        ));

        if (! $apply) {
            $this->comment('Run with --apply to execute.');
        }

        // --- Apply ----------------------------------------------------------
        if ($apply) {
            foreach ($draftIds as $id) {
                DB::table('news_articles')
                    ->where('id', $id)
                    ->update(['status' => 'draft', 'updated_at' => now()]);
            }

            foreach ($concludeStoryIds as $id) {
                DB::table('stories')
                    ->where('id', $id)
                    ->update([
                        'urgency' => 'concluded',
                        'concluded_at' => now(),
                        'updated_at' => now(),
                    ]);
            }
        }

        return self::SUCCESS;
    }
}
