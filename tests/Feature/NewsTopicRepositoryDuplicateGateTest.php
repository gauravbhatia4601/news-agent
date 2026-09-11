<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\NewsArticle;
use App\Models\NewsTopic;
use App\News\Repositories\NewsTopicRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NewsTopicRepositoryDuplicateGateTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A candidate title similar (plural/possessive/reworded) to a published
     * article from the last 24h must be suppressed: no article row created,
     * the topic marked 'duplicate_skipped' (a terminal status that recovery
     * paths skip), and no ensure-pass-style dispatch must resurrect it.
     */
    public function test_similar_title_to_recent_published_article_is_suppressed(): void
    {
        // Existing published article for a different topic.
        $existingTopic = NewsTopic::factory()->create([
            'generation_status' => 'generated',
        ]);
        NewsArticle::factory()->create([
            'topic_id' => $existingTopic->id,
            'title' => 'Supreme Court strikes down electoral bond scheme',
            'status' => 'published',
            'published_at' => now()->subHours(2),
        ]);

        // Candidate topic for the duplicate article.
        $candidateTopic = NewsTopic::factory()->create([
            'generation_status' => 'generating',
            'retry_count' => 0,
        ]);

        $repository = app(NewsTopicRepository::class);

        $result = $repository->saveGeneratedArticle(
            topicId: $candidateTopic->id,
            title: "Supreme Court's electoral bonds scheme struck down by judges",
            content: '<p>Article body.</p>',
            provider: 'test',
            model: 'test-model',
            metaTitle: null,
            metaDescription: null,
            metaKeywords: null,
            imageUrl: null,
            thumbnailUrl: null,
            status: 'published',
        );

        // Gate suppressed the save.
        $this->assertNull($result, 'Suppressed duplicate must return null.');
        $this->assertSame(0, NewsArticle::where('topic_id', $candidateTopic->id)->count(), 'No article row should be created for the suppressed topic.');

        // Topic marked duplicate_skipped — terminal status, not a failure.
        $candidateTopic->refresh();
        $this->assertSame('duplicate_skipped', $candidateTopic->generation_status, 'Suppressed topic must be marked duplicate_skipped.');
        // retry_count is not bumped — the topic was not a failure.
        $this->assertSame(0, $candidateTopic->retry_count, 'retry_count must not change for a duplicate skip.');
    }

    /**
     * A genuinely distinct article (overlap < 0.5 — different event that
     * happens to share entity names) must save normally.
     */
    public function test_distinct_title_saves_normally(): void
    {
        $existingTopic = NewsTopic::factory()->create([
            'generation_status' => 'generated',
        ]);
        NewsArticle::factory()->create([
            'topic_id' => $existingTopic->id,
            'title' => 'Supreme Court strikes down electoral bond scheme',
            'status' => 'published',
            'published_at' => now()->subHours(2),
        ]);

        $candidateTopic = NewsTopic::factory()->create([
            'generation_status' => 'generating',
        ]);

        $repository = app(NewsTopicRepository::class);

        $result = $repository->saveGeneratedArticle(
            topicId: $candidateTopic->id,
            title: 'Tech giant unveils new AI accelerator chip for data centers',
            content: '<p>Article body.</p>',
            provider: 'test',
            model: 'test-model',
            metaTitle: null,
            metaDescription: null,
            metaKeywords: null,
            imageUrl: null,
            thumbnailUrl: null,
            status: 'published',
        );

        $this->assertNotNull($result, 'Distinct article must not be suppressed.');
        $this->assertSame(1, NewsArticle::where('topic_id', $candidateTopic->id)->count(), 'Distinct article must be saved.');
        $candidateTopic->refresh();
        $this->assertSame('generated', $candidateTopic->generation_status);
    }

    /**
     * An article similar to one published MORE than 24h ago must save — old
     * coverage does not block fresh coverage of the same event.
     */
    public function test_similar_to_old_published_article_saves_normally(): void
    {
        $existingTopic = NewsTopic::factory()->create([
            'generation_status' => 'generated',
        ]);
        NewsArticle::factory()->create([
            'topic_id' => $existingTopic->id,
            'title' => 'Supreme Court strikes down electoral bond scheme',
            'status' => 'published',
            'published_at' => now()->subDays(2),
        ]);

        $candidateTopic = NewsTopic::factory()->create([
            'generation_status' => 'generating',
        ]);

        $repository = app(NewsTopicRepository::class);

        $result = $repository->saveGeneratedArticle(
            topicId: $candidateTopic->id,
            title: "Supreme Court's electoral bonds scheme struck down",
            content: '<p>Article body.</p>',
            provider: 'test',
            model: 'test-model',
            metaTitle: null,
            metaDescription: null,
            metaKeywords: null,
            imageUrl: null,
            thumbnailUrl: null,
            status: 'published',
        );

        $this->assertNotNull($result, 'Article similar to >24h-old coverage must save.');
        $this->assertSame(1, NewsArticle::where('topic_id', $candidateTopic->id)->count());
    }
}
