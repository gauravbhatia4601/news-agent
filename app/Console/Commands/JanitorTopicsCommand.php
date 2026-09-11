<?php

namespace App\Console\Commands;

use App\Jobs\GenerateArticle;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class JanitorTopicsCommand extends Command
{
    protected $signature = 'news:janitor-topics
                            {--backfill-duplicates : One-time backfill of article-less failed topics with retry_count>=5 to duplicate_skipped}';

    protected $description = 'Recover stuck generating topics, re-dispatch stale pending topics, and optionally backfill duplicate suppressions';

    private const STUCK_GENERATING_MINUTES = 30;

    private const STALE_PENDING_HOURS = 2;

    private const CAP = 10;

    public function handle(): int
    {
        if ($this->option('backfill-duplicates')) {
            $this->backfillDuplicates();
        }

        $this->recoverStuckGenerating();
        $this->redispatchStalePending();

        return self::SUCCESS;
    }

    /**
     * Stuck 'generating' topics: crashed workers that never recovered.
     * Reset to pending and dispatch, but only if no article exists yet.
     */
    private function recoverStuckGenerating(): void
    {
        $topics = DB::table('news_topics')
            ->where('generation_status', 'generating')
            ->where('updated_at', '<', now()->subMinutes(self::STUCK_GENERATING_MINUTES))
            ->whereNotExists(fn ($q) => $q->select(DB::raw(1))
                ->from('news_articles')
                ->whereColumn('news_articles.topic_id', 'news_topics.id'))
            ->orderBy('updated_at')
            ->limit(self::CAP)
            ->get(['id', 'topic_signature']);

        if ($topics->isEmpty()) {
            $this->info('No stuck generating topics to recover.');

            return;
        }

        foreach ($topics as $topic) {
            DB::table('news_topics')->where('id', $topic->id)->update([
                'generation_status' => 'pending',
                'updated_at' => now(),
            ]);

            GenerateArticle::dispatch($topic->topic_signature);
        }

        $this->info("Recovered {$topics->count()} stuck generating topic(s).");
    }

    /**
     * Stale 'pending' topics: a job that never ran (pre-v2 seeds, missed
     * dispatch). Re-dispatch if still article-less.
     */
    private function redispatchStalePending(): void
    {
        $topics = DB::table('news_topics')
            ->where('generation_status', 'pending')
            ->where('updated_at', '<', now()->subHours(self::STALE_PENDING_HOURS))
            ->whereNotExists(fn ($q) => $q->select(DB::raw(1))
                ->from('news_articles')
                ->whereColumn('news_articles.topic_id', 'news_topics.id'))
            ->orderBy('updated_at')
            ->limit(self::CAP)
            ->get(['id', 'topic_signature']);

        if ($topics->isEmpty()) {
            $this->info('No stale pending topics to re-dispatch.');

            return;
        }

        foreach ($topics as $topic) {
            GenerateArticle::dispatch($topic->topic_signature);
        }

        $this->info("Re-dispatched {$topics->count()} stale pending topic(s).");
    }

    /**
     * One-time backfill: article-less 'failed' topics with retry_count >= 5
     * are save-time duplicate suppressions mislabelled as failures before
     * the duplicate_skipped status existed. Topics WITH articles stay 'failed'
     * (true failures the ensure-pass already corrects).
     */
    private function backfillDuplicates(): void
    {
        $affected = DB::table('news_topics')
            ->where('generation_status', 'failed')
            ->where('retry_count', '>=', 5)
            ->whereNotExists(fn ($q) => $q->select(DB::raw(1))
                ->from('news_articles')
                ->whereColumn('news_articles.topic_id', 'news_topics.id'))
            ->update(['generation_status' => 'duplicate_skipped', 'updated_at' => now()]);

        $this->info("Backfilled {$affected} article-less failed topic(s) to duplicate_skipped.");
    }
}
