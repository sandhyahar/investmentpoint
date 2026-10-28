<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Live market data (Yahoo Finance, fetched server-side)
    |--------------------------------------------------------------------------
    |
    | Quotes are fetched by Laravel (not the browser) and cached briefly, so
    | every visitor shares one upstream request. The last good snapshot is
    | kept forever in the cache, so after market close / on holidays the
    | site keeps showing the final values of the last session.
    |
    */

    // Seconds between price updates while the market is open: how long a fresh snapshot is reused,
    // how often the scheduler refetches it, and how often open pages ask for it. 15s keeps the
    // load low on shared hosting (every poll is a PHP request); lower it on a bigger server.
    'cache_seconds' => env('MARKET_CACHE_SECONDS', 15),

    // Ticker bar = the first N rows of Admin -> Market Indices (by ID). Each row's name is
    // turned into a Yahoo symbol automatically (MRF -> MRF.NS) when the admin saves it.
    'ticker_count' => 4,

    // Names Yahoo's search doesn't map cleanly (matched case-insensitively)
    'aliases' => [
        'NIFTY'      => '^NSEI',
        'NIFTY 50'   => '^NSEI',
        'SENSEX'     => '^BSESN',
        'BSE SENSEX' => '^BSESN',
        'BANK NIFTY' => '^NSEBANK',
        'NIFTY BANK' => '^NSEBANK',
        'INDIA VIX'  => '^INDIAVIX',
        'VIX'        => '^INDIAVIX',
        'GOLD'       => 'GOLD',
        'SILVER'     => 'SILVER',
    ],

    // Prices in rupees worked out from an international USD quote: Yahoo symbol × USD→INR × factor,
    // then India's import duty and GST are added (india_taxes below), like the Indian retail rate.
    // A troy ounce is 31.1035 g, so Gold = ₹ per 10 g and Silver = ₹ per kg.
    'india_taxes' => [
        // Change these if the government changes the rates (percent)
        'import_duty' => (float) env('MARKET_IMPORT_DUTY', 15),
        'gst' => (float) env('MARKET_GST', 3),
    ],
    'usd_inr' => 'USDINR=X',
    'derived' => [
        'GOLD'   => ['source' => 'GC=F', 'factor' => 10 / 31.1035],
        'SILVER' => ['source' => 'SI=F', 'factor' => 1000 / 31.1035],
    ],

    // Yahoo delays these indices (SENSEX by ~15 min), so they are read real-time from Google Finance
    'google_ids' => [
        '^NSEI'    => 'NIFTY_50:INDEXNSE',
        '^BSESN'   => 'SENSEX:INDEXBOM',
        '^NSEBANK' => 'NIFTY_BANK:INDEXNSE',
    ],

    // This one Google Finance page embeds live SENSEX, NIFTY 50 and NIFTY BANK quotes
    'google_page' => 'https://www.google.com/finance/quote/SENSEX:INDEXBOM?hl=en',

    // Top gainers / losers are ranked from these NIFTY 50 constituents (Yahoo symbol => name).
    // Update this list when NSE rebalances the index.
    'stocks' => [
        'ADANIENT.NS'   => 'Adani Enterprises',
        'ADANIPORTS.NS' => 'Adani Ports',
        'APOLLOHOSP.NS' => 'Apollo Hospitals',
        'ASIANPAINT.NS' => 'Asian Paints',
        'AXISBANK.NS'   => 'Axis Bank',
        'BAJAJ-AUTO.NS' => 'Bajaj Auto',
        'BAJFINANCE.NS' => 'Bajaj Finance',
        'BAJAJFINSV.NS' => 'Bajaj Finserv',
        'BEL.NS'        => 'Bharat Electronics',
        'BHARTIARTL.NS' => 'Bharti Airtel',
        'CIPLA.NS'      => 'Cipla',
        'COALINDIA.NS'  => 'Coal India',
        'DRREDDY.NS'    => "Dr. Reddy's",
        'EICHERMOT.NS'  => 'Eicher Motors',
        'ETERNAL.NS'    => 'Eternal (Zomato)',
        'GRASIM.NS'     => 'Grasim',
        'HCLTECH.NS'    => 'HCL Tech',
        'HDFCBANK.NS'   => 'HDFC Bank',
        'HDFCLIFE.NS'   => 'HDFC Life',
        'HINDALCO.NS'   => 'Hindalco',
        'HINDUNILVR.NS' => 'Hindustan Unilever',
        'ICICIBANK.NS'  => 'ICICI Bank',
        'INDIGO.NS'     => 'InterGlobe Aviation',
        'INFY.NS'       => 'Infosys',
        'ITC.NS'        => 'ITC',
        'JIOFIN.NS'     => 'Jio Financial',
        'JSWSTEEL.NS'   => 'JSW Steel',
        'KOTAKBANK.NS'  => 'Kotak Bank',
        'LT.NS'         => 'Larsen & Toubro',
        'M&M.NS'        => 'Mahindra & Mahindra',
        'MARUTI.NS'     => 'Maruti Suzuki',
        'MAXHEALTH.NS'  => 'Max Healthcare',
        'NESTLEIND.NS'  => 'Nestle India',
        'NTPC.NS'       => 'NTPC',
        'ONGC.NS'       => 'ONGC',
        'POWERGRID.NS'  => 'Power Grid',
        'RELIANCE.NS'   => 'Reliance Ind.',
        'SBILIFE.NS'    => 'SBI Life',
        'SBIN.NS'       => 'SBI',
        'SHRIRAMFIN.NS' => 'Shriram Finance',
        'SUNPHARMA.NS'  => 'Sun Pharma',
        'TATACONSUM.NS' => 'Tata Consumer',
        'TMPV.NS'       => 'Tata Motors',
        'TATASTEEL.NS'  => 'Tata Steel',
        'TCS.NS'        => 'TCS',
        'TECHM.NS'      => 'Tech Mahindra',
        'TITAN.NS'      => 'Titan',
        'TRENT.NS'      => 'Trent',
        'ULTRACEMCO.NS' => 'UltraTech Cement',
        'WIPRO.NS'      => 'Wipro',
    ],

    // How many gainers / losers to show
    'movers_count' => 5,
];
