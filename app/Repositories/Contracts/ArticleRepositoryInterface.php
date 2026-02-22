<?php

namespace App\Repositories\Contracts;

interface ArticleRepositoryInterface
{
    public function getCategories(): array;
    public function paginateLatest(int $perPage = 15, ?string $category = null);
    public function paginatePopular(int $perPage = 15, ?string $category = null);
    public function getHeadlines(int $limit = 5, ?string $category = null);
    public function findBySlug(string $slug);
    public function incrementViews(string $slug): void;
    
    public function search(string $keyword, int $perPage = 15, ?string $category = null);
    public function getRelated(int $articleId, string $category, int $limit = 3);
    public function getFeatured();
}
