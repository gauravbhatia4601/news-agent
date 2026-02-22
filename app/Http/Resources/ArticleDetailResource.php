<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ArticleDetailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'title' => $this->title,
            'category' => $this->topic?->category,
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
        ];
    }
}
