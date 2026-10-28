<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'razorpay' => [
        'key' => env('RAZORPAY_KEY'),
        'secret' => env('RAZORPAY_SECRET'),
        'webhook_secret' => env('RAZORPAY_WEBHOOK_SECRET'),
    ],

    // AI pictures (admin "Generate with AI" and auto-published news): free services only, tried in this
    // order (App\Services\AiPictures). Gemini is not used for pictures (it needs billing for them).
    //  1. Cloudflare Workers AI, if both keys are set (free plan: 10,000 neurons/day, about 100 FLUX
    //     pictures; resets at 5:30 AM India time). Token needs the "Workers AI" permission.
    'cloudflare_ai' => [
        'account_id' => env('CLOUDFLARE_ACCOUNT_ID'),
        'token' => env('CLOUDFLARE_API_TOKEN'),
        'image_model' => env('CLOUDFLARE_IMAGE_MODEL', '@cf/black-forest-labs/flux-1-schnell'),
        // Market Trend / Nifty pictures: a real 16:9 picture that follows the prompt closely (FLUX.1 above is the backup)
        'premium_model' => env('CLOUDFLARE_PREMIUM_IMAGE_MODEL', '@cf/black-forest-labs/flux-2-klein-9b'),
    ],
    //  2. Pollinations.ai. Works with no key (a small "pollinations.ai" mark in a corner, so only used by the
    //     admin button, never for auto-published news); a free key from enter.pollinations.ai gives clean pictures.
    'pollinations' => [
        'enabled' => env('POLLINATIONS_ENABLED', true),
        'key' => env('POLLINATIONS_API_KEY'),
        'model' => env('POLLINATIONS_MODEL', 'flux'),
    ],
    //  3. The free FLUX.1 [schnell] Hugging Face Space. Works with no key (about 3 images/day per
    //     internet connection); a free Hugging Face access token (HF_TOKEN) gives a bigger daily allowance.
    'huggingface_ai' => [
        'token' => env('HF_TOKEN'),
        'space' => env('HF_IMAGE_SPACE', 'https://black-forest-labs-flux-1-schnell.hf.space'),
    ],

    // Real stock photos, used first for news pictures and by the admin "Find real photo" button (the AI
    // image is the backup). Either library works; Pixabay is used when both keys are set.
    //  - Pixabay (free key on pixabay.com/api/docs after login; 100 requests/minute)
    //  - Pexels (free: 200 requests/hour; new keys were paused in September 2026)
    // Both ask for credit: "Photos provided by ..." in the footer and the photographer under news photos.
    'pixabay' => [
        'key' => env('PIXABAY_API_KEY'),
    ],
    'pexels' => [
        'key' => env('PEXELS_API_KEY'),
    ],
    //  - Openverse (WordPress.org; NO key needed: 200 searches/day, 20/minute). Used by the admin
    //    "Find real photo" button when neither key above is set. Only public-domain photos (CC0 / PDM),
    //    so no credit is required. Not used for auto-published news (an admin should check the picture).
    'openverse' => [
        'enabled' => env('OPENVERSE_ENABLED', true),
    ],

    // Google Gemini API (aistudio.google.com): the main AI. With a key (Admin -> Settings -> Gemini AI, or
    // GEMINI_API_KEY here) articles, titles and translations are written by Gemini 3.1 Flash-Lite (text
    // only: the AI pictures come from the free services above). Groq, then Hugging Face, are the backups for text.
    'gemini_ai' => [
        'key' => env('GEMINI_API_KEY'),
        // Text: tried in this order when a key is saved; the first one the account can use is remembered
        'models' => array_values(array_filter(array_map('trim', explode(',', (string) env('GEMINI_TEXT_MODELS', 'gemini-3.1-flash-lite,gemini-3.1-flash-lite-preview'))))),
        // Admin "Generate with AI" pictures per day when nothing is set in Admin -> Settings
        'daily_images' => (int) env('AI_IMAGE_DAILY_LIMIT', 30),
    ],

    // Groq (console.groq.com): a free AI for writing text, the backup when Gemini is not set up or fails.
    'groq_ai' => [
        'key' => env('GROQ_API_KEY'),
        // Tried in this order when a key is saved; the first one the account can use is remembered
        'models' => array_values(array_filter(array_map('trim', explode(',', (string) env('GROQ_TEXT_MODELS', 'llama-3.3-70b-versatile,openai/gpt-oss-120b,qwen/qwen3.8-27b,openai/gpt-oss-20b'))))),
    ],

];
