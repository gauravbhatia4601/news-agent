<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ArticleResource extends JsonResource
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
            'views' => $this->views,
            'author' => $this->metadata['author'] ?? 'AI News Desk',
            'read_time_minutes' => $this->metadata['read_time_minutes'] ?? 1,
            'image_url' => $this->image_url,
            'thumbnail_url' => $this->thumbnail_url ?? $this->image_url,
            'published_at' => $this->created_at->toIso8601String(),
            'source_count' => $this->metadata['source_count'] ?? 0,
            'excerpt' => $this->stripExcerpt(),
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

    private function stripExcerpt(): string
    {
        $content = (string) ($this->resource->content ?? '');

        $text = strip_tags($content);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/\s+/', ' ', $text);
        $text = trim($text);

        if (mb_strlen($text) <= 130) {
            return $text;
        }

        $excerpt = mb_substr($text, 0, 130);
        $lastSpace = mb_strrpos($excerpt, ' ');
        if ($lastSpace !== false && $lastSpace > 80) {
            $excerpt = mb_substr($excerpt, 0, $lastSpace);
        }

        return $excerpt.'…';
    }
}
