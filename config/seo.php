<?php

return [
    'site_name' => 'Investment Point',

    'default_description' => 'Investment Point brings daily stock market news, Nifty and Sensex updates, technical analysis, market trends, expert views and premium investment insights.',

    // Picture shown when a page is shared on WhatsApp / Facebook / LinkedIn and has no picture of its own
    'default_image' => 'images/logo-default.png',

    // After the site is live: Google Search Console -> Add property -> "HTML tag" method -> copy the content="..." value here
    'google_verification' => env('GOOGLE_SITE_VERIFICATION'),

    // Google Analytics 4 "Measurement ID" (G-XXXXXXXXXX): visitors, sources, most read pages
    'google_analytics_id' => env('GOOGLE_ANALYTICS_ID'),

    // Google Ads: account tag (AW-XXXXXXXXX) and the label of a "Subscription lead" conversion action,
    // so Ads can see which searches bring readers who call / WhatsApp about a subscription
    'google_ads_id' => env('GOOGLE_ADS_ID'),
    'google_ads_lead_label' => env('GOOGLE_ADS_LEAD_LABEL'),

    // Blocks right-click, Inspect / View-source keys, text selection and copying on the public site.
    // On by default on the live site (APP_ENV=production), off while developing. Never applies to the admin panel.
    'protect_content' => (bool) env('CONTENT_PROTECTION', env('APP_ENV') === 'production'),
];
