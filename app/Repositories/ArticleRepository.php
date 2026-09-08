<?php

namespace App\Repositories;

use App\Models\Category;
use App\Models\NewsArticle;
use App\Models\NewsTopic;
use App\Repositories\Contracts\ArticleRepositoryInterface;
use Illuminate\Support\Facades\DB;

class ArticleRepository implements ArticleRepositoryInterface
{
    private function applyCategoryFilter($query, ?string $categorySlug): void
    {
        if (! $categorySlug) {
            return;
        }

        $india = Category::where('slug', 'india')->whereNull('parent_id')->first();
        $indiaStateIds = $india
            ? Category::where('parent_id', $india->id)->pluck('id')->toArray()
            : [];

        $isIndiaOrState = in_array($categorySlug, array_merge(
            ['india'],
            Category::whereIn('id', $indiaStateIds)->pluck('slug')->toArray()
        ));

        if ($isIndiaOrState) {
            $locationIds = $categorySlug === 'india'
                ? $indiaStateIds
                : Category::where('slug', $categorySlug)->pluck('id')->toArray();

            $query->whereHas('topic', fn ($q) => $q->whereIn('location_category_id', $locationIds));

            return;
        }

        $categoryIds = Category::where('slug', $categorySlug)
            ->orWhereHas('parent', fn ($q) => $q->where('slug', $categorySlug))
            ->orWhereHas('children', fn ($q) => $q->where('slug', $categorySlug))
            ->pluck('id');

        if ($categoryIds->isEmpty()) {
            $query->whereHas('topic', fn ($q) => $q->whereRaw('1 = 0'));

            return;
        }

        $query->whereHas('topic', fn ($q) => $q->whereIn('category_id', $categoryIds));
    }

    public function getCategories(): array
    {
        return NewsTopic::query()->distinct()->pluck('category')->toArray();
    }

    public function getCategoryTree(): array
    {
        return Category::with(['children' => fn ($q) => $q->orderBy('display_order')])
            ->whereNull('parent_id')
            ->orderBy('display_order')
            ->get()
            ->toArray();
    }

    public function getCategoryBySlug(string $slug): ?array
    {
        $category = Category::where('slug', $slug)->first();
        if (! $category) {
            return null;
        }

        return [
            'id' => $category->id,
            'name' => $category->name,
            'slug' => $category->slug,
            'description' => $category->description,
            'parent_id' => $category->parent_id,
            'parent' => $category->parent ? [
                'id' => $category->parent->id,
                'name' => $category->parent->name,
                'slug' => $category->parent->slug,
            ] : null,
            'children' => $category->children()
                ->orderBy('display_order')
                ->get()
                ->map(fn (Category $child) => [
                    'id' => $child->id,
                    'name' => $child->name,
                    'slug' => $child->slug,
                    'description' => $child->description,
                ])
                ->toArray(),
        ];
    }

    public function paginateLatest(int $perPage = 15, ?string $categorySlug = null)
    {
        $query = NewsArticle::with(['topic.categoryRelation.parent', 'topic.locationCategory', 'topic.sources'])
            ->where('status', 'published')
            ->latest();

        $this->applyCategoryFilter($query, $categorySlug);

        return $query->paginate($perPage);
    }

    public function paginatePopular(int $perPage = 15, ?string $categorySlug = null)
    {
        $query = NewsArticle::with(['topic.categoryRelation.parent', 'topic.locationCategory', 'topic.sources'])
            ->where('status', 'published')
            ->orderByDesc('views')->latest();

        $this->applyCategoryFilter($query, $categorySlug);

        return $query->paginate($perPage);
    }

    public function paginateHot(int $perPage = 15, ?string $categorySlug = null)
    {
        $query = NewsArticle::with(['topic.categoryRelation.parent', 'topic.locationCategory', 'topic.sources'])
            ->where('status', 'published')
            ->orderByDesc('hot_score');

        $this->applyCategoryFilter($query, $categorySlug);

        return $query->paginate($perPage);
    }

    public function getTrending(int $limit = 10, ?string $categorySlug = null)
    {
        $minMomentum = (float) config('news-engine.ranking.trending_min_momentum', 5.0);
        $maxAgeHours = (float) config('news-engine.ranking.trending_max_age_hours', 72.0);
        $ageCutoff = now()->subHours($maxAgeHours);

        $query = NewsArticle::with(['topic.categoryRelation.parent', 'topic.locationCategory', 'topic.sources'])
            ->where('status', 'published')
            ->where('momentum_score', '>', $minMomentum)
            ->where('published_at', '>=', $ageCutoff)
            ->orderByDesc('momentum_score');

        $this->applyCategoryFilter($query, $categorySlug);

        // Deliberately NO backfill: trending must stay pure momentum. When nothing
        // is surging, this returns empty and the frontend falls back to latest.
        return $query->take($limit)->get();
    }

    public function getHeadlines(int $limit = 5, ?string $categorySlug = null)
    {
        $query = NewsArticle::with(['topic.categoryRelation.parent', 'topic.locationCategory', 'topic.sources'])
            ->where('status', 'published')
            ->latest();

        $this->applyCategoryFilter($query, $categorySlug);

        return $query->take($limit)->get();
    }

    public function findBySlug(string $slug)
    {
        return NewsArticle::with(['topic.categoryRelation.parent', 'topic.locationCategory', 'topic.sources'])
            ->where('status', 'published')
            ->where('slug', $slug)
            ->firstOrFail();
    }

    public function incrementViews(string $slug): void
    {
        $article = NewsArticle::where('slug', $slug)->first();
        if (! $article) {
            return;
        }

        $article->increment('views');

        DB::table('article_views')->insert([
            'article_id' => $article->id,
            'viewed_at' => now(),
        ]);
    }

    public function search(string $keyword, int $perPage = 15, ?string $categorySlug = null)
    {
        $query = NewsArticle::with(['topic.categoryRelation.parent', 'topic.locationCategory', 'topic.sources'])
            ->where('status', 'published')
            ->where(function ($q) use ($keyword) {
                $q->where('title', 'ilike', '%'.$keyword.'%')
                    ->orWhere('content', 'ilike', '%'.$keyword.'%');
            })
            ->latest();

        $this->applyCategoryFilter($query, $categorySlug);

        return $query->paginate($perPage);
    }

    public function getRelated(int $articleId, string $categorySlug, int $limit = 3)
    {
        $categoryIds = Category::where('slug', $categorySlug)
            ->orWhereHas('parent', fn ($q) => $q->where('slug', $categorySlug))
            ->pluck('id');

        return NewsArticle::with(['topic.categoryRelation.parent', 'topic.locationCategory', 'topic.sources'])
            ->where('status', 'published')
            ->where('id', '!=', $articleId)
            ->when(
                $categoryIds->isNotEmpty(),
                fn ($q) => $q->whereHas('topic', fn ($q2) => $q2->whereIn('category_id', $categoryIds))
            )
            ->inRandomOrder()
            ->take($limit)
            ->get();
    }

    public function getFeatured()
    {
        return NewsArticle::with(['topic.categoryRelation.parent', 'topic.locationCategory', 'topic.sources'])
            ->where('status', 'published')
            ->where('created_at', '>=', now()->subHours(48))
            ->orderByDesc('views')
            ->first() ?? NewsArticle::with(['topic.categoryRelation.parent', 'topic.locationCategory', 'topic.sources'])->latest()->first();
    }
}
