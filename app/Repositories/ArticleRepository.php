<?php

namespace App\Repositories;

use App\Models\NewsArticle;
use App\Models\NewsTopic;
use App\Repositories\Contracts\ArticleRepositoryInterface;

class ArticleRepository implements ArticleRepositoryInterface
{
    public function getCategories(): array
    {
        return NewsTopic::query()->distinct()->pluck('category')->toArray();
    }

    public function paginateLatest(int $perPage = 15, ?string $category = null)
    {
        $query = NewsArticle::with(['topic.sources'])->latest();
        
        if ($category) {
            $query->whereHas('topic', function ($q) use ($category) {
                $q->where('category', $category);
            });
        }
        
        return $query->paginate($perPage);
    }

    public function paginatePopular(int $perPage = 15, ?string $category = null)
    {
        $query = NewsArticle::with(['topic.sources'])->orderByDesc('views')->latest();
        
        if ($category) {
            $query->whereHas('topic', function ($q) use ($category) {
                $q->where('category', $category);
            });
        }
        
        return $query->paginate($perPage);
    }

    public function getHeadlines(int $limit = 5, ?string $category = null)
    {
        $query = NewsArticle::with(['topic.sources'])->latest();
        
        if ($category) {
            $query->whereHas('topic', function ($q) use ($category) {
                $q->where('category', $category);
            });
        }
        
        return $query->take($limit)->get();
    }

    public function findBySlug(string $slug)
    {
        return NewsArticle::with(['topic.sources'])->where('slug', $slug)->firstOrFail();
    }

    public function incrementViews(string $slug): void
    {
        NewsArticle::where('slug', $slug)->increment('views');
    }

    public function search(string $keyword, int $perPage = 15, ?string $category = null)
    {
        $query = NewsArticle::with(['topic.sources'])
            ->where(function ($q) use ($keyword) {
                $q->where('title', 'ilike', '%' . $keyword . '%')
                  ->orWhere('content', 'ilike', '%' . $keyword . '%');
            })
            ->latest();

        if ($category) {
            $query->whereHas('topic', function ($q) use ($category) {
                $q->where('category', $category);
            });
        }

        return $query->paginate($perPage);
    }

    public function getRelated(int $articleId, string $category, int $limit = 3)
    {
        return NewsArticle::with(['topic.sources'])
            ->where('id', '!=', $articleId)
            ->whereHas('topic', function ($q) use ($category) {
                $q->where('category', $category);
            })
            ->inRandomOrder()
            ->take($limit)
            ->get();
    }

    public function getFeatured()
    {
        // Get the most viewed article from the last 48 hours as the featured one
        return NewsArticle::with(['topic.sources'])
            ->where('created_at', '>=', now()->subHours(48))
            ->orderByDesc('views')
            ->first() ?? NewsArticle::with(['topic.sources'])->latest()->first();
    }
}
