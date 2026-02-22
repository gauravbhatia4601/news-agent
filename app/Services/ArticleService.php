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

    public function getLatestArticles(int $perPage = 15, ?string $category = null)
    {
        return $this->articleRepository->paginateLatest($perPage, $category);
    }

    public function getPopularArticles(int $perPage = 15, ?string $category = null)
    {
        return $this->articleRepository->paginatePopular($perPage, $category);
    }

    public function getHeadlines(int $limit = 5, ?string $category = null)
    {
        return $this->articleRepository->getHeadlines($limit, $category);
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
            // Ignore if article doesn't exist
        }
    }

    public function searchArticles(string $keyword, int $perPage = 15, ?string $category = null)
    {
        return $this->articleRepository->search($keyword, $perPage, $category);
    }

    public function getRelatedArticles(int $articleId, string $category, int $limit = 3)
    {
        return $this->articleRepository->getRelated($articleId, $category, $limit);
    }

    public function getFeaturedArticle()
    {
        return $this->articleRepository->getFeatured();
    }
}
