<?php

namespace App\Console\Commands;

use App\Services\SitemapService;
use Illuminate\Console\Command;

class SitemapGenerateCommand extends Command
{
    protected $signature = 'news:sitemap-generate';

    protected $description = 'Regenerate all sitemap files';

    public function handle(SitemapService $sitemapService): int
    {
        $this->info('Generating sitemaps...');

        $start = microtime(true);
        $files = $sitemapService->generate();
        $elapsed = round(microtime(true) - $start, 2);

        $this->info("Done in {$elapsed}s. Generated " . count($files) . " sitemap files:");

        foreach ($files as $file) {
            $size = file_exists(public_path('sitemaps/' . $file))
                ? round(filesize(public_path('sitemaps/' . $file)) / 1024, 1) . ' KB'
                : '0 KB';
            $this->line("  {$file} ({$size})");
        }

        $this->info('Sitemap index: ' . url('/sitemaps/sitemap.xml'));

        return self::SUCCESS;
    }
}
