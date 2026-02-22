<?php

namespace App\News\DTO;

use Illuminate\Support\Carbon;

class DiscoveredSource
{
    public function __construct(
        public readonly string $sourceName,
        public readonly string $sourceUrl,
        public readonly string $headline,
        public readonly string $summary,
        public readonly ?Carbon $publishedAt,
        public readonly string $signature,
    ) {}

    public function toArray(): array
    {
        return [
            'source_name' => $this->sourceName,
            'source_url' => $this->sourceUrl,
            'headline' => $this->headline,
            'summary' => $this->summary,
            'published_at' => $this->publishedAt?->toDateTimeString(),
            'signature' => $this->signature,
        ];
    }
}
