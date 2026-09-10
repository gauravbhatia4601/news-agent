<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\NewsArticle;
use App\Models\NewsTopic;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DedupeArticlesCommandTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Dry-run lists the duplicate but changes nothing in the database.
     */
    public function test_dry_run_lists_duplicate_but_changes_nothing(): void
    {
        [$dupId, $repId, $distinctId] = $this->seedArticles();

        $this->artisan('news:dedupe-articles')
            ->assertSuccessful()
            ->expectsOutputToContain("Would draft article id={$dupId}");

        // Nothing changed — all three still published.
        $this->assertSame('published', NewsArticle::find($dupId)->status);
        $this->assertSame('published', NewsArticle::find($repId)->status);
        $this->assertSame('published', NewsArticle::find($distinctId)->status);
    }

    /**
     * --apply drafts exactly the duplicate, keeps the representative, and
     * leaves the distinct article published.
     */
    public function test_apply_drafts_duplicate_keeps_representative_and_distinct(): void
    {
        [$dupId, $repId, $distinctId] = $this->seedArticles();

        $this->artisan('news:dedupe-articles --apply')
            ->assertSuccessful();

        // Duplicate drafted.
        $this->assertSame('draft', NewsArticle::find($dupId)->status, 'Duplicate article should be drafted.');
        // Representative stays published.
        $this->assertSame('published', NewsArticle::find($repId)->status, 'Representative should stay published.');
        // Distinct article untouched.
        $this->assertSame('published', NewsArticle::find($distinctId)->status, 'Distinct article should stay published.');
    }

    /**
     * Seed three articles: two near-duplicate (same headline, different
     * source_count so the representative is deterministic) and one distinct.
     * Returns [dupId, repId, distinctId].
     *
     * @return array{0: int, 1: int, 2: int}
     */
    private function seedArticles(): array
    {
        // Representative: higher source_count, earlier published_at.
        $repTopic = NewsTopic::create([
            'category' => 'national',
            'topic_name' => 'Parliament passes election reform bill',
            'topic_signature' => 'sig-rep-'.uniqid(),
            'source_count' => 3,
            'generation_status' => 'generated',
        ]);
        $rep = NewsArticle::create([
            'topic_id' => $repTopic->id,
            'title' => 'Parliament passes landmark election reform bill in historic vote',
            'content' => '<p>Body</p>',
            'slug' => 'rep-'.uniqid(),
            'status' => 'published',
            'published_at' => now()->subHours(2),
        ]);

        // Duplicate: same headline drift (plural/possessive) → similar() true.
        // Lower source_count so it loses the representative race.
        $dupTopic = NewsTopic::create([
            'category' => 'national',
            'topic_name' => 'Parliament passes election reform bill',
            'topic_signature' => 'sig-dup-'.uniqid(),
            'source_count' => 1,
            'generation_status' => 'generated',
        ]);
        $dup = NewsArticle::create([
            'topic_id' => $dupTopic->id,
            'title' => "Parliament's election reform bills pass in historic vote",
            'content' => '<p>Body</p>',
            'slug' => 'dup-'.uniqid(),
            'status' => 'published',
            'published_at' => now()->subHour(),
        ]);

        // Distinct: completely unrelated headline.
        $distinctTopic = NewsTopic::create([
            'category' => 'technology',
            'topic_name' => 'New AI chip announced',
            'topic_signature' => 'sig-distinct-'.uniqid(),
            'source_count' => 2,
            'generation_status' => 'generated',
        ]);
        $distinct = NewsArticle::create([
            'topic_id' => $distinctTopic->id,
            'title' => 'Tech giant unveils new AI accelerator chip for data centers',
            'content' => '<p>Body</p>',
            'slug' => 'distinct-'.uniqid(),
            'status' => 'published',
            'published_at' => now()->subHours(3),
        ]);

        // source_count on the topic row drives the representative race
        // (news_articles has no source_count column — joined from news_topics).
        // repTopic: source_count=3 (wins), dupTopic: source_count=1 (loses).

        return [$dup->id, $rep->id, $distinct->id];
    }
}
