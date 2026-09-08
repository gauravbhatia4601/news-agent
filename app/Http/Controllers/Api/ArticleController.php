<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ArticleListRequest;
use App\Http\Requests\ArticleSearchRequest;
use App\Http\Resources\ArticleDetailResource;
use App\Http\Resources\ArticleResource;
use App\Services\ArticleService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ArticleController extends Controller
{
    public function __construct(
        protected ArticleService $articleService
    ) {}

    public function search(ArticleSearchRequest $request): AnonymousResourceCollection
    {
        $keyword = $request->validated('q');
        $category = $request->validated('category');
        $perPage = (int) $request->validated('per_page', 15);

        $articles = $this->articleService->searchArticles($keyword, $perPage, $category);

        return ArticleResource::collection($articles);
    }

    public function featured(): ArticleDetailResource|\Illuminate\Http\JsonResponse
    {
        $article = $this->articleService->getFeaturedArticle();

        if (! $article) {
            return response()->json(['message' => 'No featured article found'], 404);
        }

        return new ArticleDetailResource($article);
    }

    public function related(string $slug): AnonymousResourceCollection
    {
        $article = $this->articleService->getArticle($slug);

        $relatedArticles = $this->articleService->getRelatedArticles(
            $article->id,
            $article->topic?->categoryRelation?->slug ?? '',
            3
        );

        return ArticleResource::collection($relatedArticles);
    }

    public function index(ArticleListRequest $request): AnonymousResourceCollection
    {
        $category = $request->validated('category');
        $perPage = (int) $request->validated('per_page', 15);

        $articles = $this->articleService->getLatestArticles($perPage, $category);

        return ArticleResource::collection($articles);
    }

    public function popular(ArticleListRequest $request): AnonymousResourceCollection
    {
        $category = $request->validated('category');
        $perPage = (int) $request->validated('per_page', 15);

        $articles = $this->articleService->getPopularArticles($perPage, $category);

        return ArticleResource::collection($articles);
    }

    public function hot(ArticleListRequest $request): AnonymousResourceCollection
    {
        $category = $request->validated('category');
        $perPage = (int) $request->validated('per_page', 15);

        $articles = $this->articleService->getHotArticles($perPage, $category);

        return ArticleResource::collection($articles);
    }

    public function trending(ArticleListRequest $request): AnonymousResourceCollection
    {
        $category = $request->validated('category');
        $limit = (int) $request->validated('per_page', 10);

        $articles = $this->articleService->getTrendingArticles($limit, $category);

        return ArticleResource::collection($articles);
    }

    public function headlines(ArticleListRequest $request): AnonymousResourceCollection
    {
        $category = $request->validated('category');
        $limit = (int) $request->validated('per_page', 5);

        $articles = $this->articleService->getHeadlines($limit, $category);

        return ArticleResource::collection($articles);
    }

    public function show(string $slug): ArticleDetailResource
    {
        $this->articleService->incrementArticleViews($slug);

        $article = $this->articleService->getArticle($slug);

        return new ArticleDetailResource($article);
    }
}
