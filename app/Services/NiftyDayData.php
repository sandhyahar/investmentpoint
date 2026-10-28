<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Real figures of one Nifty trading day for the daily Nifty Derivative Market Trend Analysis:
 * Nifty open / high / low / close (Yahoo Finance daily candles, as used by the site's market ticker),
 * India VIX, NSE sector indices, major world indices, the latest FII / DII cash figures published by
 * NSE, and the next session's support / resistance from the standard (floor) pivot points of the day.
 * Never invents anything: a figure that cannot be fetched is left out.
 */
class NiftyDayData
{
    private const CHART_URL = 'https://query1.finance.yahoo.com/v8/finance/chart/';
    private const FII_DII_URL = 'https://www.nseindia.com/api/fiidiiTradeReact';
    private const USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0 Safari/537.36';

    public const SECTORS = [
        '^NSEBANK' => 'Banking', '^CNXIT' => 'IT', '^CNXAUTO' => 'Auto', '^CNXPHARMA' => 'Pharma', '^CNXFMCG' => 'FMCG',
        '^CNXMETAL' => 'Metal', '^CNXREALTY' => 'Realty', '^CNXENERGY' => 'Energy', '^CNXPSUBANK' => 'PSU Bank',
    ];

    public const GLOBAL = [
        '^GSPC' => 'S&P 500 (US)', '^IXIC' => 'Nasdaq (US)', '^DJI' => 'Dow Jones (US)',
        '^FTSE' => 'FTSE 100 (UK)', '^GDAXI' => 'DAX (Germany)', '^FCHI' => 'CAC 40 (France)',
        '^N225' => 'Nikkei (Japan)', '^HSI' => 'Hang Seng (Hong Kong)',
    ];

    /** Energy and currency: Brent crude (US$ a barrel), the US Dollar Index, and rupees per US dollar */
    public const MACRO = ['BZ=F' => 'Brent crude (US$ per barrel)', 'DX-Y.NYB' => 'US Dollar Index (DXY)', 'INR=X' => 'Rupee (₹ per US$)'];

    /**
     * @return array{date: Carbon, nifty: array, vix: ?array, sectors: array, global: array, fii_dii: ?array, levels: array}|null
     *         null when the Nifty figures cannot be fetched
     */
    public function latest(): ?array
    {
        $nifty = $this->day('^NSEI');
        if (!$nifty) {
            return null;
        }

        $sectors = [];
        foreach (self::SECTORS as $symbol => $name) {
            if ($d = $this->day($symbol)) {
                $sectors[] = ['name' => $name, 'close' => $d['close'], 'change_pct' => $d['change_pct'], 'date' => $d['date']];
            }
        }
        // Only sectors of the same trading day, best first
        $sectors = array_values(array_filter($sectors, fn ($s) => $s['date']->isSameDay($nifty['date'])));
        usort($sectors, fn ($a, $b) => $b['change_pct'] <=> $a['change_pct']);

        $global = [];
        foreach (self::GLOBAL as $symbol => $name) {
            if ($d = $this->day($symbol)) {
                $global[] = ['name' => $name, 'close' => $d['close'], 'change_pct' => $d['change_pct'], 'date' => $d['date']];
            }
        }

        $vix = $this->day('^INDIAVIX');
        $sensex = $this->day('^BSESN');
        $macro = [];
        foreach (self::MACRO as $symbol => $name) {
            if ($d = $this->day($symbol)) {
                $macro[] = ['name' => $name, 'close' => $d['close'], 'change_pct' => $d['change_pct'], 'date' => $d['date']];
            }
        }

        return [
            'date' => $nifty['date'],
            'nifty' => $nifty,
            'sensex' => $sensex && $sensex['date']->isSameDay($nifty['date']) ? $sensex : null,
            'vix' => $vix && $vix['date']->isSameDay($nifty['date']) ? $vix : null,
            'macro' => $macro,
            'moving_averages' => $this->movingAverages(),
            'breadth' => $this->breadth(),
            'headlines' => $this->headlines(),
            'sectors' => $sectors,
            'global' => $global,
            'fii_dii' => $this->fiiDii(),
            // Next session: pivots of this session. During the session (9:30 AM update): pivots of the previous one
            'levels' => self::levelsAround($nifty['close'], self::pivotLevels($nifty['high'], $nifty['low'], $nifty['close'])),
            'levels_today' => self::levelsAround($nifty['close'], self::pivotLevels($nifty['prev_high'], $nifty['prev_low'], $nifty['prev_close'])),
        ];
    }

    /**
     * Standard floor pivots of a session, rounded to the nearest 10 points:
     * P = (H + L + C) / 3, S1 = 2P - H, S2 = P - (H - L), S3 = L - 2(H - P), R1 = 2P - L, R2 = P + (H - L), R3 = H + 2(P - L)
     */
    public static function pivotLevels(float $high, float $low, float $close): array
    {
        $p = ($high + $low + $close) / 3;
        $r10 = fn (float $v) => (int) (round($v / 10) * 10);

        return ['pivot' => $r10($p), 's1' => $r10(2 * $p - $high), 's2' => $r10($p - ($high - $low)), 's3' => $r10($low - 2 * ($high - $p)),
            'r1' => $r10(2 * $p - $low), 'r2' => $r10($p + ($high - $low)), 'r3' => $r10($high + 2 * ($p - $low))];
    }

    /**
     * Support / resistance for the article around the Nifty's current level: support = the two nearest pivot levels
     * BELOW it, resistance = the two nearest ABOVE it (after a big gap the classic S1 / S2 can be above the price).
     * Beyond S3 / R3 the next 100-point round numbers are used.
     *
     * @return array{s1: int, s2: int, r1: int, r2: int}
     */
    public static function levelsAround(float $price, array $pivots): array
    {
        $all = array_values(array_unique(array_map('intval', $pivots)));
        $below = array_values(array_filter($all, fn ($v) => $v < $price));
        $above = array_values(array_filter($all, fn ($v) => $v > $price));
        rsort($below);
        sort($above);
        $floor = (int) floor(($price - 1) / 100) * 100;
        $ceil = (int) ceil(($price + 1) / 100) * 100;
        $below = array_merge($below, [$floor, $floor - 100, $floor - 200]);
        $above = array_merge($above, [$ceil, $ceil + 100, $ceil + 200]);
        $below = array_values(array_unique($below));
        $above = array_values(array_unique($above));

        return ['s1' => $below[0], 's2' => $below[1], 'r1' => $above[0], 'r2' => $above[1]];
    }

    /** Last daily candle of a Yahoo symbol and its change on the candle before (rows without a close are skipped) */
    private function day(string $symbol): ?array
    {
        try {
            $response = Http::withHeaders(['User-Agent' => self::USER_AGENT])->connectTimeout(8)->timeout(20)
                ->get(self::CHART_URL . rawurlencode($symbol), ['range' => '10d', 'interval' => '1d']);
            $result = $response->json('chart.result.0');
            $quote = $result['indicators']['quote'][0] ?? null;
            if (!$response->successful() || !$quote || empty($result['timestamp'])) {
                return null;
            }
            $tz = $result['meta']['exchangeTimezoneName'] ?? 'Asia/Kolkata';
            $rows = [];
            foreach ($result['timestamp'] as $i => $time) {
                if (isset($quote['close'][$i], $quote['high'][$i], $quote['low'][$i])) {
                    $rows[] = ['time' => $time, 'open' => (float) ($quote['open'][$i] ?? $quote['close'][$i]), 'high' => (float) $quote['high'][$i],
                        'low' => (float) $quote['low'][$i], 'close' => (float) $quote['close'][$i]];
                }
            }
            if (count($rows) < 2) {
                return null;
            }
            [$prev, $last] = array_slice($rows, -2);

            return [
                'date' => Carbon::createFromTimestamp($last['time'], $tz)->startOfDay(),
                'open' => round($last['open'], 2), 'high' => round($last['high'], 2), 'low' => round($last['low'], 2), 'close' => round($last['close'], 2),
                'prev_close' => round($prev['close'], 2),
                'prev_high' => round($prev['high'], 2),
                'prev_low' => round($prev['low'], 2),
                'change' => round($last['close'] - $prev['close'], 2),
                'change_pct' => round(($last['close'] / $prev['close'] - 1) * 100, 2),
            ];
        } catch (Throwable $e) {
            Log::info("Nifty daily data: {$symbol} failed: " . $e->getMessage());

            return null;
        }
    }

    /** Latest provisional FII / DII cash-market figures published by NSE (₹ crore), or null */
    /** Nifty 50 simple moving averages of the daily closes (20, 50 and 200 days), or [] */
    private function movingAverages(): array
    {
        try {
            $closes = array_values(array_filter((array) Http::withHeaders(['User-Agent' => self::USER_AGENT])->connectTimeout(8)->timeout(20)
                ->get(self::CHART_URL . rawurlencode('^NSEI'), ['range' => '1y', 'interval' => '1d'])->json('chart.result.0.indicators.quote.0.close'), fn ($v) => $v !== null));
            $out = [];
            foreach ([20, 50, 200] as $days) {
                if (count($closes) >= $days) {
                    $out[$days] = round(array_sum(array_slice($closes, -$days)) / $days, 2);
                }
            }

            return $out;
        } catch (Throwable $e) {
            Log::info('Nifty daily data: moving averages failed: ' . $e->getMessage());

            return [];
        }
    }

    /** Nifty 50 market breadth from NSE: advances, declines, unchanged (null when NSE does not answer) */
    private function breadth(): ?array
    {
        try {
            $rows = Http::withHeaders(['User-Agent' => self::USER_AGENT, 'Referer' => 'https://www.nseindia.com/market-data/live-market-indices', 'Accept' => 'application/json'])
                ->connectTimeout(8)->timeout(20)->get('https://www.nseindia.com/api/allIndices')->json('data');
            $row = collect(is_array($rows) ? $rows : [])->firstWhere('index', 'NIFTY 50');

            return isset($row['advances'], $row['declines'])
                ? ['advances' => (int) $row['advances'], 'declines' => (int) $row['declines'], 'unchanged' => (int) ($row['unchanged'] ?? 0)]
                : null;
        } catch (Throwable $e) {
            Log::info('Nifty daily data: NSE breadth failed: ' . $e->getMessage());

            return null;
        }
    }

    /**
     * Real business headlines of the last 24 hours from the News Inbox (publishers' feeds), most widely carried
     * first: the only news the AI may name as a sector catalyst or an event to watch.
     */
    private function headlines(): array
    {
        return \App\Models\NewsInboxItem::where('published_at', '>=', now()->subDay())
            ->orderByDesc('coverage')->orderByDesc('score')->orderByDesc('published_at')
            ->take(15)->pluck('title')->map(fn ($t) => trim(preg_replace('/\s+/u', ' ', strip_tags((string) $t))))->filter()->unique()->values()->all();
    }

    private function fiiDii(): ?array
    {
        try {
            $rows = Http::withHeaders(['User-Agent' => self::USER_AGENT, 'Referer' => 'https://www.nseindia.com/reports/fii-dii', 'Accept' => 'application/json'])
                ->connectTimeout(8)->timeout(20)->get(self::FII_DII_URL)->json();
            $by = collect(is_array($rows) ? $rows : [])->keyBy(fn ($r) => str_starts_with((string) ($r['category'] ?? ''), 'FII') ? 'fii' : 'dii');
            if (!isset($by['fii']['netValue'], $by['dii']['netValue'], $by['fii']['date'])) {
                return null;
            }

            return [
                'date' => Carbon::createFromFormat('d-M-Y', $by['fii']['date'], 'Asia/Kolkata')->startOfDay(),
                'fii_net' => (int) round((float) $by['fii']['netValue']),
                'dii_net' => (int) round((float) $by['dii']['netValue']),
            ];
        } catch (Throwable $e) {
            Log::info('Nifty daily data: NSE FII/DII failed: ' . $e->getMessage());

            return null;
        }
    }
}
