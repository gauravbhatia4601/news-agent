<?php

namespace App\News\Services;

use App\Models\Category;
use Illuminate\Support\Str;

/**
 * Detect the best-matching topic category for a discovered news cluster.
 *
 * Discovery is now location-first: we search Google News / Brave for a specific
 * location (e.g. Punjab, New Zealand), then use this service to decide whether
 * the story is Politics, Business, Sports, etc. The location becomes the
 * `location_category_id` tag, while the detected topic becomes `category_id`.
 */
class TopicCategoryDetectionService
{
    /**
     * Map of topic category slugs to keyword patterns.
     *
     * Order matters: more specific categories should come before broader ones.
     */
    private const CATEGORY_PATTERNS = [
        // National
        'politics-governance' => ['politics', 'government', 'minister', 'cm', 'chief minister', 'mp', 'mla', 'election', 'poll', 'party', 'bjp', 'congress', 'aap', 'dmk', 'tdp', 'ysr', 'trs', 'brs', 'cabinet', 'assembly', 'parliament', 'lok sabha', 'rajya sabha', 'policy', 'govt'],
        'defence-security' => ['army', 'military', 'defence', 'defense', 'border', 'terrorist', 'terrorism', 'militant', 'drdo', 'cds', 'air force', 'navy', 'jammu', 'kashmir', 'pulwama', 'loc', 'lacc', 'cyber attack', 'cybersecurity threat'],
        'states-regions' => ['state government', 'state cabinet', 'district', 'collector', 'panchayat', 'municipal', 'regional', 'state assembly'],
        'law-judiciary' => ['supreme court', 'high court', 'judiciary', 'judge', 'verdict', 'ruling', 'plea', 'bail', ' FIR ', 'chargesheet', 'conviction', 'acquit', 'tribunal', 'cji'],
        'social-justice' => ['caste', 'reservation', 'equality', 'gender', 'women safety', 'minority', ' Dalit ', 'tribe', ' Scheduled ', 'poverty', 'social welfare', 'mid day meal', 'anganwadi'],

        // World
        'south-asia' => ['pakistan', 'bangladesh', 'sri lanka', 'nepal', 'bhutan', 'maldives', 'afghanistan', 'saarc'],
        'asia-pacific' => ['china', 'japan', 'australia', 'asean', 'quad', 'indo-pacific', 'south korea', 'taiwan', 'philippines', 'vietnam', 'myanmar'],
                'americas' => ['united states', 'usa', 'canada', 'biden', 'trump', 'mexico', 'latin america'],
        'europe-uk' => ['european union', 'eu ', 'uk ', 'britain', 'france', 'germany', 'russia', 'ukraine', 'nato', 'g7', 'g20'],
        'middle-east-africa' => ['uae', 'saudi arabia', 'israel', 'gaza', 'palestine', 'iran', 'iraq', 'qatar', 'kuwait', 'oman', 'africa', 'nigeria', 'south africa', 'kenya'],

        // Business & Economy
        'markets-finance' => ['sensex', 'nifty', 'stock market', 'share market', 'ipo', 'rbi policy', 'sebi', 'mutual fund', 'banking', 'nbfc', 'insurance', 'psu', 'fiscal deficit', 'inflation data'],
        'startups-tech-biz' => ['startup', 'funding', 'unicorn', 'venture capital', 'vc ', 'seed funding', 'series a', 'series b', 'entrepreneur', 'angel investor', ' valuation ', 'acquired', 'merger', 'ipo subscription'],
        'industry-manufacturing' => ['manufacturing', 'make in india', 'pli scheme', 'automotive', 'car sales', 'ev ', 'steel', 'cement', 'pharma', 'semiconductor', 'fab ', 'production', 'factory', 'industrial'],
        'economy-policy' => ['gdp', 'economic growth', 'budget', 'gst', 'tax ', 'fiscal', 'monetary policy', 'repo rate', 'interest rate', 'trade deficit', 'exports', 'imports', 'forex', 'rupee', 'inflation'],
        'real-estate-infrastructure' => ['real estate', 'property', 'housing', 'smart city', 'highway', 'railway', 'metro', 'airport', 'port', 'infrastructure', 'construction', 'building', 'rera'],

        // Technology
        'artificial-intelligence' => ['artificial intelligence', 'ai ', ' generative ai', 'chatgpt', 'llm', 'machine learning', 'deep learning', 'indiaai', 'ai model', 'ai policy', 'ai regulation', 'openai', 'gemini', 'copilot'],
        'it-services' => ['tcs', 'infosys', 'wipro', 'hcl tech', 'it services', 'it exports', 'outsourcing', 'digital transformation', 'cloud services', 'software', 'cybersecurity services'],
        'startups-innovation' => ['startup', 'incubator', 'deep tech', 'saas', 'fintech', 'healthtech', 'edtech', 'agritech', 'innovation hub', 'research park', ' accelerator'],
        'cybersecurity' => ['cyber attack', 'data breach', 'hacking', 'ransomware', 'phishing', 'malware', 'cert-in', 'cybersecurity', 'digital fraud', 'online scam'],
        'consumer-tech-gadgets' => ['smartphone', 'iphone', 'android', '5g', 'telecom', 'jio', 'airtel', 'vodafone', 'ev ', 'electric vehicle', 'gadget', 'wearable', 'laptop', 'tablet'],

        // Sports
        'cricket' => ['cricket', 'ipl ', 'bcci', 'icc ', 'team india', 'odi', 't20', 'test match', 'world cup', 'ranji', 'wpl ', 'ms dhoni', 'virat kohli', 'rohit sharma'],
        'football' => ['football', 'isl ', 'indian super league', 'fifa', 'afc ', 'mohun bagan', 'east bengal', 'kerala blasters', 'mumbai city fc', 'sunil chhetri'],
        'olympic-sports' => ['olympics', 'asian games', 'athletics', 'wrestling', 'shooting', 'boxing', 'hockey', 'badminton', 'neeraj chopra', 'pv sindhu'],
        'racquet-sports' => ['badminton', 'tennis', 'table tennis', 'squash', 'pv sindhu', 'sania mirza', 'sumit nagal', 'rohan bopanna'],
        'other-sports' => ['kabaddi', 'chess', 'motorsport', 'f1 ', 'formula 1', 'golf', 'tennis ', 'wrestling ', 'boxing ', 'weightlifting', 'shooting ', 'hockey '],

        // Entertainment
        'bollywood-hindi-cinema' => ['bollywood', 'hindi film', 'box office', 'movie release', 'actor', 'actress', 'shah rukh', 'salman khan', 'aamir khan', 'ranbir kapoor', 'deepika padukone', 'alia bhatt'],
        'regional-cinema' => ['tamil cinema', 'telugu film', 'malayalam movie', 'kannada cinema', 'bengali film', 'marathi cinema', 'kfi ', 'tollywood', 'kollywood', 'mollywood', 'sandalwood'],
        'ott-streaming' => ['netflix', 'prime video', 'hotstar', 'jio cinema', 'sony liv', 'zee5', 'ott ', 'web series', 'streaming', 'disney', 'youtube premium'],
        'music-arts' => ['music', 'concert', 'album', 'singer', 'classical music', 'indie music', 'art exhibition', 'festival', 'literature', 'book launch'],
        'gaming-esports' => ['gaming', 'bgmi', 'valorant', 'esports', 'mobile gaming', 'pubg', 'free fire', 'gamer', 'tournament', 'live streaming gaming'],

        // Lifestyle
        'health-wellness' => ['health', 'healthcare', 'hospital', 'doctor', 'medical', 'ayush', 'fitness', 'mental health', 'disease', 'vaccine', 'covid', 'dengue', 'malaria', 'cancer', 'diabetes'],
        'food-drink' => ['food', 'restaurant', 'cuisine', 'street food', 'chef', 'cooking', 'beverage', 'wine', 'coffee', 'tea', 'organic food', 'food safety'],
        'travel-destinations' => ['travel', 'tourism', 'heritage', 'hotel', 'resort', 'airport', 'flight', 'visa', 'destination', 'pilgrimage', 'tourist'],
        'style-fashion' => ['fashion', 'designer', 'ethnic wear', 'sustainable fashion', 'beauty', 'makeup', 'skincare', 'luxury brand', 'textile'],
        'home-design' => ['architecture', 'interior design', 'real estate design', 'smart home', 'urban planning', 'furniture', 'home decor'],

        // Science & Education
        'space-isro' => ['isro', 'chandrayaan', 'gaganyaan', 'space', 'satellite', 'rocket', 'mars', 'moon mission', 'astronaut', 'nasa collaboration'],
        'climate-environment' => ['climate change', 'global warming', 'renewable energy', 'solar', 'wind energy', 'pollution', 'air quality', 'environment', 'conservation', 'forest', 'wildlife', 'cop'],
        'scientific-research' => ['research', 'scientist', 'csir', 'iit research', 'discovery', 'breakthrough', 'patent', 'journal', 'stem', 'biotechnology', 'nanotechnology'],
        'higher-education' => ['iit ', 'iim ', 'university', 'college', 'education policy', 'nta ', 'jee ', 'neet ', 'ups', 'study abroad', 'edtech', 'scholarship'],
        'agriculture-rural' => ['agriculture', 'farmer', 'msp ', 'crop', 'monsoon', 'drought', 'flood', 'rural development', 'panchayat', 'agritech', 'fertilizer', 'pesticide', 'dairy'],
    ];

    /**
     * @var array<string, int>|null
     */
    private ?array $topicCategoryIds = null;

    /**
     * Detect the topic category for a cluster of headlines/summaries.
     *
     * @param  string  $topicName
     * @param  array<int, array{headline: string, summary: string}>  $sources
     * @return array{id: int|null, name: string|null, slug: string|null}
     */
    public function detect(string $topicName, array $sources): array
    {
        $text = Str::lower($topicName);
        foreach ($sources as $source) {
            $text .= ' ' . Str::lower($source['headline'] ?? '');
            $text .= ' ' . Str::lower($source['summary'] ?? '');
        }

        // Collapse repeated punctuation/whitespace and strip stray apostrophes so
        // word-boundary regexes can match consistently.
        $text = Str::lower(Str::of($text)->replaceMatches('/[^a-z0-9\s]/', ' ')->squish()->value());

        $scores = [];
        foreach (self::CATEGORY_PATTERNS as $slug => $patterns) {
            $score = 0;
            foreach ($patterns as $pattern) {
                $normalized = Str::lower(trim($pattern));
                if ($normalized === '') {
                    continue;
                }

                $quoted = preg_quote($normalized, '/');
                $matches = [];
                preg_match_all('/\b'.$quoted.'\b/', $text, $matches);
                $count = count($matches[0]);
                if ($count > 0) {
                    // Longer/more specific patterns score higher to avoid noisy matches.
                    $weight = strlen($pattern) > 6 ? 2 : 1;
                    $score += $count * $weight;
                }
            }
            if ($score > 0) {
                $scores[$slug] = $score;
            }
        }

        if ($scores === []) {
            return ['id' => null, 'name' => null, 'slug' => null];
        }

        arsort($scores);
        $bestSlug = array_key_first($scores);
        $id = $this->topicCategoryId($bestSlug);

        if (! $id) {
            return ['id' => null, 'name' => null, 'slug' => null];
        }

        return [
            'id' => $id,
            'name' => $this->categoryName($bestSlug),
            'slug' => $bestSlug,
        ];
    }

    private function topicCategoryId(string $slug): ?int
    {
        if ($this->topicCategoryIds === null) {
            $this->topicCategoryIds = Category::whereNotNull('parent_id')
                ->whereHas('parent', fn ($q) => $q->where('slug', '!=', 'india'))
                ->pluck('id', 'slug')
                ->toArray();
        }

        return $this->topicCategoryIds[$slug] ?? null;
    }

    private function categoryName(string $slug): string
    {
        $category = Category::where('slug', $slug)->first();

        return $category?->name ?? Str::title(str_replace('-', ' ', $slug));
    }
}
