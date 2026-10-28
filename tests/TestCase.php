<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    protected function setUp(): void
    {
        parent::setUp();

        // The homepage reads live news feeds; never hit the real sites from tests
        Http::fake([
            'economictimes.indiatimes.com/*' => Http::response(self::rss('Economic Times test headline', 'https://economictimes.indiatimes.com/markets/stocks/news/test-story')),
            'www.livemint.com/*' => Http::response(self::rss('LiveMint test headline', 'https://www.livemint.com/market/stock-market-news/test-story')),
        ]);
    }

    /** Gemini is set up (a key in config, as GEMINI_API_KEY in .env) */
    protected static function geminiOn(): void
    {
        config(['services.gemini_ai.key' => 'AIzaTestKey0123456789012345678901234567']);
    }

    /** A Google Gemini API reply (generateContent) with this text */
    protected static function geminiSays(string $text)
    {
        return Http::response(['candidates' => [['content' => ['role' => 'model', 'parts' => [['text' => $text]]], 'finishReason' => 'STOP']]]);
    }

    /** Gemini over its limit (or out of balance) */
    protected static function geminiLimit()
    {
        return Http::response(['error' => ['code' => 429, 'message' => 'Resource has been exhausted (e.g. check quota).', 'status' => 'RESOURCE_EXHAUSTED']], 429);
    }

    /** The free Cloudflare Workers AI picture service is set up (keys in config, as in .env) */
    protected static function cloudflareOn(): void
    {
        config(['services.cloudflare_ai.account_id' => 'acc123', 'services.cloudflare_ai.token' => 'cf-token']);
    }

    /** A Cloudflare Workers AI FLUX picture */
    protected static function cloudflarePicture(string $bytes = 'fake-jpeg')
    {
        return Http::response(['result' => ['image' => base64_encode($bytes)], 'success' => true]);
    }

    protected static function rss(string $title, string $link): string
    {
        $date = now()->toRfc2822String();

        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0"><channel><title>Feed</title>
<item><title><![CDATA[{$title}]]></title><link>{$link}</link><description><![CDATA[Summary of {$title}]]></description><pubDate>{$date}</pubDate><enclosure url="https://img.example.com/photo.jpg" type="image/jpeg"/></item>
</channel></rss>
XML;
    }
}
