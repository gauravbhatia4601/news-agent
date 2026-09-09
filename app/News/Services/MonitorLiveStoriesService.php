<?php

namespace App\News\Services;

use App\Ai\Services\LiveStoryAgentService;
use App\Jobs\GenerateArticle;
use App\Models\Story;
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
     * @return array{discovered: int, judged_new: int, dispatched: int, concluded: bool}
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

        // Build candidate input for the judge.
        $candidates = [];
        foreach ($topics as $topic) {
            $firstSource = $topic->sources[0] ?? null;
            $candidates[] = [
                'topic_signature' => $topic->signature,
                'headline' => $firstSource?->headline ?? $topic->name,
                'summary' => $firstSource?->summary ?? '',
            ];
        }

        $verdicts = $this->agentService->judgeUpdateBatch($story, $candidates, $lastUpdate);

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

            if (! empty($verdict['is_new_development'])) {
                $judgedNew++;

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

                // Insert story_topics pivot if absent.
                DB::table('story_topics')->insertOrIgnore([
                    'story_id' => $story->id,
                    'topic_id' => $topicId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

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

                // Dispatch generation job with storyId (full article as supporting content).
                GenerateArticle::dispatch($topic->signature, $story->id);
                $dispatched++;
            }
        }

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
            'concluded' => $concluded,
        ];
    }

    private function getTopicIdBySignature(string $signature): ?int
    {
        $id = DB::table('news_topics')->where('topic_signature', $signature)->value('id');

        return $id !== null ? (int) $id : null;
    }
}
