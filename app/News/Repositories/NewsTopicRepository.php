<?php

namespace App\News\Repositories;

use App\News\DTO\DiscoveredSource;
use App\News\DTO\DiscoveredTopic;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class NewsTopicRepository
{
    public function saveTopicWithSources(DiscoveredTopic $topic): int
    {
        return DB::transaction(function () use ($topic): int {
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

            foreach ($topic->sources as $source) {
                $this->upsertSource($topicId, $source);
            }

            return $topicId;
        });
    }

    private function findFuzzyDuplicate(DiscoveredTopic $topic): ?int
    {
        if ($topic->coreTokens === []) {
            return null;
        }

        $recentTopics = DB::table('news_topics')
            ->where('created_at', '>=', now()->subDays(7))
            ->whereNotNull('core_tokens')
            ->get(['id', 'core_tokens']);

        foreach ($recentTopics as $existing) {
            $existingTokens = json_decode($existing->core_tokens, true);
            if (! is_array($existingTokens) || $existingTokens === []) {
                continue;
            }

            $intersection = count(array_intersect($topic->coreTokens, $existingTokens));
            $union = count(array_unique(array_merge($topic->coreTokens, $existingTokens)));
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
    ): void {
        DB::transaction(function () use ($topicId, $title, $content, $provider, $model, $metaTitle, $metaDescription, $metaKeywords, $imageUrl, $thumbnailUrl, $metadata, $status, $qualityReport, $generationDurationSeconds): void {
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
                'quality_report' => $qualityReport,
                'generation_duration_seconds' => $generationDurationSeconds,
                'metadata' => ! empty($metadata) ? json_encode($metadata) : null,
                'updated_at' => now(),
            ];

            if ($existing) {
                DB::table('news_articles')
                    ->where('topic_id', $topicId)
                    ->update($payload);
            } else {
                DB::table('news_articles')->insert($payload + [
                    'topic_id' => $topicId,
                    'created_at' => now(),
                ]);
            }

            $this->markGenerated($topicId);
        });
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
