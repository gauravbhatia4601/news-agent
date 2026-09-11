<?php

namespace App\News\Repositories;

use App\News\DTO\DiscoveredSource;
use App\News\DTO\DiscoveredTopic;
use App\News\Support\HeadlineSimilarity;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class NewsTopicRepository
{
    public function saveTopicWithSources(DiscoveredTopic $topic, bool $forceUniqueSignature = false): int
    {
        return DB::transaction(function () use ($topic, $forceUniqueSignature): int {
            $existingTopic = DB::table('news_topics')
                ->where('topic_signature', $topic->signature)
                ->first();

            if ($existingTopic) {
                DB::table('news_topics')
                    ->where('id', $existingTopic->id)
                    ->update([
                        'category' => $topic->category,
                        'category_id' => $topic->categoryId ?? $existingTopic->category_id,
                        'location_category_id' => $topic->locationCategoryId ?? $existingTopic->location_category_id,
                        'topic_name' => $topic->name,
                        'source_count' => $topic->sourceCount(),
                        'core_tokens' => json_encode($topic->coreTokens),
                        'updated_at' => now(),
                    ]);

                $topicId = (int) $existingTopic->id;
            } elseif ($forceUniqueSignature) {
                // Story-namespaced signatures must persist as their own row — fuzzy
                // matching would redirect the monitor to an hourly topic row whose
                // signature differs, silently breaking the story link.
                $topicId = (int) DB::table('news_topics')->insertGetId([
                    'category' => $topic->category ?? 'Uncategorized',
                    'category_id' => $topic->categoryId,
                    'location_category_id' => $topic->locationCategoryId,
                    'topic_name' => $topic->name,
                    'topic_signature' => $topic->signature,
                    'core_tokens' => json_encode($topic->coreTokens),
                    'source_count' => $topic->sourceCount(),
                    'generation_status' => 'pending',
                    'llm_generated_at' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                $fuzzyMatch = $this->findFuzzyDuplicate($topic);
                if ($fuzzyMatch !== null) {
                    $existing = DB::table('news_topics')->where('id', $fuzzyMatch)->first();

                    DB::table('news_topics')
                        ->where('id', $fuzzyMatch)
                        ->update([
                            'category' => $topic->category ?? $existing->category ?? 'Uncategorized',
                            'category_id' => $topic->categoryId ?? $existing->category_id ?? null,
                            'topic_name' => $topic->name,
                            'source_count' => $topic->sourceCount(),
                            'core_tokens' => json_encode($topic->coreTokens),
                            'location_category_id' => $topic->locationCategoryId ?? $existing->location_category_id ?? null,
                            'updated_at' => now(),
                        ]);

                    $topicId = $fuzzyMatch;
                } else {
                    $topicId = (int) DB::table('news_topics')->insertGetId([
                        'category' => $topic->category,
                        'category_id' => $topic->categoryId,
                        'location_category_id' => $topic->locationCategoryId,
                        'topic_name' => $topic->name,
                        'topic_signature' => $topic->signature,
                        'core_tokens' => json_encode($topic->coreTokens),
                        'source_count' => $topic->sourceCount(),
                        'generation_status' => 'pending',
                        'llm_generated_at' => null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            $topic->persistedId = $topicId;

            foreach ($topic->sources as $source) {
                $this->upsertSource($topicId, $source);
            }

            return $topicId;
        });
    }

    private function findFuzzyDuplicate(DiscoveredTopic $topic): ?int
    {
        if ($topic->name === '') {
            return null;
        }

        // Hourly-discovery existing-topic matcher. Raw-token Jaccard on
        // core_tokens missed headline drift (plurals, possessives, reworded
        // leads) and spawned duplicate topic rows — ~700 articles/day vs the
        // ~200 baseline. Two gates now attach a candidate to an existing row:
        //   1. Stemmed-token Jaccard >= 0.55 (HeadlineSimilarity::tokens)
        //   2. HeadlineSimilarity::similar(name, existingName, 0.6)
        // Signatures are untouched — only the attach-vs-insert decision changes.
        $candidateTokens = HeadlineSimilarity::tokens($topic->name);

        // 36h recency window — same-event detection, not cross-week entity
        // merging. Two distinct events sharing entities 5 days apart is a
        // worse failure than a missed match; the save-time gate backstops.
        $recentTopics = DB::table('news_topics')
            ->where('created_at', '>=', now()->subHours(36))
            ->orderByDesc('id')
            ->get(['id', 'topic_name']);

        foreach ($recentTopics as $existing) {
            $existingName = (string) ($existing->topic_name ?? '');
            if ($existingName === '') {
                continue;
            }

            // Gate 2: stemmed overlap coefficient (handles reworded leads that
            // Jaccard on full token sets can miss).
            if (HeadlineSimilarity::similar($topic->name, $existingName, 0.6)) {
                return (int) $existing->id;
            }

            // Gate 1: stemmed-token Jaccard >= 0.55.
            $existingTokens = HeadlineSimilarity::tokens($existingName);
            if ($candidateTokens === [] || $existingTokens === []) {
                continue;
            }

            $intersection = count(array_intersect($candidateTokens, $existingTokens));
            $union = count(array_unique(array_merge($candidateTokens, $existingTokens)));
            $jaccard = $union > 0 ? $intersection / $union : 0;

            if ($jaccard >= 0.55) {
                return (int) $existing->id;
            }
        }

        return null;
    }

    public function claimTopicForGeneration(int $topicId): bool
    {
        $affected = DB::table('news_topics')
            ->where('id', $topicId)
            ->where('generation_status', 'pending')
            ->update([
                'generation_status' => 'generating',
                'updated_at' => now(),
            ]);

        return $affected > 0;
    }

    public function markGenerated(int $topicId): void
    {
        DB::table('news_topics')
            ->where('id', $topicId)
            ->update([
                'generation_status' => 'generated',
                'llm_generated_at' => now(),
                'updated_at' => now(),
            ]);
    }

    /**
     * @param  string[]  $topicSignatures
     * @return array<int, array{id: int, category: string, topic_name: string, topic_signature: string, sources: array<int, array<string, mixed>>}>
     */
    public function getPendingTopicsBySignatures(array $topicSignatures): array
    {
        if ($topicSignatures === []) {
            return [];
        }

        $topics = DB::table('news_topics')
            ->leftJoin('categories as location_category', 'location_category.id', '=', 'news_topics.location_category_id')
            ->where('news_topics.generation_status', 'pending')
            ->whereIn('news_topics.topic_signature', $topicSignatures)
            ->orderByDesc('news_topics.updated_at')
            ->get(['news_topics.id', 'news_topics.category', 'news_topics.topic_name', 'news_topics.topic_signature', 'location_category.name as location_name']);

        $result = [];

        foreach ($topics as $topic) {
            $sources = DB::table('news_topic_sources')
                ->where('topic_id', $topic->id)
                ->orderByDesc('published_at')
                ->get(['source_name', 'source_url', 'headline', 'summary', 'published_at'])
                ->map(fn ($source) => (array) $source)
                ->all();

            $result[] = [
                'id' => (int) $topic->id,
                'category' => (string) $topic->category,
                'topic_name' => (string) $topic->topic_name,
                'topic_signature' => (string) $topic->topic_signature,
                'location' => (string) ($topic->location_name ?? ''),
                'sources' => $sources,
            ];
        }

        return $result;
    }

    /**
     * Persist a generated article for a topic.
     *
     * Returns the article id on save, or null when the article was suppressed
     * as a duplicate of a recently-published article (the last-resort gate).
     * Null is not an error — callers must NOT treat it as a failure.
     *
     * @return int|null Article id, or null when suppressed as a duplicate.
     */
    public function saveGeneratedArticle(
        int $topicId,
        string $title,
        string $content,
        ?string $provider,
        ?string $model,
        ?string $metaTitle,
        ?string $metaDescription,
        ?string $metaKeywords,
        ?string $imageUrl,
        ?string $thumbnailUrl,
        array $metadata = [],
        string $status = 'published',
        ?string $qualityReport = null,
        int $generationDurationSeconds = 0,
        ?int $storyId = null,
    ): ?int {
        // Duplicate gate (last-resort backstop): before persisting as
        // published, compare the candidate title against every published
        // article from the last 24h across ALL pipelines (hourly discovery
        // AND live-story supporting articles). Upstream stem-matching,
        // the monitor pre-filter, and the per-story daily cap catch most
        // duplicates; this is the guaranteed backstop that runs at the
        // moment a generated article is about to be saved — so it also
        // blocks an hourly-discovery article duplicating a story's
        // supporting article for the same event.
        // ponytail: a few hundred published rows/day at current scale (~200
        // baseline) — an unindexed 24h scan is fine here. If volume ever
        // reaches ~10k/day, narrow to same-category or add a title hash
        // index. This is not a hot path (one call per generated article).
        if ($status === 'published' && ($dupe = $this->findRecentPublishedDuplicate($title, $topicId)) !== null) {
            \Log::info('Duplicate article suppressed at save gate.', [
                'topic_id' => $topicId,
                'suppressed_title' => $title,
                'matched_article_id' => $dupe->id,
                'matched_title' => $dupe->title,
            ]);

            // Mark the topic as a deliberate duplicate skip — a terminal
            // status distinct from real failures. Recovery paths (ensure-
            // pass, janitor, retry-failed) all skip 'duplicate_skipped'.
            // An admin can still manually force-retry via the admin UI.
            DB::table('news_topics')->where('id', $topicId)->update([
                'generation_status' => 'duplicate_skipped',
                'updated_at' => now(),
            ]);

            return null;
        }

        return DB::transaction(function () use ($topicId, $title, $content, $provider, $model, $metaTitle, $metaDescription, $metaKeywords, $imageUrl, $thumbnailUrl, $metadata, $status, $qualityReport, $generationDurationSeconds, $storyId): int {
            // Derive the story from the pivot when not explicitly threaded: any
            // article for a story-linked topic is automatically a supporting
            // article — this closes the detection retcon race (pivot linked at
            // detection, article created later by whatever queued job wins).
            if ($storyId === null) {
                $pivotStoryId = DB::table('story_topics')->where('topic_id', $topicId)->value('story_id');
                $storyId = $pivotStoryId !== null ? (int) $pivotStoryId : null;
            }

            $existing = DB::table('news_articles')->where('topic_id', $topicId)->first();
            $slug = $this->generateUniqueArticleSlug($title, $existing?->id ?? null);

            $payload = [
                'title' => $title,
                'content' => $content,
                'provider' => $provider,
                'model' => $model,
                'slug' => $slug,
                'meta_title' => $metaTitle,
                'meta_description' => $metaDescription,
                'meta_keywords' => $metaKeywords,
                'image_url' => $imageUrl,
                'thumbnail_url' => $thumbnailUrl,
                'status' => $status,
                'published_at' => $status === 'published' ? now() : null,
                'quality_report' => $qualityReport,
                'generation_duration_seconds' => $generationDurationSeconds,
                'metadata' => ! empty($metadata) ? json_encode($metadata) : null,
                'story_id' => $storyId,
                'updated_at' => now(),
            ];

            if ($existing) {
                DB::table('news_articles')
                    ->where('topic_id', $topicId)
                    ->update($payload);
                $articleId = (int) $existing->id;
            } else {
                $articleId = (int) DB::table('news_articles')->insertGetId($payload + [
                    'topic_id' => $topicId,
                    'created_at' => now(),
                ]);
            }

            $this->markGenerated($topicId);

            return $articleId;
        });
    }

    /**
     * Find a published article from the last 24h whose title is similar to the
     * candidate. Returns the first match (id, title) or null. The cross-
     * pipeline scan (all stories + hourly discovery) is the guarantee that no
     * duplicate slips past the upstream filters.
     */
    private function findRecentPublishedDuplicate(string $candidateTitle, int $topicId): ?object
    {
        $recent = DB::table('news_articles')
            ->where('status', 'published')
            ->where('published_at', '>=', now()->subDay())
            ->where('topic_id', '!=', $topicId)
            ->get(['id', 'title']);

        foreach ($recent as $article) {
            if (HeadlineSimilarity::similar($candidateTitle, (string) $article->title)) {
                return $article;
            }
        }

        return null;
    }

    public function markGenerationFailed(int $topicId): void
    {
        DB::table('news_topics')
            ->where('id', $topicId)
            ->increment('retry_count');

        DB::table('news_topics')
            ->where('id', $topicId)
            ->update([
                'generation_status' => 'failed',
                'updated_at' => now(),
            ]);
    }

    public function retryFailed(int $maxRetries = 3): array
    {
        $topics = DB::table('news_topics')
            ->where('generation_status', 'failed')
            ->where('retry_count', '<', $maxRetries)
            ->orderByDesc('updated_at')
            ->take(10)
            ->get(['id', 'topic_signature']);

        foreach ($topics as $topic) {
            DB::table('news_topics')
                ->where('id', $topic->id)
                ->update([
                    'generation_status' => 'pending',
                    'updated_at' => now(),
                ]);
        }

        return array_map(fn ($t) => $t->topic_signature, $topics->toArray());
    }

    private function generateUniqueArticleSlug(string $title, ?int $ignoreId = null): string
    {
        $base = Str::slug($title);
        if ($base === '') {
            $base = 'article';
        }

        $slug = $base;
        $counter = 2;

        while (DB::table('news_articles')
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->where('slug', $slug)
            ->exists()) {
            $slug = $base.'-'.$counter;
            $counter++;
        }

        return $slug;
    }

    private function upsertSource(int $topicId, DiscoveredSource $source): void
    {
        $sourceUrlHash = sha1((string) preg_replace('/\?.*$/', '', $source->sourceUrl));

        $existingSource = DB::table('news_topic_sources')
            ->where('topic_id', $topicId)
            ->where('source_url_hash', $sourceUrlHash)
            ->first();

        $payload = [
            'source_name' => $source->sourceName,
            'source_url' => $source->sourceUrl,
            'headline' => $source->headline,
            'summary' => $source->summary,
            'published_at' => $source->publishedAt,
            'updated_at' => now(),
        ];

        if ($existingSource) {
            DB::table('news_topic_sources')
                ->where('id', $existingSource->id)
                ->update($payload);

            return;
        }

        DB::table('news_topic_sources')->insert($payload + [
            'topic_id' => $topicId,
            'source_url_hash' => $sourceUrlHash,
            'created_at' => now(),
        ]);
    }
}
