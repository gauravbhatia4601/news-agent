<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::prefix('sitemaps')->group(function () {
    Route::get('/', function () {
        return redirect('/sitemaps/sitemap.xml', 301);
    });

    Route::get('sitemap.xml', function () {
        $path = public_path('sitemaps/sitemap.xml');
        if (! file_exists($path)) {
            abort(404);
        }
        return response()->file($path, ['Content-Type' => 'application/xml']);
    });

    Route::get('{filename}', function (string $filename) {
        if (! preg_match('/^sitemap-[a-z0-9\-]+\.xml$/i', $filename)) {
            abort(404);
        }
        $path = public_path('sitemaps/' . $filename);
        if (! file_exists($path)) {
            abort(404);
        }
        return response()->file($path, ['Content-Type' => 'application/xml']);
    })->where('filename', '.+');
});

Route::get('/sitemap.xml', function () {
    return redirect('/sitemaps/sitemap.xml', 301);
});

Route::get('/feed.xml', function () {
    $articles = \App\Models\NewsArticle::query()
        ->where('status', 'published')
        ->orderByDesc('created_at')
        ->take(50)
        ->get();

    $appUrl = config('app.url', 'https://thetrustjournal.com');

    $xml = '<?xml version="1.0" encoding="UTF-8"?>';
    $xml .= '<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom" xmlns:media="http://search.yahoo.com/mrss/">';
    $xml .= '<channel>';
    $xml .= '<title>The AI Journal</title>';
    $xml .= '<link>' . $appUrl . '</link>';
    $xml .= '<description>Latest news from India and around the world — AI-powered journalism</description>';
    $xml .= '<language>en-in</language>';
    $xml .= '<copyright>' . date('Y') . ' The AI Journal</copyright>';
    $xml .= '<atom:link href="' . $appUrl . '/feed.xml" rel="self" type="application/rss+xml"/>';

    foreach ($articles as $article) {
        $title = htmlspecialchars($article->title);
        $link = $appUrl . '/article/' . $article->slug;
        $description = htmlspecialchars(
            $article->meta_description
            ?? \Illuminate\Support\Str::limit(strip_tags($article->content), 200)
        );
        $pubDate = $article->created_at->toRfc822String();
        $guid = $link;
        $author = htmlspecialchars($article->metadata['author'] ?? 'AI News Desk');
        $primaryKeyword = $article->meta_keywords
            ? htmlspecialchars(explode(', ', $article->meta_keywords)[0] ?? '')
            : '';
        $mediaContent = $article->image_url
            ? '<media:content url="' . htmlspecialchars($appUrl . $article->image_url) . '" medium="image"/>'
            : '';

        $xml .= '<item>';
        $xml .= "<title>{$title}</title>";
        $xml .= "<link>{$link}</link>";
        $xml .= "<guid isPermaLink=\"true\">{$guid}</guid>";
        $xml .= "<description>{$description}</description>";
        $xml .= "<pubDate>{$pubDate}</pubDate>";
        $xml .= "<author>{$author}</author>";
        if ($primaryKeyword !== '') {
            $xml .= "<category>{$primaryKeyword}</category>";
        }
        $xml .= $mediaContent;
        $xml .= '</item>';
    }

    $xml .= '</channel>';
    $xml .= '</rss>';

    return response($xml, 200, [
        'Content-Type' => 'application/rss+xml',
        'Cache-Control' => 'public, max-age=300, s-maxage=600',
    ]);
});

Route::get('/robots.txt', function () {
    $appUrl = config('app.url', 'https://thetrustjournal.com');
    $content = "User-agent: *\n";
    $content .= "Allow: /\n\n";
    $content .= "Sitemap: {$appUrl}/sitemaps/sitemap.xml\n";
    $content .= "Sitemap: {$appUrl}/sitemaps/sitemap-news.xml\n";

    return response($content, 200, ['Content-Type' => 'text/plain']);
});
