<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StoryUpdateResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'content' => $this->content,
            'event_at' => $this->event_at?->toIso8601String(),
            'source_name' => $this->source_name,
            'source_url' => $this->source_url,
        ];
    }
}
