<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $category = $this->resolveCategory();

        // Latest update = newest published article linked to this story.
        // Prefer the eager-loaded latestArticle (listing endpoints); fall back to
        // the sorted articles relation (show endpoint) or a query last.
        $latestUpdate = null;
        $latest = null;
        if ($this->resource->relationLoaded('latestArticle')) {
            $latest = $this->latestArticle;
        } elseif ($this->resource->relationLoaded('articles')) {
            $latest = $this->articles
                ->sortByDesc(fn ($a) => $a->published_at ?? $a->created_at)
                ->first();
        }
        if ($latest !== null) {
            $latestUpdate = (new ArticleResource($latest))->toArray($request);
        }

        $updateCount = isset($this->resource->articles_count)
            ? $this->resource->articles_count
            : ($this->resource->relationLoaded('articles') ? $this->articles->count() : $this->articles()->count());

        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'title' => $this->title,
            'description' => $this->description,
            'category' => $category,
            'urgency' => $this->urgency,
            'status' => $this->status,
            'started_at' => $this->started_at?->toIso8601String(),
            'concluded_at' => $this->concluded_at?->toIso8601String(),
            'last_monitored_at' => $this->last_monitored_at?->toIso8601String(),
            'update_count' => $updateCount,
            'latest_update' => $latestUpdate,
        ];
    }

    /**
     * Resolve the story category — mirrors ArticleResource's category resolution.
     */
    private function resolveCategory(): ?array
    {
        if (! $this->relationLoaded('category') || ! $this->category instanceof \App\Models\Category) {
            return null;
        }

        return [
            'id' => $this->category->id,
            'name' => $this->category->name,
            'slug' => $this->category->slug,
            'parent_id' => $this->category->parent_id,
        ];
    }
}
