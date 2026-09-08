<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MarketDataService
{
    private const CACHE_KEY = 'news-engine:market-data';

    private const CACHE_TTL = 300;

    private array $indices = [
        '^NSEI' => ['name' => 'NIFTY 50',      'currency' => 'INR', 'region' => 'india'],
        '^BSESN' => ['name' => 'SENSEX',         'currency' => 'INR', 'region' => 'india'],
        '^NSEBANK' => ['name' => 'NIFTY BANK',     'currency' => 'INR', 'region' => 'india'],
        '^GSPC' => ['name' => 'S&P 500',        'currency' => 'USD', 'region' => 'us'],
        '^DJI' => ['name' => 'DOW JONES',      'currency' => 'USD', 'region' => 'us'],
        '^IXIC' => ['name' => 'NASDAQ',         'currency' => 'USD', 'region' => 'us'],
        '^FTSE' => ['name' => 'FTSE 100',       'currency' => 'GBP', 'region' => 'europe'],
        '^N225' => ['name' => 'NIKKEI 225',     'currency' => 'JPY', 'region' => 'asia'],
    ];

    public function getMarketData(): array
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL, function (): array {
            return $this->fetchAll();
        });
    }

    private function fetchAll(): array
    {
        $results = [];

        foreach ($this->indices as $symbol => $meta) {
            $data = $this->fetchOne($symbol);
            if ($data === null) {
                continue;
            }

            $results[] = array_merge($meta, $data);
        }

        return $results;
    }

    private function fetchOne(string $symbol): ?array
    {
        $encoded = urlencode($symbol);
        $url = "https://query1.finance.yahoo.com/v8/finance/chart/{$encoded}";

        try {
            $response = Http::timeout(10)
                ->withHeaders(['User-Agent' => 'Mozilla/5.0'])
                ->get($url);

            if (! $response->ok()) {
                return null;
            }

            $meta = $response->json('chart.result.0.meta');
            if (! is_array($meta)) {
                return null;
            }

            $price = (float) ($meta['regularMarketPrice'] ?? 0);
            $previousClose = (float) ($meta['chartPreviousClose'] ?? $meta['previousClose'] ?? 0);
            $change = $price - $previousClose;
            $changePercent = $previousClose > 0 ? ($change / $previousClose) * 100 : 0;

            return [
                'symbol' => $symbol,
                'price' => $price,
                'change' => round($change, 2),
                'change_percent' => round($changePercent, 2),
                'previous_close' => $previousClose,
                'day_high' => (float) ($meta['regularMarketDayHigh'] ?? 0),
                'day_low' => (float) ($meta['regularMarketDayLow'] ?? 0),
                'fifty_two_week_high' => (float) ($meta['fiftyTwoWeekHigh'] ?? 0),
                'fifty_two_week_low' => (float) ($meta['fiftyTwoWeekLow'] ?? 0),
                'market_time' => isset($meta['regularMarketTime'])
                    ? date('c', (int) $meta['regularMarketTime'])
                    : null,
            ];
        } catch (\Throwable $e) {
            Log::warning('Market data fetch failed for '.$symbol.': '.$e->getMessage());

            return null;
        }
    }
}
