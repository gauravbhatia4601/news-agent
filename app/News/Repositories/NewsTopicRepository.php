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
        $existingTopic = DB::table('news_topics')
            ->where('topic_signature', $topic->signature)
            ->first();

        if ($existingTopic) {
            DB::table('news_topics')
                ->where('id', $existingTopic->id)
                ->update([
                    'category' => $topic->category,
                    'topic_name' => $topic->name,
                    'source_count' => $topic->sourceCount(),
                    'updated_at' => now(),
                ]);

            $topicId = (int) $existingTopic->id;
        } else {
            $topicId = (int) DB::table('news_topics')->insertGetId([
                'category' => $topic->category,
                'topic_name' => $topic->name,
                'topic_signature' => $topic->signature,
                'source_count' => $topic->sourceCount(),
                'generation_status' => 'pending',
                'llm_generated_at' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        foreach ($topic->sources as $source) {
            $this->upsertSource($topicId, $source);
        }

        return $topicId;
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
            ->where('generation_status', 'pending')
            ->whereIn('topic_signature', $topicSignatures)
            ->orderByDesc('updated_at')
            ->get(['id', 'category', 'topic_name', 'topic_signature']);

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
    ): void {
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
    }

    public function markGenerationFailed(int $topicId): void
    {
        DB::table('news_topics')
            ->where('id', $topicId)
            ->update([
                'generation_status' => 'failed',
                'updated_at' => now(),
            ]);
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
