<?php

namespace App\Services;

use App\Repositories\Contracts\ArticleRepositoryInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class ArticleService
{
    public function __construct(
        protected ArticleRepositoryInterface $articleRepository
    ) {}

    public function getCategories(): array
    {
        return $this->articleRepository->getCategories();
    }

    public function getCategoryTree(): array
    {
        return $this->articleRepository->getCategoryTree();
    }

    public function getCategoryBySlug(string $slug): ?array
    {
        return $this->articleRepository->getCategoryBySlug($slug);
    }

    public function getLatestArticles(int $perPage = 15, ?string $categorySlug = null)
    {
        return $this->articleRepository->paginateLatest($perPage, $categorySlug);
    }

    public function getPopularArticles(int $perPage = 15, ?string $categorySlug = null)
    {
        return $this->articleRepository->paginatePopular($perPage, $categorySlug);
    }

    public function getTrendingArticles(int $limit = 10, ?string $categorySlug = null)
    {
        return $this->articleRepository->getTrending($limit, $categorySlug);
    }

    public function getArticle(string $slug)
    {
        return $this->articleRepository->findBySlug($slug);
    }

    public function incrementArticleViews(string $slug): void
    {
        try {
            $this->articleRepository->incrementViews($slug);
        } catch (ModelNotFoundException $e) {
        }
    }

    public function searchArticles(string $keyword, int $perPage = 15, ?string $categorySlug = null)
    {
        return $this->articleRepository->search($keyword, $perPage, $categorySlug);
    }

    public function getRelatedArticles(int $articleId, string $categorySlug, int $limit = 3)
    {
        return $this->articleRepository->getRelated($articleId, $categorySlug, $limit);
    }
}
