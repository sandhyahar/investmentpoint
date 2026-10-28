<?php

namespace App\Services;

use App\Models\NewsInboxItem;
use Illuminate\Http\Client\Pool;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Fills Admin -> News Inbox from the publishers' RSS feeds (config news.inbox_feeds):
 * business stories only, scored by keywords, and ranked "popular" by how many
 * publishers are covering the same story.
 */
class NewsCollector
{
    private const STOPWORDS = ['the', 'a', 'an', 'and', 'or', 'of', 'to', 'in', 'on', 'for', 'at', 'by', 'with', 'from', 'as', 'is',
        'are', 'was', 'be', 'its', 'it', 'this', 'that', 'after', 'over', 'amid', 'into', 'up', 'down', 'says', 'said', 'how', 'why',
        'what', 'will', 'may', 'can', 'has', 'have', 'new', 'more', 'than', 'your', 'you', 'check', 'details', 'here', 'today',
        // Words in almost every market headline: they say nothing about which story it is
        'share', 'shares', 'price', 'stock', 'stocks', 'market', 'markets', 'live', 'updates', 'crore', 'lakh', 'india', 'indian',
        'company', 'firm', 'gains', 'falls', 'rises', 'jumps', 'surges', 'focus', 'all',
        // Price-movement words: "X falls" and "Y falls" are different stories
        'fall', 'rise', 'gain', 'jump', 'surge', 'slide', 'slides', 'slip', 'slips', 'drop', 'drops', 'decline', 'declines',
        'rally', 'rallies', 'climb', 'climbs', 'plunge', 'plunges', 'soar', 'soars', 'higher', 'lower', 'trade', 'trading',
        'session', 'sector', 'index', 'week', 'day', 'year', 'per', 'cent', 'percent'];

    // Automatic pages (live price tickers, live blogs) are not stories to write about
    private const SKIP = ['/live updates/i', '/\blive blog\b/i', '/stock details$/i'];

    public function __construct(private LiveNewsService $feeds)
    {
    }

    /** @return array{found: int, added: int} */
    public function collect(): array
    {
        $sources = config('news.inbox_feeds', []);
        $responses = Http::pool(fn (Pool $pool) => array_map(
            fn ($feed) => $pool->withHeaders(['User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/126.0 Safari/537.36'])
                ->timeout(10)->get($feed['url']),
            $sources
        ));

        $found = $added = 0;
        $keepFrom = now()->subDays((int) config('news.inbox_keep_days', 7));
        foreach ($responses as $i => $response) {
            if (!$response instanceof \Illuminate\Http\Client\Response || !$response->successful()) {
                continue;
            }
            foreach ($this->feeds->parse($response->body(), $sources[$i]['source']) as $item) {
                $found++;
                if (!$this->feeds->isBusinessNews($item['url'], []) || $this->feeds->hasExcludedWord($item['title'])
                    || collect(self::SKIP)->contains(fn ($p) => preg_match($p, $item['title']))
                    || ($score = $this->score($item['title'], $item['excerpt'])) === 0
                    || \Carbon\Carbon::parse($item['published_at'])->lt($keepFrom)) {
                    continue;
                }
                $row = NewsInboxItem::firstOrCreate(['url_hash' => sha1($item['url'])], [
                    'source' => $item['source'],
                    'url' => mb_substr($item['url'], 0, 1000),
                    'title' => mb_substr($item['title'], 0, 500),
                    'summary' => $item['excerpt'] ?: null,
                    'published_at' => \Carbon\Carbon::parse($item['published_at']),
                    'score' => $score,
                ]);
                $added += (int) $row->wasRecentlyCreated;
            }
        }

        $this->updateCoverage();
        NewsInboxItem::where('status', '!=', 'used')->where('published_at', '<', $keepFrom)->delete();

        return ['found' => $found, 'added' => $added];
    }

    /** Keyword matches in the headline (x2) and summary. */
    public function score(string $title, ?string $summary): int
    {
        $score = 0;
        foreach (config('news.inbox_keywords', []) as $word) {
            $pattern = '/(?<![\p{L}\p{N}])' . preg_quote($word, '/') . '(?![\p{L}\p{N}])/iu';
            $score += preg_match($pattern, $title) ? 2 : (preg_match($pattern, (string) $summary) ? 1 : 0);
        }

        return $score;
    }

    /**
     * "Popular": a story is covered by several publishers when their headlines share most of their
     * important words. coverage = number of different publishers with a matching headline (last 2 days).
     */
    private function updateCoverage(): void
    {
        $recent = NewsInboxItem::where('published_at', '>=', now()->subDays(2))->get(['id', 'source', 'title', 'coverage']);
        $words = $recent->mapWithKeys(fn ($r) => [$r->id => $this->keywords($r->title)]);

        foreach ($recent as $item) {
            $mine = $words[$item->id];
            $sources = [$item->source => true];
            if (count($mine) >= 3) {
                foreach ($recent as $other) {
                    if ($other->source === $item->source || isset($sources[$other->source])) {
                        continue;
                    }
                    $theirs = $words[$other->id];
                    $shared = count(array_intersect($mine, $theirs));
                    if ($shared >= 3 && $shared / max(1, min(count($mine), count($theirs))) >= 0.5) {
                        $sources[$other->source] = true;
                    }
                }
            }
            if (count($sources) !== $item->coverage) {
                NewsInboxItem::whereKey($item->id)->update(['coverage' => count($sources)]);
            }
        }
    }

    /**
     * Other stories about the same news (headlines sharing most important words) from the last 2 days,
     * still open ('new') or already written up ('used').
     */
    public function sameStory(NewsInboxItem $item, string $status = 'new')
    {
        $mine = $this->keywords($item->title);
        if (count($mine) < 3) {
            return collect();
        }

        return NewsInboxItem::where('status', $status)->whereKeyNot($item->id)->where('published_at', '>=', now()->subDays(2))->get()
            ->filter(function ($other) use ($mine) {
                $theirs = $this->keywords($other->title);
                $shared = count(array_intersect($mine, $theirs));

                return $shared >= 3 && $shared / max(1, min(count($mine), count($theirs))) >= 0.5;
            });
    }

    /** Important lower-case words of a headline (no stopwords, 3+ letters). */
    private function keywords(string $title): array
    {
        $words = preg_split('/[^\p{L}\p{N}]+/u', Str::lower($title), -1, PREG_SPLIT_NO_EMPTY);

        return array_values(array_unique(array_filter($words, fn ($w) => mb_strlen($w) >= 3 && !in_array($w, self::STOPWORDS, true))));
    }
}
