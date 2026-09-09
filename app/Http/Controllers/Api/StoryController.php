<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ArticleResource;
use App\Http\Resources\StoryResource;
use App\Http\Resources\StoryUpdateResource;
use App\Models\NewsArticle;
use App\Models\Story;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class StoryController extends Controller
{
    /**
     * List active stories, paginated, optional urgency filter.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Story::query()
            ->with(['category', 'latestArticle', 'latestUpdate'])
            ->withCount(['articles', 'updates']);

        $urgency = $request->filled('urgency') ? $request->string('urgency')->toString() : null;
        if (in_array($urgency, ['live', 'developing', 'ongoing', 'concluded'], true)) {
            // Explicit urgency filter — includes concluded when requested.
            $query->where('urgency', $urgency);
        } else {
            // Default: only active (non-concluded) stories.
            $query->active();
        }

        $perPage = min($request->integer('per_page', 12), 50);
        $stories = $query->orderByDesc('started_at')->paginate($perPage);

        return StoryResource::collection($stories);
    }

    /**
     * Show a single story by slug.
     */
    public function show(string $slug): JsonResponse
    {
        $story = Story::with(['category', 'latestArticle', 'latestUpdate'])
            ->withCount(['articles', 'updates'])
            ->where('slug', $slug)
            ->firstOrFail();

        return response()->json([
            'data' => (new StoryResource($story))->toArray(request()),
        ]);
    }

    /**
     * Paginated reverse-chron timeline of discrete updates for a story.
     */
    public function timeline(string $slug, Request $request): AnonymousResourceCollection
    {
        $story = Story::where('slug', $slug)->firstOrFail();

        $perPage = min($request->integer('per_page', 20), 100);
        $updates = $story->updates()
            ->orderByDesc('event_at')
            ->paginate($perPage);

        return StoryUpdateResource::collection($updates);
    }

    /**
     * Paginated reverse-chron supporting articles for a story.
     */
    public function articles(string $slug, Request $request): AnonymousResourceCollection
    {
        $story = Story::where('slug', $slug)->firstOrFail();

        $perPage = min($request->integer('per_page', 9), 50);
        $articles = NewsArticle::where('story_id', $story->id)
            ->where('status', 'published')
            ->with('topic.categoryRelation', 'topic.locationCategory')
            ->orderByDesc('published_at')
            ->paginate($perPage);

        return ArticleResource::collection($articles);
    }
}
