<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $category = $this->resolveCategory();

        // Latest timeline entry = newest StoryUpdate. Prefer the eager-loaded
        // latestUpdate (listing endpoints); fall back to the updates relation.
        $latestUpdate = null;
        if ($this->resource->relationLoaded('latestUpdate')) {
            $update = $this->latestUpdate;
        } else {
            $update = $this->resource->relationLoaded('updates')
                ? $this->updates->sortByDesc('event_at')->first()
                : null;
        }
        if ($update !== null) {
            $latestUpdate = [
                'content' => $update->content,
                'event_at' => $update->event_at?->toIso8601String(),
            ];
        }

        // Discrete timeline entry count — prefer the eager-loaded count column.
        $updateCount = isset($this->resource->updates_count)
            ? $this->resource->updates_count
            : ($this->resource->relationLoaded('updates') ? $this->updates->count() : $this->updates()->count());

        // Supporting article count (kept for listings that still want it).
        $articleCount = isset($this->resource->articles_count)
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
            'article_count' => $articleCount,
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
