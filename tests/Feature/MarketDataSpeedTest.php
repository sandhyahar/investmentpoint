<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MarketDataSpeedTest extends TestCase
{
    use RefreshDatabase;

    public function test_market_data_answers_from_the_last_values_without_waiting_for_yahoo(): void
    {
        Http::fake(); // any upstream call is recorded
        Cache::forget('market.snapshot');
        Cache::forever('market.last_good', ['indices' => [], 'gainers' => [], 'losers' => [], 'market_open' => true, 'marker' => 'last-good']);

        $sentBeforeResponse = null;
        $this->app->terminating(function () use (&$sentBeforeResponse) {
            $sentBeforeResponse ??= count(Http::recorded());
        });

        // The last values come back at once; the refresh only starts after the response
        $this->getJson('/market-data')->assertOk()->assertJsonPath('marker', 'last-good');
        $this->assertSame(0, $sentBeforeResponse);
        $this->assertNotEmpty(Http::recorded(), 'a background refresh ran after the response');
    }

    public function test_market_refresh_command_runs(): void
    {
        Http::fake(['*' => Http::response([], 500)]);
        Cache::forever('market.last_good', ['indices' => [], 'gainers' => [], 'losers' => [], 'market_open' => false]);

        $this->artisan('market:refresh')->assertSuccessful();
        $this->assertNotNull(Cache::get('market.snapshot'));
    }
}
