<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\NewsArticle;
use App\News\Repositories\NewsTopicRepository;
use App\News\Services\NewsArticleGenerationService;
use App\Services\AuditLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ArticleController extends Controller
{
    public function __construct(
        private readonly NewsTopicRepository $topicRepository,
        private readonly NewsArticleGenerationService $generationService,
    ) {}

    private function applyLocationCategoryFilter($query, string $slug): void
    {
        $india = Category::where('slug', 'india')->whereNull('parent_id')->first();
        if (! $india) {
            return;
        }

        if ($slug === 'india') {
            $stateIds = Category::where('parent_id', $india->id)->pluck('id');
            $query->whereHas('topic', fn ($q) => $q->whereIn('location_category_id', $stateIds));

            return;
        }

        $state = Category::where('slug', $slug)->where('parent_id', $india->id)->first();
        if ($state) {
            $query->whereHas('topic', fn ($q) => $q->where('location_category_id', $state->id));
        }
    }

    public function index(Request $request): JsonResponse
    {
        $query = NewsArticle::with('topic.categoryRelation', 'topic.locationCategory', 'topic.sources');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('category')) {
            $slug = $request->category;

            $india = Category::where('slug', 'india')->whereNull('parent_id')->first();
            $isState = $india && Category::where('slug', $slug)->where('parent_id', $india->id)->exists();

            if ($isState || $slug === 'india') {
                $this->applyLocationCategoryFilter($query, $slug);
            } else {
                $query->whereHas('topic.categoryRelation', fn ($q) => $q->where('slug', $slug));
            }
        }

        if ($request->filled('search')) {
            $query->where('title', 'ilike', '%'.$request->search.'%');
        }

        $sortBy = $request->get('sort_by', 'created_at');
        $sortDir = $request->get('sort_dir', 'desc');
        $allowedSorts = ['created_at', 'title', 'views', 'updated_at', 'word_count', 'generation_duration_seconds'];
        if (! in_array($sortBy, $allowedSorts)) {
            $sortBy = 'created_at';
        }
        if (! in_array($sortDir, ['asc', 'desc'])) {
            $sortDir = 'desc';
        }

        $perPage = $request->integer('per_page', 20);
        $articles = $query->orderBy($sortBy, $sortDir)->paginate(min($perPage, 100));

        return response()->json($articles->through(fn ($a) => [
            'id' => $a->id,
            'slug' => $a->slug,
            'title' => $a->title,
            'status' => $a->status,
            'category' => $a->topic?->categoryRelation?->name,
            'category_slug' => $a->topic?->categoryRelation?->slug,
            'location' => $a->topic?->locationCategory ? [
                'id' => $a->topic->locationCategory->id,
                'name' => $a->topic->locationCategory->name,
                'slug' => $a->topic->locationCategory->slug,
            ] : null,
            'author' => $a->metadata['author'] ?? 'AI News Desk',
            'read_time_minutes' => $a->metadata['read_time_minutes'] ?? 1,
            'views' => $a->views,
            'word_count' => str_word_count(strip_tags($a->content)),
            'model' => $a->model,
            'generation_duration_seconds' => $a->generation_duration_seconds,
            'meta_title' => $a->meta_title,
            'quality_report' => $a->quality_report,
            'created_at' => $a->created_at->toIso8601String(),
            'updated_at' => $a->updated_at->toIso8601String(),
        ]));
    }

    public function show(int $id): JsonResponse
    {
        $article = NewsArticle::with('topic.categoryRelation', 'topic.sources')->findOrFail($id);

        return response()->json([
            'data' => [
                'id' => $article->id,
                'slug' => $article->slug,
                'title' => $article->title,
                'content' => $article->content,
                'status' => $article->status,
                'provider' => $article->provider,
                'model' => $article->model,
                'meta_title' => $article->meta_title,
                'meta_description' => $article->meta_description,
                'meta_keywords' => $article->meta_keywords,
                'image_url' => $article->image_url,
                'thumbnail_url' => $article->thumbnail_url,
                'quality_report' => $article->quality_report,
                'metadata' => $article->metadata,
                'views' => $article->views,
                'category' => $article->topic?->categoryRelation?->name,
                'category_slug' => $article->topic?->categoryRelation?->slug,
                'topic_name' => $article->topic?->topic_name,
                'sources' => $article->topic?->sources?->map(fn ($s) => [
                    'id' => $s->id,
                    'source_name' => $s->source_name,
                    'source_url' => $s->source_url,
                    'headline' => $s->headline,
                    'summary' => $s->summary,
                ]),
                'created_at' => $article->created_at->toIso8601String(),
                'updated_at' => $article->updated_at->toIso8601String(),
            ],
        ]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $article = NewsArticle::findOrFail($id);

        $validated = $request->validate([
            'title' => 'sometimes|string|max:250',
            'status' => 'sometimes|in:published,draft,archived',
            'meta_title' => 'sometimes|nullable|string|max:255',
            'meta_description' => 'sometimes|nullable|string|max:500',
        ]);

        $article->update($validated);

        AuditLogService::log('update', 'Article', $id);

        return response()->json([
            'data' => ['id' => $article->id, 'slug' => $article->slug, 'status' => $article->status],
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $article = NewsArticle::findOrFail($id);
        $article->delete();

        AuditLogService::log('delete', 'Article', $id);

        return response()->json(['message' => 'Article deleted']);
    }

    public function regenerate(int $id): JsonResponse
    {
        $article = NewsArticle::with('topic')->findOrFail($id);
        $topic = $article->topic;

        if (! $topic) {
            return response()->json(['message' => 'No topic found for this article'], 404);
        }

        $topic->update(['generation_status' => 'pending', 'retry_count' => 0]);

        // Keep the existing article until the new generation succeeds.
        // saveGeneratedArticle() replaces the article in place by topic_id
        // when generation completes, so deletion at dispatch time would risk
        // losing the published article permanently if generation fails.
        \App\Jobs\GenerateArticle::dispatch($topic->topic_signature);

        AuditLogService::log('regenerate', 'Article', $id);

        return response()->json(['message' => 'Regeneration dispatched', 'topic_signature' => $topic->topic_signature]);
    }

    public function batch(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|exists:news_articles,id',
            'action' => 'required|in:delete,regenerate,publish,draft,archive',
        ]);

        $ids = $validated['ids'];
        $action = $validated['action'];

        AuditLogService::log('batch', 'Article', null, ['ids' => $validated['ids'], 'action' => $validated['action']]);

        $articles = NewsArticle::whereIn('id', $ids)->get();

        if ($action === 'delete') {
            NewsArticle::whereIn('id', $ids)->delete();

            return response()->json(['message' => count($ids).' articles deleted']);
        }

        if ($action === 'regenerate') {
            $count = 0;
            foreach ($articles as $article) {
                $topic = $article->topic;
                if (! $topic) {
                    continue;
                }
                $topic->update(['generation_status' => 'pending', 'retry_count' => 0]);
                \App\Jobs\GenerateArticle::dispatch($topic->topic_signature);
                $count++;
            }

            return response()->json(['message' => $count.' articles queued for regeneration']);
        }

        $statusMap = ['publish' => 'published', 'draft' => 'draft', 'archive' => 'archived'];
        $newStatus = $statusMap[$action];
        NewsArticle::whereIn('id', $ids)->update(['status' => $newStatus]);

        return response()->json(['message' => count($ids).' articles set to '.$newStatus]);
    }

    public function removeImage(int $id): JsonResponse
    {
        $article = NewsArticle::with('topic.categoryRelation', 'topic.sources')->findOrFail($id);

        // Best-effort file deletion — only for locally-stored images.
        if ($article->image_url && str_starts_with($article->image_url, '/storage/news-images/')) {
            $path = substr($article->image_url, strlen('/storage/'));
            try {
                Storage::disk('public')->delete($path);
            } catch (\Throwable $e) {
                \Log::warning('Failed to delete article image file.', [
                    'article_id' => $id,
                    'path' => $path,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $article->image_url = null;
        $article->thumbnail_url = null;
        $metadata = $article->metadata ?? [];
        $metadata['image_origin'] = null;
        $article->metadata = $metadata;
        $article->save();

        AuditLogService::log('remove_image', 'Article', $id);

        return response()->json([
            'data' => [
                'id' => $article->id,
                'slug' => $article->slug,
                'title' => $article->title,
                'content' => $article->content,
                'status' => $article->status,
                'provider' => $article->provider,
                'model' => $article->model,
                'meta_title' => $article->meta_title,
                'meta_description' => $article->meta_description,
                'meta_keywords' => $article->meta_keywords,
                'image_url' => $article->image_url,
                'thumbnail_url' => $article->thumbnail_url,
                'quality_report' => $article->quality_report,
                'metadata' => $article->metadata,
                'views' => $article->views,
                'category' => $article->topic?->categoryRelation?->name,
                'category_slug' => $article->topic?->categoryRelation?->slug,
                'topic_name' => $article->topic?->topic_name,
                'sources' => $article->topic?->sources?->map(fn ($s) => [
                    'id' => $s->id,
                    'source_name' => $s->source_name,
                    'source_url' => $s->source_url,
                    'headline' => $s->headline,
                    'summary' => $s->summary,
                ]),
                'created_at' => $article->created_at->toIso8601String(),
                'updated_at' => $article->updated_at->toIso8601String(),
            ],
        ]);
    }
}
