<?php

namespace App\Services;

use App\Models\MarketIndex;
use Carbon\Carbon;
use Illuminate\Http\Client\Pool;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Live Indian market quotes, fetched server-side. The ticker shows whatever the
 * admin put in the first rows of Market Indices (any NSE/BSE stock or index);
 * NIFTY / SENSEX / BANK NIFTY come real-time from Google Finance, everything
 * else (incl. the NIFTY 50 stocks for gainers/losers) from Yahoo Finance.
 *
 * One upstream refresh is shared by all visitors (short cache while the market
 * is open). The last good snapshot is kept forever, so after 15:30 IST, on
 * weekends and holidays the site keeps showing the final values of the last
 * session - no database needed.
 */
class MarketDataService
{
    private const SPARK_URL = 'https://query1.finance.yahoo.com/v7/finance/spark';
    private const SNAPSHOT_KEY = 'market.snapshot';
    private const LAST_GOOD_KEY = 'market.last_good';
    private const REFRESHING_KEY = 'market.refreshing';
    private const SEARCH_URL = 'https://query1.finance.yahoo.com/v1/finance/search';
    private const USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0 Safari/537.36';
    private const CHUNK = 20; // Yahoo spark accepts up to 20 symbols per call

    /** Drop cached values, e.g. after the admin renames a ticker row. */
    public static function flush(): void
    {
        Cache::forget(self::SNAPSHOT_KEY);
        Cache::forget(self::LAST_GOOD_KEY);
    }

    /**
     * Never keeps a visitor waiting on Yahoo / Google (a refresh takes a few seconds): once any
     * values exist, the last ones are returned at once and a single refresh runs after the
     * response (the scheduled market:refresh normally keeps them fresh before they expire).
     */
    public function snapshot(): array
    {
        $cached = Cache::get(self::SNAPSHOT_KEY);
        if ($cached) {
            return $cached;
        }

        $lastGood = Cache::get(self::LAST_GOOD_KEY);
        if ($lastGood) {
            if (Cache::add(self::REFRESHING_KEY, true, 30)) {
                app()->terminating(function () {
                    try {
                        $this->refresh();
                    } finally {
                        Cache::forget(self::REFRESHING_KEY);
                    }
                });
            }

            return $lastGood;
        }

        return $this->refresh(); // very first visit: nothing to show yet
    }

    /** Fetch fresh quotes now (market:refresh, or after a response when the cache ran out). */
    public function refresh(): array
    {
        $lastGood = Cache::get(self::LAST_GOOD_KEY);

        try {
            $tickers = $this->tickerRows();
            $symbols = array_merge(['^NSEI'], $tickers->pluck('symbol')->filter()->all(), array_keys(config('market.stocks')));
            $snapshot = $this->build($tickers, $this->fetchQuotes(array_values(array_unique($symbols))), $lastGood);
        } catch (Throwable $e) {
            Log::warning('Market data fetch failed: ' . $e->getMessage());
            $snapshot = null;
        }

        if ($snapshot) {
            Cache::forever(self::LAST_GOOD_KEY, $snapshot);
            $ttl = $snapshot['market_open'] ? (int) config('market.cache_seconds', 5) : 60;
            Cache::put(self::SNAPSHOT_KEY, $snapshot, $ttl);

            return $snapshot;
        }

        // Upstream down: serve the last known values and retry in 30s
        $fallback = $lastGood ?: $this->empty();
        $fallback['stale'] = true;
        Cache::put(self::SNAPSHOT_KEY, $fallback, 30);

        return $fallback;
    }

    /**
     * The snapshot with gain / loss spelled out on every quote, for API users:
     * status (gain|loss|unchanged), gain and loss in points (the other one is 0) and a ready-made
     * change_text like "+315.20 (+0.43%)", plus a count of how many items are up or down.
     */
    public static function withGainLoss(array $snapshot): array
    {
        $summary = ['gain' => 0, 'loss' => 0, 'unchanged' => 0];
        $enrich = function (?array $q) use (&$summary) {
            if (!$q) {
                return $q;
            }
            $change = (float) ($q['change'] ?? 0);
            $pct = (float) ($q['change_percent'] ?? 0);
            $status = $pct > 0 ? 'gain' : ($pct < 0 ? 'loss' : 'unchanged');
            $summary[$status]++;
            $sign = fn ($n) => ($n > 0 ? '+' : ($n < 0 ? '-' : '')) . number_format(abs($n), 2);

            return $q + [
                'status' => $status,
                'gain' => $status === 'gain' ? round(abs($change), 2) : 0,
                'loss' => $status === 'loss' ? round(abs($change), 2) : 0,
                'change_text' => $sign($change) . ' (' . $sign($pct) . '%)',
            ];
        };

        foreach (['indices', 'gainers', 'losers'] as $list) {
            $snapshot[$list] = array_map($enrich, $snapshot[$list] ?? []);
        }
        $snapshot['summary'] = $summary;

        return $snapshot;
    }

    /** Last stored snapshot without any network call (for instant first paint). */
    public function cachedSnapshot(): ?array
    {
        return Cache::get(self::SNAPSHOT_KEY) ?: Cache::get(self::LAST_GOOD_KEY);
    }

    /**
     * Turn an admin-typed name into a Yahoo symbol: "MRF" -> MRF.NS, "Tata Motors" -> TMPV.NS,
     * "NIFTY IT" -> ^CNXIT. Anything already shaped like a symbol (MRF.NS, ^NSEI) is kept.
     * Prefers NSE, then BSE listings. Returns null when nothing matches.
     */
    public function resolveSymbol(string $name): ?string
    {
        $name = trim(preg_replace('/\s+/', ' ', $name));
        $aliases = array_change_key_case(config('market.aliases', []), CASE_UPPER);
        if (isset($aliases[strtoupper($name)])) {
            return $aliases[strtoupper($name)];
        }
        if (isset(config('market.derived', [])[strtoupper($name)]) || preg_match('/^\^[A-Z0-9.\-]+$|^[A-Z0-9&\-]+\.(NS|BO)$/i', $name)) {
            return strtoupper($name);
        }

        return Cache::remember('market.symbol.' . md5(strtoupper($name)), 86400, function () use ($name) {
            try {
                $quotes = Http::withHeaders(['User-Agent' => self::USER_AGENT])->timeout(8)
                    ->get(self::SEARCH_URL, ['q' => $name, 'quotesCount' => 10, 'newsCount' => 0, 'region' => 'IN', 'lang' => 'en-IN'])
                    ->json('quotes') ?? [];
            } catch (Throwable $e) {
                return null;
            }
            $quotes = array_filter($quotes, fn ($q) => in_array($q['quoteType'] ?? '', ['EQUITY', 'INDEX', 'ETF'], true));
            foreach (['NSI', 'BSE'] as $exchange) {
                foreach ($quotes as $q) {
                    if (($q['exchange'] ?? '') === $exchange) {
                        return $q['symbol'];
                    }
                }
            }

            return null;
        }) ?: null;
    }

    /** One live quote (used by the admin form to check a symbol before saving). */
    public function quote(string $symbol): ?array
    {
        $quotes = $this->fetchQuotes([$symbol]);
        $google = config('market.google_ids')[$symbol] ?? null;
        $quote = ($google ? ($quotes['google:' . $google] ?? null) : null) ?? ($quotes[$symbol] ?? null);

        return $quote ? $this->quoteFields($quote) : null;
    }

    /** Ticker rows, filling in any missing symbol (e.g. rows created before symbols existed). */
    private function tickerRows()
    {
        $rows = MarketIndex::tickers();
        foreach ($rows as $row) {
            if (!$row->symbol && ($symbol = $this->resolveSymbol($row->name))) {
                $row->symbol = $symbol;
                $row->saveQuietly(); // quietly: don't flush the cache we're about to fill
            }
        }

        return $rows;
    }

    /** @return array<string, array{price: float, previous_close: float, time: int}> keyed by Yahoo symbol (and "google:ID") */
    private function fetchQuotes(array $symbols): array
    {
        // GOLD / SILVER aren't real Yahoo symbols: fetch their USD source and the USD→INR rate instead
        $derived = array_intersect_key(config('market.derived', []), array_flip($symbols));
        if ($derived) {
            $symbols = array_diff($symbols, array_keys($derived));
            $symbols = array_values(array_unique(array_merge($symbols, array_column($derived, 'source'), [config('market.usd_inr')])));
        }

        $chunks = array_chunk($symbols, self::CHUNK);
        $userAgent = self::USER_AGENT;
        $needsGoogle = (bool) array_intersect($symbols, array_keys(config('market.google_ids', [])));

        $responses = Http::pool(function (Pool $pool) use ($chunks, $userAgent, $needsGoogle) {
            $requests = [];
            if ($needsGoogle) {
                $requests[] = $pool->as('google')->withHeaders(['User-Agent' => $userAgent, 'Accept-Language' => 'en'])
                    ->timeout(8)
                    ->get(config('market.google_page'));
            }
            foreach ($chunks as $i => $chunk) {
                $requests[] = $pool->as('yahoo' . $i)->withHeaders(['User-Agent' => $userAgent])
                    ->timeout(8)
                    ->get(self::SPARK_URL, ['symbols' => implode(',', $chunk), 'range' => '1d', 'interval' => '5m']);
            }

            return $requests;
        });

        $quotes = [];
        foreach ($responses as $name => $response) {
            if (!$response instanceof \Illuminate\Http\Client\Response || !$response->successful()) {
                continue;
            }
            if ($name === 'google') {
                $quotes += $this->parseGoogle($response->body());
                continue;
            }
            foreach ($response->json('spark.result') ?? [] as $result) {
                $meta = $result['response'][0]['meta'] ?? null;
                $price = $meta['regularMarketPrice'] ?? null;
                $previous = $meta['chartPreviousClose'] ?? ($meta['previousClose'] ?? null);
                if (!is_numeric($price) || !is_numeric($previous) || (float) $previous == 0.0) {
                    continue;
                }
                $quotes[$result['symbol']] = [
                    'price' => (float) $price,
                    'previous_close' => (float) $previous,
                    'time' => (int) ($meta['regularMarketTime'] ?? 0),
                ];
            }
        }

        $fx = $quotes[config('market.usd_inr')] ?? null;
        // Indian price = world price x USD/INR x unit, plus India's import duty and GST
        // (e.g. gold: 15% duty, 3% GST - config market.india_taxes)
        $taxes = (1 + (float) config('market.india_taxes.import_duty', 0) / 100) * (1 + (float) config('market.india_taxes.gst', 0) / 100);
        foreach ($derived as $symbol => $rule) {
            $rule['factor'] *= $taxes;
            $source = $quotes[$rule['source']] ?? null;
            if ($source && $fx) {
                $quotes[$symbol] = [
                    'price' => $source['price'] * $fx['price'] * $rule['factor'],
                    'previous_close' => $source['previous_close'] * $fx['previous_close'] * $rule['factor'],
                    'time' => $source['time'],
                ];
            }
        }

        return $quotes;
    }

    /**
     * Google Finance embeds quotes as JSON tuples in the page, e.g.
     * ["SENSEX","INDEXBOM"],"BSE SENSEX",1,null,[73752.15,171.6,0.23,...],null,73580.54,null,null,null,[1790311425]
     * The same index appears several times; keep the newest one.
     *
     * @return array<string, array{price: float, previous_close: float, time: int}> keyed "google:SENSEX:INDEXBOM"
     */
    private function parseGoogle(string $html): array
    {
        $pattern = '/\["([A-Z0-9_]+)","(INDEX[A-Z]+)"\],"[^"]*",\d+,null,\[(-?[\d.]+),[^\]]*\],null,(-?[\d.]+),null,null,null,\[(\d+)\]/';
        preg_match_all($pattern, $html, $matches, PREG_SET_ORDER);

        $quotes = [];
        foreach ($matches as $m) {
            $key = 'google:' . $m[1] . ':' . $m[2];
            $time = (int) $m[5];
            if ((float) $m[4] == 0.0 || (isset($quotes[$key]) && $quotes[$key]['time'] >= $time)) {
                continue;
            }
            $quotes[$key] = ['price' => (float) $m[3], 'previous_close' => (float) $m[4], 'time' => $time];
        }

        return $quotes;
    }

    private function build($tickers, array $quotes, ?array $lastGood): ?array
    {
        if (!$quotes) {
            return null;
        }

        $googleIds = config('market.google_ids', []);
        $indices = [];
        foreach ($tickers as $row) {
            $key = 'm' . $row->id;
            $google = $googleIds[$row->symbol] ?? null;
            $quote = ($google ? ($quotes['google:' . $google] ?? null) : null) ?? ($quotes[$row->symbol] ?? null);
            $previous = $lastGood['indices'][$key] ?? null;
            // Keep the previous value for a row Yahoo skipped this round (only if it is still the same symbol)
            $indices[$key] = $quote
                ? array_merge(['name' => $row->name, 'symbol' => $row->symbol], $this->quoteFields($quote))
                : (($previous['symbol'] ?? null) === $row->symbol ? $previous : null);
        }

        $stocks = [];
        foreach (config('market.stocks') as $symbol => $name) {
            if (isset($quotes[$symbol])) {
                $stocks[] = array_merge(['name' => $name, 'symbol' => str_replace('.NS', '', $symbol)], $this->quoteFields($quotes[$symbol]));
            }
        }

        $count = (int) config('market.movers_count', 5);
        $gainers = array_values(array_filter($stocks, fn ($s) => $s['change_percent'] > 0));
        $losers = array_values(array_filter($stocks, fn ($s) => $s['change_percent'] < 0));
        usort($gainers, fn ($a, $b) => $b['change_percent'] <=> $a['change_percent']);
        usort($losers, fn ($a, $b) => $a['change_percent'] <=> $b['change_percent']);

        if (!$stocks && $lastGood) {
            $gainers = $lastGood['gainers'];
            $losers = $lastGood['losers'];
        }

        $asOf = $quotes['google:NIFTY_50:INDEXNSE']['time'] ?? ($quotes['^NSEI']['time'] ?? max(array_column($quotes, 'time')));

        return [
            'indices' => $indices,
            'gainers' => array_slice($gainers, 0, $count),
            'losers' => array_slice($losers, 0, $count),
            'market_open' => $this->isMarketOpen($asOf),
            'as_of' => $asOf ? Carbon::createFromTimestamp($asOf, 'Asia/Kolkata')->toIso8601String() : null,
            'stale' => false,
        ];
    }

    private function quoteFields(array $quote): array
    {
        $change = $quote['price'] - $quote['previous_close'];

        return [
            'price' => round($quote['price'], 2),
            'previous_close' => round($quote['previous_close'], 2),
            'change' => round($change, 2),
            'change_percent' => round($change / $quote['previous_close'] * 100, 2),
        ];
    }

    /** NSE regular session 09:15-15:30 IST on weekdays, and data actually moving (catches holidays). */
    private function isMarketOpen(int $lastTradeTime): bool
    {
        $now = Carbon::now('Asia/Kolkata');
        $minutes = $now->hour * 60 + $now->minute;

        return $now->isWeekday()
            && $minutes >= 9 * 60 + 15
            && $minutes <= 15 * 60 + 30
            && $lastTradeTime > 0
            && $now->timestamp - $lastTradeTime < 15 * 60;
    }

    private function empty(): array
    {
        return ['indices' => [], 'gainers' => [], 'losers' => [], 'market_open' => false, 'as_of' => null, 'stale' => true];
    }
}
