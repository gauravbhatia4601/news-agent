<?php

namespace App\Console\Commands;

use App\Ai\Services\LiveStoryAgentService;
use App\Models\Story;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DetectLiveStoriesCommand extends Command
{
    protected $signature = 'news:detect-live-stories
                            {--scope=india : Discovery scope (india|global)}
                            {--limit= : Override detection batch size}';

    protected $description = 'Detect ongoing live events from recently discovered topics via LLM triage.';

    public function handle(LiveStoryAgentService $agentService): int
    {
        if (! (bool) config('news-engine.live_stories.detection_enabled', true)) {
            $this->warn('Live story detection disabled by config.');

            return self::SUCCESS;
        }

        $batchSize = (int) ($this->option('limit') ?: config('news-engine.live_stories.detection_batch_size', 20));

        // Pull topics created in the last 2 hours (both scopes covered by the 2h window).
        $topics = DB::table('news_topics')
            ->where('created_at', '>=', now()->subHours(2))
            ->orderByDesc('created_at')
            ->limit($batchSize)
            ->get(['id', 'category', 'topic_name', 'topic_signature', 'category_id']);

        if ($topics->isEmpty()) {
            $this->info('No recent topics to triage.');

            return self::SUCCESS;
        }

        // Build input rows for the agent.
        $topicBatch = [];
        $topicMeta = [];

        foreach ($topics as $topic) {
            $headlines = DB::table('news_topic_sources')
                ->where('topic_id', $topic->id)
                ->orderByDesc('published_at')
                ->limit(2)
                ->pluck('headline')
                ->all();

            $topicBatch[] = [
                'topic_signature' => $topic->topic_signature,
                'topic_name' => $topic->topic_name,
                'category' => $topic->category,
                'dominant_term' => $topic->topic_name,
                'headlines' => $headlines,
            ];

            $topicMeta[$topic->topic_signature] = $topic;
        }

        $this->line('Triaging '.count($topicBatch).' topics...');

        $detected = $agentService->detectLiveStories($topicBatch);

        $liveEvents = array_filter($detected, fn ($d) => ! empty($d['is_live_event']));

        if ($liveEvents === []) {
            $this->info('No live events detected.');

            return self::SUCCESS;
        }

        $maxActive = (int) config('news-engine.live_stories.max_active_stories', 20);
        $activeCount = Story::active()->count();
        $created = 0;
        $skipped = 0;

        foreach ($liveEvents as $event) {
            $sig = $event['topic_signature'] ?? '';
            $topic = $topicMeta[$sig] ?? null;

            if ($topic === null) {
                continue;
            }

            $suggestedTitle = trim($event['suggested_title'] ?? '') ?: $topic->topic_name;
            $searchQuery = trim($event['search_query'] ?? '') ?: $topic->topic_name;
            $urgency = $this->normalizeUrgency($event['urgency'] ?? 'developing');
            $slug = Str::slug($suggestedTitle);

            // Dedupe by slug.
            if (Story::where('slug', $slug)->exists()) {
                $skipped++;

                continue;
            }

            // Dedupe by Jaccard >= 0.6 on search_query tokens vs active stories.
            if ($this->isDuplicateQuery($searchQuery)) {
                $skipped++;

                continue;
            }

            // Respect max_active_stories.
            if ($activeCount >= $maxActive) {
                $this->warn("Max active stories ({$maxActive}) reached — skipping remaining detections.");
                break;
            }

            // Create the story.
            $story = Story::create([
                'slug' => $slug,
                'title' => $suggestedTitle,
                'search_query' => $searchQuery,
                'category_id' => $topic->category_id,
                'urgency' => $urgency,
                'status' => 'auto-detected',
                'started_at' => now(),
                'monitor_interval_minutes' => (int) config('news-engine.live_stories.monitor_interval_minutes', 10),
                'empty_cycles' => 0,
            ]);

            // Insert story_topics pivot for the seeding topic.
            DB::table('story_topics')->insertOrIgnore([
                'story_id' => $story->id,
                'topic_id' => $topic->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Seed the first timeline entry from the seeding topic's most recent source.
            $seedSource = DB::table('news_topic_sources')
                ->where('topic_id', $topic->id)
                ->orderByDesc('published_at')
                ->first(['headline', 'source_name', 'source_url', 'published_at']);

            if ($seedSource !== null) {
                DB::table('story_updates')->insert([
                    'story_id' => $story->id,
                    'content' => mb_substr($seedSource->headline ?? $topic->topic_name, 0, 160),
                    'event_at' => $seedSource->published_at ?? now(),
                    'source_name' => $seedSource->source_name,
                    'source_url' => $seedSource->source_url,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            // Retcon: if the seeding topic has an article, link it to the story.
            DB::table('news_articles')
                ->where('topic_id', $topic->id)
                ->whereNull('story_id')
                ->update(['story_id' => $story->id]);

            $this->line("  Created story: {$story->title} [{$urgency}]");
            $created++;
            $activeCount++;
        }

        $this->newLine();
        $this->info("Detection complete: {$created} stories created, {$skipped} duplicates skipped.");

        $this->table(
            ['Title', 'Urgency', 'Search Query'],
            Story::active()->orderByDesc('created_at')->limit($created)->get(['title', 'urgency', 'search_query'])->map(fn ($s) => [$s->title, $s->urgency, $s->search_query])->all()
        );

        return self::SUCCESS;
    }

    /**
     * Check if the search_query is too similar to an existing active story (Jaccard >= 0.6).
     */
    private function isDuplicateQuery(string $query): bool
    {
        $queryTokens = $this->tokenize($query);
        if ($queryTokens === []) {
            return false;
        }

        $activeStories = Story::active()->whereNotNull('search_query')->get(['search_query']);

        foreach ($activeStories as $story) {
            $existingTokens = $this->tokenize($story->search_query);
            if ($existingTokens === []) {
                continue;
            }

            if ($this->tokenJaccard($queryTokens, $existingTokens) >= 0.6) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  string[]  $a
     * @param  string[]  $b
     */
    private function tokenJaccard(array $a, array $b): float
    {
        $setA = array_unique($a);
        $setB = array_unique($b);
        $intersection = count(array_intersect($setA, $setB));
        $union = count(array_unique(array_merge($setA, $setB)));

        return $union > 0 ? ($intersection / $union) : 0.0;
    }

    /**
     * @return string[]
     */
    private function tokenize(string $text): array
    {
        $stopwords = ['the', 'a', 'an', 'in', 'on', 'at', 'of', 'for', 'and', 'or', 'to', 'is', 'are', 'was', 'were', 'be', 'been', 'by', 'with', 'from', 'as', 'it', 'its', 'this', 'that', 'these', 'those', 'has', 'have', 'had', 'will', 'would', 'could', 'should', 'may', 'might', 'can', 'do', 'does', 'did', 'not', 'no', 'but', 'if', 'then', 'than', 'so', 'such', 'also', 'about', 'into', 'after', 'before', 'during', 'while', 'where', 'when', 'what', 'which', 'who', 'whom', 'whose', 'how', 'why'];

        $words = preg_split('/[\s\p{P}]+/u', mb_strtolower($text)) ?: [];
        $tokens = array_filter($words, fn ($w) => strlen($w) > 2 && ! in_array($w, $stopwords, true));

        return array_values($tokens);
    }

    private function normalizeUrgency(string $urgency): string
    {
        $urgency = mb_strtolower(trim($urgency));

        return in_array($urgency, ['live', 'developing', 'ongoing'], true) ? $urgency : 'developing';
    }
}
