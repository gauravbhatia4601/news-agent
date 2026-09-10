<?php

namespace App\News\Services;

use App\Ai\Services\LiveStoryAgentService;
use App\Jobs\GenerateArticle;
use App\Models\Story;
use App\News\Support\HeadlineSimilarity;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MonitorLiveStoriesService
{
    public function __construct(
        private readonly NewsDiscoveryService $discoveryService,
        private readonly LiveStoryAgentService $agentService,
    ) {}

    /**
     * Run one monitoring cycle for a story: discover fresh candidates,
     * judge them for genuine new developments, dispatch generation jobs,
     * and handle auto-conclude logic.
     *
     * @return array{discovered: int, judged_new: int, dispatched: int, retried: int, concluded: bool}
     */
    public function runCycle(Story $story): array
    {
        $fresh = $story->freshnessConfig();
        $topicsLimit = (int) config('news-engine.live_stories.monitor_topics_limit', 3);

        // Discover fresh candidates with adaptive freshness.
        $topics = $this->discoveryService->discoverForQuery(
            $story->search_query,
            [
                'google' => $fresh['google'],
                'brave' => $fresh['brave'],
                'gdelt' => $fresh['gdelt'],
                'hours' => $fresh['hours'],
            ],
            $fresh['hours'],
            $topicsLimit,
            3,
            $story->id,
        );

        $discovered = count($topics);

        // Get the last published article for this story (the "last update").
        $lastUpdateArticle = $story->articles()->orderByDesc('published_at')->first();
        $lastUpdate = $lastUpdateArticle
            ? ['headline' => $lastUpdateArticle->title, 'summary' => (string) $lastUpdateArticle->meta_description]
            : null;

        // Build the "already covered" headline set for the pre-filter:
        // the last update article title + recent timeline entry contents.
        // Candidates near-duplicate to any covered headline are suppressed
        // before the judge (no judge cost, no pivot, no timeline, no dispatch).
        $coveredHeadlines = [];
        if ($lastUpdateArticle !== null && $lastUpdateArticle->title !== '') {
            $coveredHeadlines[] = $lastUpdateArticle->title;
        }
        $recentTimelineContents = $story->updates()
            ->where('event_at', '>=', now()->subHours(6))
            ->limit(10)
            ->pluck('content')
            ->all();
        foreach ($recentTimelineContents as $content) {
            if (trim((string) $content) !== '') {
                $coveredHeadlines[] = $content;
            }
        }

        // Pre-filter candidates: skip near-duplicates of covered headlines
        // or of candidates already accepted this cycle.
        $candidates = [];
        $acceptedThisCycle = [];
        foreach ($topics as $topic) {
            $firstSource = $topic->sources[0] ?? null;
            $candidateHeadline = $firstSource?->headline ?? $topic->name;

            $isDuplicate = false;
            foreach ($coveredHeadlines as $covered) {
                if (HeadlineSimilarity::similar($candidateHeadline, $covered)) {
                    $isDuplicate = true;
                    break;
                }
            }
            if (! $isDuplicate) {
                foreach ($acceptedThisCycle as $accepted) {
                    if (HeadlineSimilarity::similar($candidateHeadline, $accepted)) {
                        $isDuplicate = true;
                        break;
                    }
                }
            }

            if ($isDuplicate) {
                Log::info('Story monitor: suppressed duplicate candidate before judge.', [
                    'story_id' => $story->id,
                    'headline' => $candidateHeadline,
                ]);

                continue;
            }

            $acceptedThisCycle[] = $candidateHeadline;
            $candidates[] = [
                'topic_signature' => $topic->signature,
                'headline' => $candidateHeadline,
                'summary' => $firstSource?->summary ?? '',
            ];
        }

        if ($candidates === []) {
            Log::info('Story monitor: all candidates suppressed as duplicates, skipping judge.', [
                'story_id' => $story->id,
                'discovered' => $discovered,
            ]);

            $verdicts = [];
        } else {
            $verdicts = $this->agentService->judgeUpdateBatch($story, $candidates, $lastUpdate);
        }

        $judgedNew = 0;
        $dispatched = 0;
        $concluded = false;
        $adjustment = 'keep';

        foreach ($topics as $topic) {
            $verdict = $verdicts[$topic->signature] ?? null;
            if ($verdict === null) {
                continue;
            }

            // Check for concluded urgency.
            $adjustment = mb_strtolower(trim($verdict['urgency_adjustment'] ?? 'keep'));
            if ($adjustment === 'concluded') {
                $concluded = true;
            }

            // persistedId is set by discoverForQuery (forceUniqueSignature) — the
            // namespaced row exists with this exact signature, so generation
            // will find it. getTopicIdBySignature stays as a defensive fallback.
            $topicId = $topic->persistedId ?? $this->getTopicIdBySignature($topic->signature);

            if ($topicId === null) {
                Log::warning('Story monitor: persisted topic id missing for signature, skipping.', [
                    'story_id' => $story->id,
                    'signature' => $topic->signature,
                ]);

                continue;
            }

            // Link EVERY judged candidate via pivot (provenance) — even when judged
            // "repetition": supporting articles accumulate across cycles, and any
            // article later generated for the topic auto-links via saveGeneratedArticle.
            DB::table('story_topics')->insertOrIgnore([
                'story_id' => $story->id,
                'topic_id' => $topicId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            if (! empty($verdict['is_new_development'])) {
                $judgedNew++;

                // Create the discrete timeline entry from the judge's update_text.
                // Guard: skip blank entries (LLM edge cases) — never render blanks.
                $updateText = trim((string) ($verdict['update_text'] ?? ''));
                if ($updateText !== '') {
                    $firstSource = $topic->sources[0] ?? null;
                    $story->updates()->create([
                        'content' => $updateText,
                        'event_at' => now(),
                        'source_name' => $firstSource?->sourceName,
                        'source_url' => $firstSource?->sourceUrl,
                    ]);
                } else {
                    Log::warning('Story monitor: judge returned empty update_text, skipping timeline entry.', [
                        'story_id' => $story->id,
                        'signature' => $topic->signature,
                    ]);
                }

                // Per-story daily article cap: still link via pivot and still
                // create timeline entries above, but skip generation dispatch
                // once the story has published cap articles in the last 24h.
                if ($this->storyDailyArticleCapReached($story)) {
                    Log::info('Story monitor: daily supporting-article cap reached, skipping dispatch.', [
                        'story_id' => $story->id,
                        'signature' => $topic->signature,
                    ]);
                } else {
                    // Dispatch generation job with storyId (full article as supporting content).
                    GenerateArticle::dispatch($topic->signature, $story->id);
                    $dispatched++;
                }
            }
        }

        // Ensure every linked topic has its supporting article — recovers failed,
        // never-dispatched, and crashed-generation topics alike.
        $ensure = $this->ensureSupportingArticles($story);
        $retried = $ensure['dispatched'];

        // Apply urgency adjustments.
        if ($concluded) {
            $story->update([
                'urgency' => 'concluded',
                'concluded_at' => now(),
                'last_monitored_at' => now(),
                'empty_cycles' => 0,
            ]);
        } else {
            // Apply non-keep urgency adjustment if it's a valid urgency and differs.
            if (in_array($adjustment, ['live', 'developing', 'ongoing'], true) && $adjustment !== $story->urgency) {
                $story->urgency = $adjustment;
            }

            // Auto-conclude via empty-cycles counter.
            $emptyCycles = $judgedNew > 0 ? 0 : $story->empty_cycles + 1;
            $threshold = (int) config('news-engine.live_stories.auto_conclude_after_empty_cycles', 6);
            $intervalMinutes = (int) config('news-engine.live_stories.monitor_interval_minutes', 10);

            if ($emptyCycles >= $threshold) {
                // Only auto-conclude if the last update is older than threshold * interval minutes.
                $staleMinutes = $threshold * $intervalMinutes;
                $lastUpdateDate = $lastUpdateArticle?->published_at ?? $story->started_at;
                if ($lastUpdateDate->lt(now()->subMinutes($staleMinutes))) {
                    $story->urgency = 'concluded';
                    $story->concluded_at = now();
                    $concluded = true;
                }
            }

            $story->empty_cycles = $emptyCycles;
            $story->last_monitored_at = now();
            $story->save();
        }

        Log::info('Monitor cycle complete.', [
            'story_id' => $story->id,
            'discovered' => $discovered,
            'judged_new' => $judgedNew,
            'dispatched' => $dispatched,
            'concluded' => $concluded,
        ]);

        return [
            'discovered' => $discovered,
            'judged_new' => $judgedNew,
            'dispatched' => $dispatched,
            'retried' => $retried,
            'concluded' => $concluded,
        ];
    }

    /**
     * Supporting-article resilience (the "ensure" pass): every linked topic
     * without an article gets recovered, whatever its stuck state —
     * 'failed' (transient LLM error), 'pending' with a job that never ran
     * (pre-v2 stories seeded without a dispatch), or 'generating' with a
     * crashed worker. Idempotent via the pending-claim, so a re-dispatch for
     * a topic whose job is still queued is harmless. Capped per cycle.
     */
    private function ensureSupportingArticles(Story $story): array
    {
        $retryLimit = (int) config('news-engine.live_stories.update_retry_limit', 3);

        // Only touch topics whose last activity is older than 15 min — a queued
        // job should have claimed/finished by then. In-flight ('generating' and
        // recent) topics are left alone.
        $stuck = DB::table('story_topics')
            ->join('news_topics', 'news_topics.id', '=', 'story_topics.topic_id')
            ->where('story_topics.story_id', $story->id)
            ->where('news_topics.generation_status', '!=', 'generating')
            ->where('news_topics.updated_at', '<', now()->subMinutes(15))
            ->limit(4)
            ->get(['news_topics.id', 'news_topics.topic_signature', 'news_topics.generation_status', 'news_topics.retry_count']);

        $retried = 0;
        $recovered = 0;

        foreach ($stuck as $topic) {
            $hasArticle = DB::table('news_articles')->where('topic_id', $topic->id)->exists();

            if ($hasArticle) {
                // Article exists — stale flag: correct the status, no dispatch.
                if ($topic->generation_status !== 'generated') {
                    DB::table('news_topics')->where('id', $topic->id)->update([
                        'generation_status' => 'generated',
                        'updated_at' => now(),
                    ]);
                    $recovered++;
                }

                continue;
            }

            if ($topic->generation_status === 'failed' && $topic->retry_count >= $retryLimit) {
                Log::warning('Story monitor: supporting topic exhausted its retries.', [
                    'story_id' => $story->id,
                    'topic_id' => $topic->id,
                    'retry_count' => $topic->retry_count,
                ]);

                continue;
            }

            // Per-story daily article cap: skip the recovery dispatch when the
            // story has already published cap articles in the last 24h. The
            // topic is NOT reset to pending in this case — it stays stuck so
            // the next cycle outside the cap window can recover it.
            if ($this->storyDailyArticleCapReached($story)) {
                Log::info('Story monitor: daily supporting-article cap reached, skipping recovery dispatch.', [
                    'story_id' => $story->id,
                    'topic_id' => $topic->id,
                ]);

                continue;
            }

            DB::table('news_topics')->where('id', $topic->id)->update([
                'generation_status' => 'pending',
                'retry_count' => $topic->retry_count + ($topic->generation_status === 'failed' ? 1 : 0),
                'updated_at' => now(),
            ]);

            GenerateArticle::dispatch($topic->topic_signature, $story->id);
            $retried++;
        }

        return ['dispatched' => $retried, 'recovered' => $recovered];
    }

    private function getTopicIdBySignature(string $signature): ?int
    {
        $id = DB::table('news_topics')->where('topic_signature', $signature)->value('id');

        return $id !== null ? (int) $id : null;
    }

    /**
     * Per-story daily article cap: true when the story has published >= cap
     * articles in the last 24h. Guards both the judged-new dispatch path and
     * the ensureSupportingArticles recovery path.
     */
    private function storyDailyArticleCapReached(Story $story): bool
    {
        $cap = (int) config('news-engine.live_stories.max_supporting_articles_per_day', 4);
        if ($cap <= 0) {
            return false;
        }

        $publishedLast24h = DB::table('news_articles')
            ->where('story_id', $story->id)
            ->where('status', 'published')
            ->where('published_at', '>=', now()->subDay())
            ->count();

        return $publishedLast24h >= $cap;
    }
}
