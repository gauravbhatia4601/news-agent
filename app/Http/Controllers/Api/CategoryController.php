<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ArticleService;
use Illuminate\Http\JsonResponse;

class CategoryController extends Controller
{
    public function __construct(
        protected ArticleService $articleService
    ) {}

    public function index(): JsonResponse
    {
        return response()->json([
            'data' => $this->articleService->getCategoryTree(),
        ]);
    }
}
