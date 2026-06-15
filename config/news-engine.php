<?php

return [

    /*
    |--------------------------------------------------------------------------
    | News Categories
    |--------------------------------------------------------------------------
    |
    | Flat category slugs (legacy). New hierarchical categories are stored
    | in the `categories` table. Discovery now iterates subcategories from
    | the database with per-subcategory search queries below.
    |
    */
    'categories' => array_filter(array_map('trim', explode(',', env('NEWS_DISCOVERY_CATEGORIES', 'technology,sports,politics,entertainment,social'))) ?: []),

    /*
    |--------------------------------------------------------------------------
    | Discovery Defaults
    |--------------------------------------------------------------------------
    */
    'discovery' => [
        'default_limit' => (int) env('NEWS_DISCOVERY_LIMIT', 3),
        'default_fresh_hours' => (int) env('NEWS_DISCOVERY_FRESH_HOURS', 12),
        'default_sources_per_topic' => (int) env('NEWS_DISCOVERY_SOURCES_PER_TOPIC', 3),
        'seen_cache_key' => env('NEWS_DISCOVERY_SEEN_CACHE_KEY', 'news-engine:rss:seen-signatures'),
        'seen_cache_ttl_seconds' => (int) env('NEWS_DISCOVERY_SEEN_CACHE_TTL_SECONDS', 172800),
    ],

    /*
    |--------------------------------------------------------------------------
    | Article Generation
    |--------------------------------------------------------------------------
    */
    'generation' => [
        'enabled' => (bool) env('NEWS_GENERATION_ENABLED', true),
        'provider' => env('NEWS_GENERATION_PROVIDER', env('AI_DEFAULT_PROVIDER', 'openrouter')),
        'model' => env('NEWS_GENERATION_MODEL', 'gemma4:31b-cloud'),
        'timeout' => (int) env('NEWS_GENERATION_TIMEOUT', 120),
    ],

    /*
    |--------------------------------------------------------------------------
    | News Discovery Provider
    |--------------------------------------------------------------------------
    */
    'discovery_provider' => env('NEWS_DISCOVERY_PROVIDER', 'google_rss'),

    /*
    |--------------------------------------------------------------------------
    | Article Images
    |--------------------------------------------------------------------------
    */
    'images' => [
        'enabled' => (bool) env('NEWS_IMAGES_ENABLED', false),

        'source' => [
            'enabled' => (bool) env('NEWS_SOURCE_IMAGES_ENABLED', false),
            'max_attempts' => (int) env('NEWS_SOURCE_IMAGES_MAX_ATTEMPTS', 3),
            'html_timeout' => (int) env('NEWS_SOURCE_IMAGES_HTML_TIMEOUT', 10),
            'image_timeout' => (int) env('NEWS_SOURCE_IMAGES_DOWNLOAD_TIMEOUT', 20),
        ],

        'ai' => [
            'enabled' => (bool) env('NEWS_AI_IMAGE_ENABLED', false),
            'provider' => env('NEWS_AI_IMAGE_PROVIDER', 'pollinations'),
            'style_prompt' => env(
                'NEWS_AI_IMAGE_STYLE_PROMPT',
                'Minimalist editorial illustration, modern flat style, clean composition, soft neutral palette, no text, no logos, no watermarks.'
            ),
            'pollinations' => [
                'base_url' => env('NEWS_AI_IMAGE_BASE_URL', 'https://image.pollinations.ai'),
                'model' => env('NEWS_AI_IMAGE_MODEL', 'flux'),
                'width' => (int) env('NEWS_AI_IMAGE_WIDTH', 1536),
                'height' => (int) env('NEWS_AI_IMAGE_HEIGHT', 864),
                'timeout' => (int) env('NEWS_AI_IMAGE_TIMEOUT', 35),
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Source Registry
    |--------------------------------------------------------------------------
    */
    'sources' => [
        'enabled' => array_filter(array_map('trim', explode(',', env('NEWS_DISCOVERY_SOURCES', 'google_rss')))),

        'brave_search' => [
            'enabled' => (bool) env('BRAVE_SEARCH_ENABLED', false),
            'api_key' => env('BRAVE_SEARCH_API_KEY'),
            'base_url' => env('BRAVE_SEARCH_BASE_URL', 'https://api.search.brave.com/res/v1'),
            'timeout' => (int) env('BRAVE_SEARCH_TIMEOUT', 20),
            'search_lang' => env('BRAVE_SEARCH_LANG', 'en'),
            'freshness' => env('BRAVE_SEARCH_FRESHNESS', 'pw'),
            'max_urls' => (int) env('BRAVE_SEARCH_MAX_URLS', 20),
            'max_tokens' => (int) env('BRAVE_SEARCH_MAX_TOKENS', 8192),
            'queries' => [
                'politics-governance' => 'India politics OR parliament OR Lok Sabha OR election OR Modi government',
                'defence-security' => 'India defence OR Indian Army OR border security OR terrorism India OR DRDO',
                'states-regions' => 'India state politics OR chief minister OR regional development India OR state elections',
                'law-judiciary' => 'Supreme Court India OR High Court ruling OR Indian law reform OR judiciary news',
                'social-justice' => 'India social justice OR caste equality OR gender rights India OR minority rights India',

                'south-asia' => 'India Pakistan relations OR Bangladesh India OR Sri Lanka news OR Nepal India OR SAARC OR Maldives',
                'asia-pacific' => 'India China relations OR India Japan OR India ASEAN OR Quad summit OR Indo-Pacific',
                'americas' => 'India US relations OR India Canada OR India Latin America trade OR diaspora',
                'europe-uk' => 'India EU trade OR India UK FTA OR India Russia relations OR G7 India',
                'middle-east-africa' => 'India UAE relations OR India Saudi Arabia OR India Israel OR India Africa trade OR Gulf workers',

                'markets-finance' => 'India stock market OR Nifty OR Sensex OR RBI policy OR SEBI OR banking India OR mutual funds OR IPO India',
                'startups-tech-biz' => 'Indian startup funding OR unicorn India OR venture capital India OR startup ecosystem OR Bengaluru startups',
                'industry-manufacturing' => 'Make in India OR PLI scheme India OR automotive India OR steel industry India OR pharmaceutical India',
                'economy-policy' => 'India GDP OR India inflation OR RBI monetary policy OR budget India OR GST reform OR Indian economy',
                'real-estate-infrastructure' => 'India real estate OR Indian infrastructure OR smart cities India OR highway projects OR airport development',

                'artificial-intelligence' => 'India AI policy OR AI research India OR AI startup India OR machine learning India OR IndiaAI mission',
                'it-services' => 'TCS OR Infosys OR Wipro OR HCL Tech OR Indian IT industry OR IT exports India OR outsourcing',
                'startups-innovation' => 'Indian startups OR tech incubator India OR deep tech India OR SaaS India OR innovation hub India',
                'cybersecurity' => 'India cybersecurity OR cybercrime India OR CERT-In OR data breach India OR digital security India',
                'consumer-tech-gadgets' => 'India smartphone market OR 5G India OR telecom India OR consumer electronics India OR EV India',

                'cricket' => 'India cricket OR Team India OR IPL OR BCCI OR ICC cricket OR Ranji Trophy',
                'football' => 'Indian football OR ISL OR Indian Super League OR Indian national football team OR AIFF',
                'olympic-sports' => 'India Olympic sports OR Indian athletics OR Neeraj Chopra OR Indian wrestling OR shooting India',
                'racquet-sports' => 'PV Sindhu OR Indian badminton OR Indian tennis OR Sumit Nagal OR table tennis India',
                'other-sports' => 'Indian hockey OR kabaddi India OR chess India OR Indian motorsports OR Indian golf',

                'bollywood-hindi-cinema' => 'Bollywood news OR Hindi film release OR Bollywood box office OR Hindi movie review OR celebrity news India',
                'regional-cinema' => 'Tamil cinema OR Telugu film OR Malayalam movie OR Kannada cinema OR Bengali film OR Marathi cinema',
                'ott-streaming' => 'Netflix India OR Prime Video India OR Hotstar OR JioCinema OR Indian web series OR OTT India',
                'music-arts' => 'Indian music OR indie music India OR classical music India OR art exhibition India OR Indian festival',
                'gaming-esports' => 'India gaming OR BGMI India OR Valorant India OR esports India OR mobile gaming India',

                'health-wellness' => 'India public health OR AYUSH OR Indian healthcare OR fitness India OR mental health India',
                'food-drink' => 'Indian cuisine OR restaurant India OR food trend India OR culinary India OR street food India',
                'travel-destinations' => 'India tourism OR travel destination India OR heritage site India OR visa India OR adventure travel India',
                'style-fashion' => 'Indian fashion OR Indian designer OR ethnic wear India OR sustainable fashion India OR beauty industry India',
                'home-design' => 'Indian architecture OR interior design India OR smart home India OR urban living India OR Indian real estate design',

                'space-isro' => 'ISRO mission OR ISRO launch OR Chandrayaan OR Gaganyaan OR Indian space program OR space exploration India',
                'climate-environment' => 'India climate change OR renewable energy India OR pollution India OR environment policy India OR conservation',
                'scientific-research' => 'Indian research OR CSIR India OR IIT research OR Indian scientist OR scientific breakthrough India',
                'higher-education' => 'IIT OR IIM OR Indian university OR higher education India OR study abroad India OR edtech India',
                'agriculture-rural' => 'Indian agriculture OR agritech India OR MSP India OR rural development OR food security India OR farmer',

                'andhra-pradesh' => 'Andhra Pradesh OR Amaravati OR Visakhapatnam OR Vijayawada OR Chandrababu Naidu OR Telugu Desam',
                'bihar' => 'Bihar OR Patna OR Nitish Kumar OR Bihar politics OR Bihar development OR Gaya',
                'delhi-ncr' => 'Delhi OR New Delhi OR NCR OR Gurugram OR Noida OR Delhi government OR MCD',
                'gujarat' => 'Gujarat OR Ahmedabad OR Surat OR Vadodara OR Gujarat government OR GIFT City',
                'karnataka' => 'Karnataka OR Bengaluru OR Bangalore OR Mysuru OR Karnataka politics OR Kannada',
                'kerala' => 'Kerala OR Thiruvananthapuram OR Kochi OR Kozhikode OR Kerala government OR Malayalam',
                'madhya-pradesh' => 'Madhya Pradesh OR Bhopal OR Indore OR Gwalior OR MP government OR Madhya Pradesh politics',
                'maharashtra' => 'Maharashtra OR Mumbai OR Pune OR Nagpur OR Maharashtra government OR Marathi',
                'punjab-haryana' => 'Punjab OR Haryana OR Chandigarh OR Ludhiana OR Ambala OR Punjabi OR Haryana politics',
                'rajasthan' => 'Rajasthan OR Jaipur OR Udaipur OR Jodhpur OR Rajasthan government OR Rajasthani',
                'tamil-nadu' => 'Tamil Nadu OR Chennai OR Coimbatore OR Madurai OR MK Stalin OR DMK OR Tamil',
                'telangana' => 'Telangana OR Hyderabad OR Warangal OR Telangana government OR Revanth Reddy OR Telugu',
                'uttar-pradesh' => 'Uttar Pradesh OR Lucknow OR Kanpur OR Varanasi OR Yogi Adityanath OR UP government',
                'west-bengal' => 'West Bengal OR Kolkata OR Darjeeling OR Mamata Banerjee OR Trinamool OR Bengali',
                'northeast-india' => 'Assam OR Manipur OR Meghalaya OR Nagaland OR Tripura OR Mizoram OR Arunachal OR Sikkim OR Northeast India',
                'odisha-jharkhand' => 'Odisha OR Jharkhand OR Bhubaneswar OR Ranchi OR Naveen Patnaik OR Odisha government OR Jamshedpur',
                'chhattisgarh' => 'Chhattisgarh OR Raipur OR Bhilai OR Chhattisgarh government OR Bastar OR Naxal',
                'himalayan-states' => 'Uttarakhand OR Himachal Pradesh OR Dehradun OR Shimla OR Uttarakhand government OR Manali',
                'goa-coastal' => 'Goa OR Panaji OR Puducherry OR Andaman OR Lakshadweep OR Daman Diu OR Dadra Nagar Haveli',
                'central-uts' => 'Jammu Kashmir OR Ladakh OR Srinagar OR Jammu OR Kashmir politics OR Leh',
            ],
        ],

        'google_rss' => [
            'base_feed_url' => env('GOOGLE_NEWS_RSS_URL', 'https://news.google.com/rss/search'),
            'hl' => env('GOOGLE_NEWS_RSS_HL', 'en-IN'),
            'gl' => env('GOOGLE_NEWS_RSS_GL', 'IN'),
            'ceid' => env('GOOGLE_NEWS_RSS_CEID', 'IN:en'),
            'timeout' => (int) env('GOOGLE_NEWS_RSS_TIMEOUT', 20),
            'queries' => [
                'politics-governance' => '(India politics OR parliament OR Lok Sabha OR election OR Modi government) when:1d',
                'defence-security' => '(India defence OR Indian Army OR border security OR terrorism OR DRDO) when:1d',
                'states-regions' => '(state politics India OR chief minister OR regional development) when:1d',
                'law-judiciary' => '(Supreme Court India OR High Court ruling OR Indian law) when:1d',
                'social-justice' => '(social justice India OR caste equality OR gender rights OR minority rights) when:1d',

                'south-asia' => '(India Pakistan OR Bangladesh India OR Sri Lanka OR Nepal OR Maldives OR SAARC) when:1d',
                'asia-pacific' => '(India China OR India Japan OR ASEAN OR Quad OR Indo-Pacific) when:1d',
                'americas' => '(India US relations OR India Canada OR India Latin America OR Indian diaspora) when:1d',
                'europe-uk' => '(India EU trade OR India UK FTA OR India Russia OR G7 India) when:1d',
                'middle-east-africa' => '(India UAE OR India Saudi Arabia OR India Israel OR India Africa OR Gulf workers) when:1d',

                'markets-finance' => '(Sensex OR Nifty OR RBI policy OR SEBI OR banking India OR IPO India OR mutual fund) when:1d',
                'startups-tech-biz' => '(Indian startup funding OR unicorn India OR venture capital OR startup ecosystem) when:1d',
                'industry-manufacturing' => '(Make in India OR PLI scheme OR automotive India OR steel industry) when:1d',
                'economy-policy' => '(India GDP OR RBI monetary OR budget India OR GST reform OR Indian economy) when:1d',
                'real-estate-infrastructure' => '(India real estate OR Indian infrastructure OR smart city OR highway project) when:1d',

                'artificial-intelligence' => '(India AI OR AI startup India OR machine learning India OR IndiaAI) when:1d',
                'it-services' => '(TCS OR Infosys OR Wipro OR Indian IT industry OR outsourcing India) when:1d',
                'startups-innovation' => '(Indian startups OR tech incubator India OR deep tech India OR SaaS India) when:1d',
                'cybersecurity' => '(India cybersecurity OR cybercrime India OR CERT-In OR data breach India) when:1d',
                'consumer-tech-gadgets' => '(India smartphone OR 5G India OR telecom India OR EV India gadget) when:1d',

                'cricket' => '(India cricket OR Team India OR IPL OR BCCI OR ICC cricket) when:1d',
                'football' => '(Indian football OR ISL OR Indian national team OR AIFF) when:1d',
                'olympic-sports' => '(India Olympics OR Neeraj Chopra OR Indian athletics OR wrestling India) when:1d',
                'racquet-sports' => '(PV Sindhu OR Indian badminton OR Indian tennis OR table tennis India) when:1d',
                'other-sports' => '(Indian hockey OR kabaddi OR chess India OR Indian motorsports) when:1d',

                'bollywood-hindi-cinema' => '(Bollywood news OR Hindi film release OR Bollywood box office OR celebrity India) when:1d',
                'regional-cinema' => '(Tamil cinema OR Telugu film OR Malayalam movie OR Kannada cinema OR Bengali film) when:1d',
                'ott-streaming' => '(Netflix India OR Prime Video India OR Hotstar OR JioCinema OR Indian web series) when:1d',
                'music-arts' => '(Indian music OR indie music India OR Indian festival OR art exhibition) when:1d',
                'gaming-esports' => '(India gaming OR BGMI OR Valorant India OR esports India OR mobile gaming) when:1d',

                'health-wellness' => '(India public health OR AYUSH OR Indian healthcare OR fitness India OR mental health) when:1d',
                'food-drink' => '(Indian cuisine OR restaurant India OR food trend India OR street food India) when:1d',
                'travel-destinations' => '(India tourism OR travel destination India OR heritage site OR adventure travel) when:1d',
                'style-fashion' => '(Indian fashion OR Indian designer OR ethnic wear OR sustainable fashion) when:1d',
                'home-design' => '(Indian architecture OR interior design India OR smart home OR urban living) when:1d',

                'space-isro' => '(ISRO mission OR Chandrayaan OR Gaganyaan OR Indian space program) when:1d',
                'climate-environment' => '(India climate change OR renewable energy India OR pollution India OR conservation) when:1d',
                'scientific-research' => '(CSIR India OR IIT research OR Indian scientist OR scientific breakthrough) when:1d',
                'higher-education' => '(IIT OR IIM OR Indian university OR study abroad India OR edtech India) when:1d',
                'agriculture-rural' => '(Indian agriculture OR agritech India OR MSP India OR rural development OR farmer) when:1d',
            ],
        ],
    ],
];
