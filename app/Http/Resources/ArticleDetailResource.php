<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ArticleDetailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $cat = $this->resolveCategory();

        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'title' => $this->title,
            'category' => $cat,
            'location' => $this->resolveLocation(),
            'topic_name' => $this->topic?->topic_name,
            'content' => $this->content,
            'views' => $this->views,
            'meta_title' => $this->meta_title ?? $this->title,
            'meta_description' => $this->meta_description,
            'meta_keywords' => $this->meta_keywords,
            'author' => $this->metadata['author'] ?? 'AI News Desk',
            'read_time_minutes' => $this->metadata['read_time_minutes'] ?? 1,
            'image_url' => $this->image_url,
            'thumbnail_url' => $this->thumbnail_url ?? $this->image_url,
            'published_at' => $this->created_at->toIso8601String(),
            'sources' => $this->topic?->sources->map(fn($source) => [
                'name' => $source->source_name,
                'url' => $source->source_url,
                'published_at' => $source->published_at?->toIso8601String(),
            ]),
            'citations' => $this->metadata['citations'] ?? [],
            'faq_section' => $this->metadata['faq_section'] ?? [],
            'internal_links' => $this->metadata['internal_links'] ?? [],
            'entities' => $this->metadata['entities'] ?? [],
        ];
    }

    private function resolveCategory(): ?array
    {
        $topic = $this->topic;

        if (! $topic) {
            return null;
        }

        // New hierarchical — loaded via `category_id` relationship
        if ($topic->relationLoaded('categoryRelation') && $topic->categoryRelation instanceof \App\Models\Category) {
            return [
                'id' => $topic->categoryRelation->id,
                'name' => $topic->categoryRelation->name,
                'slug' => $topic->categoryRelation->slug,
                'parent_id' => $topic->categoryRelation->parent_id,
            ];
        }

        // Legacy flat category string — no FK yet
        $legacyCategory = $topic->getAttribute('category');
        if (is_string($legacyCategory) && $legacyCategory !== '') {
            return [
                'id' => null,
                'name' => $legacyCategory,
                'slug' => \Illuminate\Support\Str::slug($legacyCategory),
                'parent_id' => null,
            ];
        }

        return null;
    }

    private function resolveLocation(): ?array
    {
        $topic = $this->topic;

        if (! $topic || ! $topic->relationLoaded('locationCategory')) {
            return null;
        }

        $loc = $topic->locationCategory;
        if (! $loc instanceof \App\Models\Category) {
            return null;
        }

        return [
            'id' => $loc->id,
            'name' => $loc->name,
            'slug' => $loc->slug,
        ];
    }
}
