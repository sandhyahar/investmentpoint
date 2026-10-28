<?php

namespace App\Console\Commands;

use App\Services\MarketDataService;
use Illuminate\Console\Command;

/**
 * Keeps the live market snapshot fresh in the background (scheduled every few seconds while
 * the market is open), so the ticker and /market-data never wait on Yahoo / Google.
 */
class RefreshMarketData extends Command
{
    protected $signature = 'market:refresh';

    protected $description = 'Fetch fresh market quotes into the cache';

    public function handle(MarketDataService $market): int
    {
        $snapshot = $market->refresh();
        $this->info('Market data refreshed' . (!empty($snapshot['stale']) ? ' (upstream unavailable, kept last values)' : '') . '.');

        return self::SUCCESS;
    }
}
