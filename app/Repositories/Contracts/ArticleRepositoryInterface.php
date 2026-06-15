<?php

namespace App\Repositories\Contracts;

interface ArticleRepositoryInterface
{
    public function getCategoryTree(): array;
    public function findBySlug(string $slug);
    public function incrementViews(string $slug): void;
    public function getFeatured();
    public function paginateLatest(int $perPage = 15, ?string $categorySlug = null);
    public function paginatePopular(int $perPage = 15, ?string $categorySlug = null);
    public function paginateHot(int $perPage = 15, ?string $categorySlug = null);
    public function getTrending(int $limit = 10, ?string $categorySlug = null);
    public function getHeadlines(int $limit = 5, ?string $categorySlug = null);
    public function search(string $keyword, int $perPage = 15, ?string $categorySlug = null);
    public function getRelated(int $articleId, string $categorySlug, int $limit = 3);
    public function getCategories(): array;
    public function getCategoryBySlug(string $slug): ?array;
}
