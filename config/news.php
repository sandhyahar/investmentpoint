<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Live market news (RSS, fetched server-side)
    |--------------------------------------------------------------------------
    |
    | The homepage "Latest News" box can show admin news, these live headlines,
    | or both (Admin -> Site Settings). Feeds are merged, de-duplicated and the
    | newest items kept. The last good list is kept, so a feed outage never
    | empties the box.
    |
    */

    // Business & market news. Business Standard's feed is left out: it stamps every item
    // with the same time and is mostly one-line stock notes.
    // 'include': only articles whose link contains one of these sections are kept, so the
    // list stays Indian business news (companies, stocks, IPOs, industry) - no travel,
    // forex, world markets or lifestyle stories.
    'feeds' => [
        ['source' => 'Economic Times', 'url' => 'https://economictimes.indiatimes.com/markets/stocks/rssfeeds/2146842.cms', 'include' => ['/markets/stocks/', '/markets/ipos/']],
        ['source' => 'Economic Times', 'url' => 'https://economictimes.indiatimes.com/industry/rssfeeds/13352306.cms',      'include' => [
            // Only these industries: banking/finance, telecom, auto, energy, real estate, airlines, cement/industrial goods
            '/industry/banking/', '/industry/telecom/', '/industry/auto/', '/industry/energy/',
            '/industry/services/property-', '/industry/transportation/airlines-', '/industry/indl-goods/',
        ]],
        ['source' => 'LiveMint',       'url' => 'https://www.livemint.com/rss/markets',   'include' => ['/market/stock-market-news/', '/market/mark-to-market/', '/market/ipo/']],
        ['source' => 'LiveMint',       'url' => 'https://www.livemint.com/rss/companies', 'include' => ['/companies/']],
    ],

    // Headlines containing any of these words are left out even from business sections
    // (whole words, any case). Add or remove words here to tune the list.
    'exclude_words' => [
        'drug', 'drugs', 'alcohol', 'liquor', 'drunk', 'murder', 'rape', 'arrested', 'arrest', 'killed', 'dies', 'died', 'death', 'dead', 'crash', 'accident',
        'celebrity', 'bollywood', 'actor', 'actress', 'cricket', 'ipl', 'wedding', 'dating', 'divorce', 'horoscope', 'astrology',
        'viral', 'strange', 'bizarre', 'weird', 'shocking', 'to-do list',
    ],

    // Never shown, whatever the feed (world markets, picture galleries, videos, consumer travel)
    'exclude' => ['/us-stocks/', '/global-markets/', '/slideshow/', '/videoshow/', '/photos/', '/web-stories/', '/podcast/', '/transportation/railways/', '/travel/'],

    // How many live headlines to show
    'limit' => 10,

    // Minutes a fetched list is reused before checking the feeds again
    'cache_minutes' => 5,

    // Feed images that are just a publisher logo, not the article photo (substring match)
    'ignore_images' => ['Business_Standard_1_685x385'],

    /*
    |--------------------------------------------------------------------------
    | News Inbox (Admin -> News Inbox, filled by `php artisan news:collect`)
    |--------------------------------------------------------------------------
    |
    | Publishers' public RSS feeds. Only the headline, the feed's short summary and the
    | link are kept - enough to write an original short report that credits the source.
    | 'exclude_words' and 'exclude' above also apply here.
    |
    */

    'inbox_feeds' => [
        ['source' => 'Economic Times',        'url' => 'https://economictimes.indiatimes.com/markets/rssfeeds/1977021501.cms'],
        ['source' => 'Economic Times',        'url' => 'https://economictimes.indiatimes.com/markets/stocks/rssfeeds/2146842.cms'],
        ['source' => 'Mint',                  'url' => 'https://www.livemint.com/rss/markets'],
        ['source' => 'Mint',                  'url' => 'https://www.livemint.com/rss/companies'],
        ['source' => 'Business Standard',     'url' => 'https://www.business-standard.com/rss/markets-106.rss'],
        ['source' => 'Business Standard',     'url' => 'https://www.business-standard.com/rss/companies-101.rss'],
        ['source' => 'Hindu BusinessLine',    'url' => 'https://www.thehindubusinessline.com/markets/feeder/default.rss'],
        ['source' => 'Hindu BusinessLine',    'url' => 'https://www.thehindubusinessline.com/companies/feeder/default.rss'],
        ['source' => 'CNBC-TV18',             'url' => 'https://www.cnbctv18.com/commonfeeds/v1/cne/rss/market.xml'],
        ['source' => 'CNBC-TV18',             'url' => 'https://www.cnbctv18.com/commonfeeds/v1/cne/rss/business.xml'],
    ],

    // Business keywords: each one found adds to a story's score (twice when it is in the headline).
    // Stories with no keyword at all are not collected.
    'inbox_keywords' => [
        'sensex', 'nifty', 'stock', 'stocks', 'share', 'shares', 'market', 'markets', 'ipo', 'listing', 'sebi', 'rbi',
        'bank', 'banks', 'banking', 'repo', 'inflation', 'gdp', 'rupee', 'gold', 'silver', 'crude', 'oil', 'fii', 'fpi', 'dii',
        'mutual fund', 'sip', 'dividend', 'bonus', 'buyback', 'results', 'profit', 'revenue', 'earnings', 'q1', 'q2', 'q3', 'q4',
        'quarter', 'order', 'acquisition', 'merger', 'stake', 'investment', 'investors', 'funding', 'rating', 'target price',
        'gst', 'tax', 'budget', 'exports', 'imports', 'auto', 'pharma', 'fmcg', 'metal', 'realty', 'psu', 'crore', 'lakh',
    ],

    // Days a collected story stays in the inbox (any status) before `news:prune` removes it.
    // Only the last 2 days are used to spot repeats, and auto-publish takes stories under 12 hours old.
    'inbox_keep_days' => 3,

    // Business News and Most Popular articles older than this are deleted daily by `news:prune`
    // (with their pictures), so only current news is stored. The newest 'limit' articles of each
    // are always kept, so the homepage never runs empty. Old links then open the News page.
    'keep_days' => (int) env('NEWS_KEEP_DAYS', 7),

    /*
    | "Write with AI": Google Gemini (Admin -> Settings -> Gemini AI), else Groq (Admin -> Settings),
    | else Hugging Face with HF_TOKEN (the token needs the "Make calls to Inference Providers"
    | permission; free accounts get a small monthly allowance). Each one is the backup of the one before.
    */
    'writer' => [
        'huggingface_model' => env('HF_TEXT_MODEL', 'meta-llama/Llama-3.1-8B-Instruct'),
        'daily_limit' => (int) env('AI_WRITER_DAILY_LIMIT', 100),
    ],
    /*
    | Every trading day (Mon-Fri, not on market holidays) at each of 'times' (India time), the Market Trend article with
    | the short link 'share_name' (investmentpoint.in/nifty) is rewritten from the day's real market figures,
    | with a new AI picture (php artisan nifty:daily-update; App\Services\NiftyDailyUpdater).
    */
    'nifty_daily' => [
        'enabled' => (bool) env('NIFTY_DAILY_UPDATE', true),
        // India time of each update (owner, 23 Oct 2026): morning and afternoon (the session so far, today's levels),
        // after the close (final, with the next session's levels)
        'times' => [
            'open' => env('NIFTY_OPEN_TIME', '11:11'),
            'midday' => env('NIFTY_MIDDAY_TIME', '14:02'),
            'close' => env('NIFTY_CLOSE_TIME', '17:00'),
        ],
        'share_name' => env('NIFTY_DAILY_SHARE_NAME', 'nifty'),
        // When the AI is busy: tries in all, and the wait between them
        'tries' => (int) env('NIFTY_DAILY_TRIES', 3),
        'retry_seconds' => (int) env('NIFTY_DAILY_RETRY_SECONDS', 20),
    ],
    /*
    | Auto-publish (php artisan news:auto-publish, every 30 minutes): the best inbox stories are
    | written by the free AI, given an AI image and published with a "Source" credit.
    | Kept small on purpose: Google treats sites that mass-publish AI articles as spam, and the
    | free AI pictures have a daily allowance.
    | A draft containing a number that is not in the original story is NOT published
    | (it stays in the News Inbox for you to check).
    */
    'auto' => [
        'enabled' => (bool) env('NEWS_AUTO_PUBLISH', true),
        // At most 20 attempts (published or held) a day
        'business_per_run' => 2,
        'business_per_day' => 10,
        'popular_per_run' => 1,
        'popular_per_day' => 3,
        'attempts_per_day' => 20,
        'min_score' => 3,          // business keyword score a story needs
        'max_age_hours' => 12,     // only fresh stories
        'images' => true,          // AI image for each article (free AI services only: App\Services\AiPictures)
    ],
];
