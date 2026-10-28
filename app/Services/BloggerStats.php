<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The "Total Pageviews" numbers of a Blogger blog (its Stats widget): the all-time total and
 * the last 30 days, for the little graph. Blogger updates them every ~20 minutes.
 *
 * The widget's data address carries a token that only appears in the blog's home page,
 * so the page is read first, then the numbers.
 */
class BloggerStats
{
    private const MINUTES = 20;

    /**
     * Cached numbers, never waiting for Blogger: when they are old (or missing) they are
     * refreshed after the page has been sent. Null until the first refresh has finished.
     *
     * @return array{total: int, daily: int[]}|null
     */
    public function get(string $blog): ?array
    {
        $key = 'blogger-stats.' . md5($blog);
        $stats = Cache::get($key . '.last');

        if (!Cache::has($key) && Cache::add($key . '.refreshing', true, 60)) {
            app()->terminating(fn () => $this->refresh($blog));
        }

        return $stats;
    }

    /** Download the numbers now (also run by newspaper:import every hour). */
    public function refresh(string $blog): ?array
    {
        $key = 'blogger-stats.' . md5($blog);

        try {
            $html = Http::timeout(15)->get(rtrim($blog, '/') . '/')->throw()->body();
            if (!preg_match("/'statsUrl':\s*'([^']+)'/", $html, $m)) {
                return null;
            }
            $url = 'https:' . str_replace(['\x3d', '\x26'], ['=', '&'], $m[1]);
            $data = Http::timeout(15)->get($url)->throw()->json();
            if (!isset($data['total'])) {
                return null;
            }

            $stats = [
                'total' => (int) $data['total'],
                'daily' => array_map(fn ($point) => (int) ($point[1] ?? 0), $data['sparklineData'] ?? []),
            ];
            Cache::put($key, true, now()->addMinutes(self::MINUTES));
            Cache::forever($key . '.last', $stats);

            return $stats;
        } catch (Throwable $e) {
            Log::warning('Blogger stats failed: ' . $e->getMessage());
            Cache::put($key, true, now()->addMinutes(5)); // try again a bit later

            return null;
        } finally {
            Cache::forget($key . '.refreshing');
        }
    }
}
