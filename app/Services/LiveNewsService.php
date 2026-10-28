<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Http\Client\Pool;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Latest Indian market headlines from public RSS feeds (Economic Times, LiveMint,
 * Business Standard), fetched server-side and cached for a few minutes.
 */
class LiveNewsService
{
    private const CACHE_KEY = 'news.live';
    private const LAST_GOOD_KEY = 'news.live.last_good';

    /**
     * @return array<int, array{title: string, url: string, image: ?string, excerpt: string, published_at: string, source: string}>
     */
    public function latest(?int $limit = null): array
    {
        $limit = $limit ?? (int) config('news.limit', 10);

        $items = Cache::get(self::CACHE_KEY);
        if ($items === null) {
            $lastGood = Cache::get(self::LAST_GOOD_KEY);
            if ($lastGood) {
                // Don't make this visitor wait for the feeds (up to 8s): show the last headlines
                // now and refresh once the page has been sent. The flag stops parallel refreshes.
                if (Cache::add(self::CACHE_KEY . '.refreshing', true, 60)) {
                    app()->terminating(fn () => $this->refresh());
                }
                $items = $lastGood;
            } else {
                $items = $this->refresh(); // very first visit: nothing to show yet
            }
        }

        return array_slice($items, 0, $limit);
    }

    /** Download the feeds and store the result; keeps the last headlines if the feeds are down. */
    private function refresh(): array
    {
        try {
            $items = $this->fetch();
        } catch (Throwable $e) {
            Log::warning('Live news fetch failed: ' . $e->getMessage());
            $items = [];
        }

        if ($items) {
            Cache::forever(self::LAST_GOOD_KEY, $items);
            Cache::put(self::CACHE_KEY, $items, now()->addMinutes((int) config('news.cache_minutes', 5)));
        } else {
            // Feeds down: keep showing the last headlines, try again in a minute
            $items = Cache::get(self::LAST_GOOD_KEY, []);
            Cache::put(self::CACHE_KEY, $items, now()->addMinute());
        }
        Cache::forget(self::CACHE_KEY . '.refreshing');

        return $items;
    }

    private function fetch(): array
    {
        $feeds = config('news.feeds', []);

        $responses = Http::pool(fn (Pool $pool) => array_map(
            fn ($feed) => $pool
                ->withHeaders(['User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/126.0 Safari/537.36'])
                ->timeout(8)
                ->get($feed['url']),
            $feeds
        ));

        $items = [];
        $seen = [];
        foreach ($responses as $i => $response) {
            if (!$response instanceof \Illuminate\Http\Client\Response || !$response->successful()) {
                continue;
            }
            foreach ($this->parse($response->body(), $feeds[$i]['source']) as $item) {
                if (!$this->isBusinessNews($item['url'], $feeds[$i]['include'] ?? []) || $this->hasExcludedWord($item['title'])) {
                    continue;
                }
                // Same story from two feeds: keep the first
                $key = Str::slug(Str::limit($item['title'], 60, ''));
                if ($key !== '' && !isset($seen[$key])) {
                    $seen[$key] = true;
                    $items[] = $item;
                }
            }
        }

        usort($items, fn ($a, $b) => strcmp($b['published_at'], $a['published_at']));

        return array_slice($items, 0, 60);
    }

    /** Headlines whose title or summary contains every word of the search (newest first). */
    public function search(string $query, ?int $limit = null): array
    {
        $this->latest(0); // make sure the cache is filled
        $words = array_filter(preg_split('/\s+/', Str::lower(trim($query))));
        $all = Cache::get(self::CACHE_KEY) ?? Cache::get(self::LAST_GOOD_KEY, []); // may still be refreshing
        $matches = array_filter($all, function ($item) use ($words) {
            $text = Str::lower($item['title'] . ' ' . $item['excerpt']);
            foreach ($words as $word) {
                if (!Str::contains($text, $word)) {
                    return false;
                }
            }
            return true;
        });

        return array_slice(array_values($matches), 0, $limit ?? (int) config('news.limit', 10));
    }

    /** Keep only articles from the feed's business sections, never from excluded ones. */
    public function isBusinessNews(string $url, array $include): bool
    {
        $path = strtolower((string) parse_url($url, PHP_URL_PATH));

        if (Str::contains($path, config('news.exclude', []))) {
            return false;
        }

        return !$include || Str::contains($path, $include);
    }

    /** Non-business headlines (crime, drugs, celebrities, sport, "strange" stories...). */
    public function hasExcludedWord(string $title): bool
    {
        foreach (config('news.exclude_words', []) as $word) {
            if (preg_match('/(?<![\p{L}\p{N}])' . preg_quote($word, '/') . '(?![\p{L}\p{N}])/iu', $title)) {
                return true;
            }
        }

        return false;
    }

    public function parse(string $xml, string $source): array
    {
        $previous = libxml_use_internal_errors(true);
        $feed = simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA);
        libxml_use_internal_errors($previous);
        if (!$feed || !isset($feed->channel->item)) {
            return [];
        }

        $items = [];
        foreach ($feed->channel->item as $entry) {
            $title = html_entity_decode(trim((string) $entry->title), ENT_QUOTES | ENT_HTML5);
            $url = trim((string) $entry->link);
            if ($title === '' || !Str::startsWith($url, ['http://', 'https://'])) {
                continue;
            }

            try {
                $published = Carbon::parse((string) $entry->pubDate)->timezone('Asia/Kolkata');
            } catch (Throwable $e) {
                $published = now('Asia/Kolkata');
            }

            $description = (string) $entry->description;

            $items[] = [
                'title' => $title,
                'url' => $url,
                'image' => $this->image($entry, $description),
                'excerpt' => \App\Support\Html::plain($description, 180),
                'published_at' => $published->toIso8601String(),
                'source' => $source,
            ];
        }

        return $items;
    }

    private function image(\SimpleXMLElement $entry, string $description): ?string
    {
        $candidates = [];
        if (isset($entry->enclosure)) {
            $candidates[] = (string) $entry->enclosure['url'];
        }
        $media = $entry->children('http://search.yahoo.com/mrss/');
        if (isset($media->content)) {
            $candidates[] = (string) $media->content->attributes()->url;
        }
        if (isset($media->thumbnail)) {
            $candidates[] = (string) $media->thumbnail->attributes()->url;
        }
        if (preg_match('/<img[^>]+src=["\']([^"\']+)/i', $description, $m)) {
            $candidates[] = $m[1];
        }

        foreach ($candidates as $url) {
            if (Str::startsWith($url, ['http://', 'https://']) && !Str::contains($url, config('news.ignore_images', []))) {
                return $url;
            }
        }

        return null;
    }
}
