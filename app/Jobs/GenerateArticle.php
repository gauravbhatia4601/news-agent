<?php

namespace App\Jobs;

use App\News\Services\NewsArticleGenerationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class GenerateArticle implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $backoff = [30, 60, 120];

    public function __construct(
        public readonly string $topicSignature,
    ) {}

    public function handle(NewsArticleGenerationService $generationService): void
    {
        $generationService->generateForDiscoveredTopics([$this->topicSignature]);
    }
}
