<?php

namespace App\Services;

use App\Http\Resources\ArticleDetailResource;
use App\Http\Resources\ArticleResource;
use App\Http\Resources\StoryResource;
use App\Models\Category;
use App\Models\NewsArticle;
use App\Models\Story;
use Illuminate\Support\Facades\Cache;

/**
 * Batched homepage feed. One Redis-cached payload serves everything the
 * homepage used to fetch in 16 separate HTTP calls.
 *
 * The scheduler (news:warm-home-feed, every 60s) is the ONLY writer:
 * user requests are pure cache reads, never trigger SQL. A cold cache
 * (deploy/restart) falls back to building inline once.
 */
class HomeFeedService
{
    public const CACHE_KEY = 'news-engine:home-feed';

    // Longer than the 60s scheduler refresh so the key never expires
    // between ticks — no expiry cliff, no cache stampede.
    public const CACHE_TTL = 120;

    private const HEADLINES_PER_CATEGORY = 5;

    private const STORY_LIMIT = 5;

    private const HOT_LIMIT = 7;

    public function warm(): void
    {
        Cache::put(self::CACHE_KEY, $this->build(), now()->addSeconds(self::CACHE_TTL));
    }

    public function get(): array
    {
        $feed = Cache::get(self::CACHE_KEY);

        if (is_array($feed) && $feed !== []) {
            return $feed;
        }

        // Cold cache (deploy/restart): build inline once; scheduler takes over.
        $feed = $this->build();
        Cache::put(self::CACHE_KEY, $feed, now()->addSeconds(self::CACHE_TTL));

        return $feed;
    }

    private function build(): array
    {
        $eagerLoad = ['topic.categoryRelation.parent', 'topic.locationCategory', 'topic.sources'];

        $featured = NewsArticle::with($eagerLoad)
            ->where('status', 'published')
            ->where('published_at', '>=', now()->subHours((float) config('news-engine.listings.hero_window_hours', 24.0)))
            ->orderByDesc('momentum_score')
            ->orderByDesc('published_at')
            ->first()
            ?? NewsArticle::with($eagerLoad)
                ->where('status', 'published')
                ->latest('published_at')
                ->first();

        $hot = NewsArticle::with($eagerLoad)
            ->where('status', 'published')
            ->where('published_at', '>=', now()->subHours((float) config('news-engine.listings.top_stories_window_hours', 48.0)))
            ->orderByDesc('hot_score')
            ->orderByDesc('published_at')
            ->take(self::HOT_LIMIT)
            ->get();

        $categories = Category::with(['children' => fn ($q) => $q->orderBy('display_order')])
            ->whereNull('parent_id')
            ->orderBy('display_order')
            ->get();

        $headlines = [];
        foreach ($categories as $category) {
            $headlines[$category->id] = $this->headlinesForCategory((string) $category->slug);
        }

        $stories = Story::query()
            ->with(['category', 'latestUpdate'])
            ->withCount(['updates'])
            ->active()
            ->orderByDesc('started_at')
            ->limit(self::STORY_LIMIT)
            ->get();

        // Serialize through the same resources the old endpoints used, so the
        // frontend payload shapes are byte-for-byte compatible. (JsonResource
        // toArray in a console context needs a plain Request instance.)
        $request = request();

        return [
            'featured' => $featured ? (new ArticleDetailResource($featured))->toArray($request) : null,
            'hot' => ArticleResource::collection($hot)->toArray($request),
            'categories' => $categories
                ->map(fn (Category $category) => [
                    'id' => $category->id,
                    'name' => $category->name,
                    'slug' => $category->slug,
                    'parent_id' => $category->parent_id,
                    'children' => $category->children->map(fn (Category $child) => [
                        'id' => $child->id,
                        'name' => $child->name,
                        'slug' => $child->slug,
                        'parent_id' => $child->parent_id,
                    ])->values()->all(),
                    'headlines' => ArticleResource::collection($headlines[$category->id] ?? collect())->toArray($request),
                ])->values()->all(),
            'stories' => StoryResource::collection($stories)->toArray($request),
            'generated_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Per-category latest headlines. India + state slugs share the
     * location-category filter used by the old /headlines endpoint
     * (mirrors ArticleRepository::applyCategoryFilter).
     */
    private function headlinesForCategory(string $categorySlug): \Illuminate\Support\Collection
    {
        $india = Category::where('slug', 'india')->whereNull('parent_id')->first();
        $indiaStateIds = $india
            ? Category::where('parent_id', $india->id)->pluck('id')->toArray()
            : [];

        $query = NewsArticle::with(['topic.categoryRelation.parent', 'topic.locationCategory', 'topic.sources'])
            ->where('status', 'published')
            ->latest('published_at');

        if ($categorySlug === 'india' || in_array($categorySlug, Category::whereIn('id', $indiaStateIds)->pluck('slug')->toArray(), true)) {
            $locationIds = $categorySlug === 'india'
                ? $indiaStateIds
                : Category::where('slug', $categorySlug)->pluck('id')->toArray();

            if ($locationIds !== []) {
                $query->whereHas('topic', fn ($q) => $q->whereIn('location_category_id', $locationIds));
            }

            return $query->take(self::HEADLINES_PER_CATEGORY)->get();
        }

        $categoryIds = Category::where('slug', $categorySlug)
            ->orWhereHas('parent', fn ($q) => $q->where('slug', $categorySlug))
            ->orWhereHas('children', fn ($q) => $q->where('slug', $categorySlug))
            ->pluck('id');

        if ($categoryIds->isEmpty()) {
            return collect();
        }

        return NewsArticle::with(['topic.categoryRelation.parent', 'topic.locationCategory', 'topic.sources'])
            ->where('status', 'published')
            ->whereHas('topic.categoryRelation', fn ($q) => $q->whereIn('categories.id', $categoryIds))
            ->latest('published_at')
            ->take(self::HEADLINES_PER_CATEGORY)
            ->get();
    }
}