<?php

namespace App\Services;

use App\Models\Category;
use App\Models\NewsArticle;

class SitemapService
{
    private string $outputDir;

    private string $baseUrl;

    public function __construct()
    {
        $this->outputDir = public_path('sitemaps');
        $this->baseUrl = rtrim(
            config('app.frontend_url') ?: config('app.url', 'https://theneuraljournal.com'),
            '/'
        );
    }

    public function generate(): array
    {
        if (! is_dir($this->outputDir)) {
            mkdir($this->outputDir, 0755, true);
        }

        $files = [];

        $files[] = $this->generateStaticPages();
        $files[] = $this->generateCategories();
        $articleFiles = $this->generateArticles();
        $files = array_merge($files, $articleFiles);
        $newsFile = $this->generateGoogleNews();
        if ($newsFile) {
            $files[] = $newsFile;
        }
        $files[] = $this->generateIndex($files);

        $this->cleanupOrphaned($files);

        return $files;
    }

    private function generateStaticPages(): string
    {
        $filename = 'sitemap-pages.xml';
        $pages = [
            ['loc' => '', 'priority' => '1.0', 'changefreq' => 'hourly'],
            ['loc' => '/trending', 'priority' => '0.9', 'changefreq' => 'hourly'],
            ['loc' => '/categories', 'priority' => '0.8', 'changefreq' => 'daily'],
            ['loc' => '/search', 'priority' => '0.5', 'changefreq' => 'weekly'],
        ];

        $lastmod = now()->toAtomString();

        $xml = $this->wrapUrlset($pages, $lastmod, fn ($page) => [
            'loc' => $this->baseUrl.$page['loc'],
            'lastmod' => $lastmod,
            'changefreq' => $page['changefreq'],
            'priority' => $page['priority'],
        ]);

        $this->writeFile($filename, $xml);

        return $filename;
    }

    private function generateCategories(): string
    {
        $filename = 'sitemap-categories.xml';

        $categories = Category::with('children')
            ->orderBy('display_order')
            ->get();

        $urls = [];

        foreach ($categories as $cat) {
            $urls[] = [
                'loc' => $this->baseUrl.'/category/'.$cat->slug,
                'lastmod' => max($cat->updated_at, $cat->created_at)->toAtomString(),
                'changefreq' => 'daily',
                'priority' => '0.7',
            ];

            foreach ($cat->children as $child) {
                $urls[] = [
                    'loc' => $this->baseUrl.'/category/'.$child->slug,
                    'lastmod' => max($child->updated_at, $child->created_at)->toAtomString(),
                    'changefreq' => 'daily',
                    'priority' => '0.6',
                ];
            }
        }

        $xml = $this->renderUrlset($urls);
        $this->writeFile($filename, $xml);

        return $filename;
    }

    /**
     * @return string[]
     */
    private function generateArticles(): array
    {
        $files = [];
        $batch = 0;
        $perFile = 1000;

        NewsArticle::select('slug', 'updated_at', 'created_at')
            ->where('status', 'published')
            ->orderByDesc('created_at')
            ->chunk($perFile, function ($articles) use (&$files, &$batch) {
                $batch++;
                $filename = "sitemap-articles-{$batch}.xml";

                $urls = [];
                foreach ($articles as $article) {
                    $lastmod = $article->updated_at ? $article->updated_at->toAtomString() : $article->created_at->toAtomString();
                    $ageHours = $article->created_at->diffInHours(now());
                    $changefreq = $ageHours < 24 ? 'hourly' : ($ageHours < 168 ? 'daily' : 'weekly');
                    $priority = $ageHours < 12 ? '0.9' : ($ageHours < 48 ? '0.8' : ($ageHours < 168 ? '0.6' : '0.4'));

                    $urls[] = [
                        'loc' => $this->baseUrl.'/article/'.$article->slug,
                        'lastmod' => $lastmod,
                        'changefreq' => $changefreq,
                        'priority' => $priority,
                    ];
                }

                $xml = $this->renderUrlset($urls);
                $this->writeFile($filename, $xml);
                $files[] = $filename;
            });

        return $files;
    }

    private function generateGoogleNews(): ?string
    {
        $articles = NewsArticle::select('slug', 'title', 'created_at', 'meta_keywords')
            ->where('status', 'published')
            ->where('created_at', '>=', now()->subHours(48))
            ->orderByDesc('created_at')
            ->take(1000)
            ->get();

        if ($articles->isEmpty()) {
            return null;
        }

        $filename = 'sitemap-news.xml';

        $urlsetOpen = '<?xml version="1.0" encoding="UTF-8"?>';
        $urlsetOpen .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:news="http://www.google.com/schemas/sitemap-news/0.9">';
        $urlsetClose = '</urlset>';

        $publicationName = htmlspecialchars('The Neural Journal');
        $publicationLang = 'en';

        $items = '';
        foreach ($articles as $article) {
            $title = htmlspecialchars($article->title);
            $loc = $this->baseUrl.'/article/'.$article->slug;
            $pubDate = $article->created_at->toW3cString();
            $keywords = $article->meta_keywords ? htmlspecialchars($article->meta_keywords) : '';

            $items .= '<url>';
            $items .= "<loc>{$loc}</loc>";
            $items .= '<news:news>';
            $items .= '<news:publication>';
            $items .= "<news:name>{$publicationName}</news:name>";
            $items .= "<news:language>{$publicationLang}</news:language>";
            $items .= '</news:publication>';
            $items .= "<news:publication_date>{$pubDate}</news:publication_date>";
            $items .= "<news:title>{$title}</news:title>";
            if ($keywords !== '') {
                $items .= "<news:keywords>{$keywords}</news:keywords>";
            }
            $items .= '</news:news>';
            $items .= '</url>';
        }

        $xml = $urlsetOpen.$items.$urlsetClose;
        $this->writeFile($filename, $xml);

        return $filename;
    }

    /**
     * @param  string[]  $files
     */
    private function generateIndex(array $files): string
    {
        $filename = 'sitemap.xml';

        $xml = '<?xml version="1.0" encoding="UTF-8"?>';
        $xml .= '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';

        foreach ($files as $file) {
            $xml .= '<sitemap>';
            $xml .= '<loc>'.$this->baseUrl.'/sitemaps/'.$file.'</loc>';
            $xml .= '<lastmod>'.now()->toAtomString().'</lastmod>';
            $xml .= '</sitemap>';
        }

        $xml .= '</sitemapindex>';

        $this->writeFile($filename, $xml);

        return $filename;
    }

    /**
     * @param  string[]  $activeFiles
     */
    private function cleanupOrphaned(array $activeFiles): void
    {
        $keep = array_flip($activeFiles);
        $keep['sitemap.xml'] = true;

        $existing = glob($this->outputDir.'/sitemap*.xml') ?: [];

        foreach ($existing as $file) {
            $basename = basename($file);
            if (! isset($keep[$basename])) {
                @unlink($file);
            }
        }
    }

    /**
     * @param  array<int, array<string, string>>  $urls
     */
    private function renderUrlset(array $urls): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>';
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';

        foreach ($urls as $url) {
            $xml .= '<url>';
            $xml .= '<loc>'.htmlspecialchars($url['loc']).'</loc>';
            $xml .= '<lastmod>'.$url['lastmod'].'</lastmod>';
            $xml .= '<changefreq>'.$url['changefreq'].'</changefreq>';
            $xml .= '<priority>'.$url['priority'].'</priority>';
            $xml .= '</url>';
        }

        $xml .= '</urlset>';

        return $xml;
    }

    /**
     * @param  array<int, mixed>  $items
     */
    private function wrapUrlset(array $items, string $defaultLastmod, callable $mapper): string
    {
        $urls = array_map($mapper, $items);

        return $this->renderUrlset($urls);
    }

    private function writeFile(string $filename, string $content): void
    {
        file_put_contents($this->outputDir.'/'.$filename, $content, LOCK_EX);
    }
}
