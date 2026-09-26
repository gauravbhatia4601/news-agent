<?php

namespace App\Console\Commands;

use App\Services\HomeFeedService;
use Illuminate\Console\Command;

class WarmHomeFeedCommand extends Command
{
    protected $signature = 'news:warm-home-feed';

    protected $description = 'Rebuild the batched homepage feed into the cache (scheduler-driven; user requests never trigger SQL)';

    public function handle(HomeFeedService $service): int
    {
        $service->warm();

        $this->line('home feed warmed at '.now()->toTimeString());

        return self::SUCCESS;
    }
}