<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ArticleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'title' => $this->title,
            'category' => $this->topic?->category,
            'topic_name' => $this->topic?->topic_name,
            'views' => $this->views,
            'author' => $this->metadata['author'] ?? 'AI News Desk',
            'read_time_minutes' => $this->metadata['read_time_minutes'] ?? 1,
            'image_url' => $this->image_url,
            'thumbnail_url' => $this->thumbnail_url ?? $this->image_url,
            'published_at' => $this->created_at->toIso8601String(),
            'source_count' => $this->metadata['source_count'] ?? 0,
        ];
    }
}
