<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Global News Discovery Queries
    |--------------------------------------------------------------------------
    |
    | Query maps for world/global news discovery (scope=global).
    | These are used instead of the India-specific queries in news-engine.php
    | when the discovery command runs with --scope=global.
    |
    */

    'sources' => [

        'brave_search' => [
            'queries' => [
                'south-asia' => 'Pakistan OR Bangladesh OR Sri Lanka OR Nepal OR Maldives OR Bhutan OR Afghanistan politics',
                'asia-pacific' => 'China OR Japan OR Southeast Asia OR Australia OR ASEAN OR Korea OR Taiwan OR Indo-Pacific',
                'americas' => 'United States politics OR Canada OR Mexico OR Latin America OR Brazil OR Argentina economy',
                'europe-uk' => 'European Union OR UK OR Russia OR Ukraine OR NATO OR Germany OR France OR Italy economy',
                'middle-east-africa' => 'Gulf OR Israel OR Palestine OR Iran OR Saudi Arabia OR Africa OR Egypt OR Nigeria energy',
            ],
        ],

        'google_rss' => [
            'queries' => [
                'south-asia' => '(Pakistan OR Bangladesh OR Sri Lanka OR Nepal OR Maldives OR Bhutan OR Afghanistan) when:1d',
                'asia-pacific' => '(China OR Japan OR Southeast Asia OR Australia OR ASEAN OR Korea OR Taiwan OR Indo-Pacific) when:1d',
                'americas' => '(United States politics OR Canada OR Mexico OR Latin America OR Brazil OR Argentina) when:1d',
                'europe-uk' => '(European Union OR UK OR Russia OR Ukraine OR NATO OR Germany OR France OR Italy) when:1d',
                'middle-east-africa' => '(Gulf OR Israel OR Palestine OR Iran OR Saudi Arabia OR Africa OR Egypt OR Nigeria) when:1d',
            ],
        ],

    ],

];
