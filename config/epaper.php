<?php

return [

    /*
    |--------------------------------------------------------------------------
    | E-Paper editions (menu: E-Paper -> Gujarati / Hindi / English)
    |--------------------------------------------------------------------------
    |
    | Each language reads its editions live from a Blogger blog, so anything
    | posted there shows up on the site automatically (cached for a few
    | minutes). Leave 'blog' empty to show a "coming soon" page instead.
    |
    */

    'editions' => [
        'gujarati' => [
            'label' => 'Gujarati',
            'native' => 'ગુજરાતી',
            'title' => 'ગુજરાતી ઈ-પેપર',
            'blog' => 'https://markettrendguj-ipoint.blogspot.com',
        ],
        'hindi' => [
            'label' => 'Hindi',
            'native' => 'हिन्दी',
            'title' => 'हिन्दी ई-पेपर',
            'blog' => 'https://markettrendhindi-ipoint.blogspot.com',
        ],
        'english' => [
            'label' => 'English',
            'native' => 'English',
            'title' => 'English E-Paper',
            'blog' => 'https://markettrendeng-ipoint.blogspot.com',
        ],
    ],

    // Digital News Paper: the blogspot its editions are imported from (newspaper:import),
    // whose "Total Pageviews" counter is also shown on the Digital News Paper pages
    'weekly_blog' => 'https://weeklynewspaper-ipoint.blogspot.com',

    // Editions per page on the list
    'per_page' => 24,

    // Minutes the edition list / a single edition is cached
    'cache_minutes' => 10,
];
