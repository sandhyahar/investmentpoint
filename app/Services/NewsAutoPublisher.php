<?php

namespace App\Services;

use App\Models\LatestNews;
use App\Models\NewsInboxItem;
use App\Models\PopularNews;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Auto-publish: takes the best stories from the News Inbox, has the free AI write an original
 * short article with an AI image, and publishes it to Business News / Most Popular with a
 * "Source" credit. Limits and rules: config news.auto.
 */
class NewsAutoPublisher
{
    public function __construct(private NewsWriter $writer, private NewsCollector $collector, private StockPhotos $photos)
    {
    }

    /**
     * Picture for an article: a real Pexels photo of the topic first (free, looks real), else a
     * realistic AI photo (free: Cloudflare / Pollinations with a key / Hugging Face). Returns the article columns to save.
     */
    private function picture(string $headline): array
    {
        if ($photo = $this->photos->forHeadline($headline)) {
            return ['image' => $photo['path'], 'image_credit' => $photo['credit'], 'image_credit_url' => $photo['credit_url']];
        }
        $ai = $this->writer->image($headline);

        return ['image' => $ai, 'image_credit' => null, 'image_credit_url' => null];
    }

    /** @return array{business: int, popular: int, held: int, message: ?string} */
    public function run(): array
    {
        $result = ['business' => 0, 'popular' => 0, 'held' => 0, 'message' => null];
        $cfg = config('news.auto');

        if (!$cfg['enabled']) {
            return ['message' => 'Auto-publish is switched off (NEWS_AUTO_PUBLISH=false).'] + $result;
        }
        if (!$this->writer->isConfigured()) {
            return ['message' => 'AI writing is not set up (no Gemini or Groq key in Admin -> Site Settings).'] + $result;
        }

        // Articles published before the fact-check existed: check a few per run, hide any that fail
        $this->recheckOlder($result);
        // ...and swap their old cartoon-style pictures for realistic photos (3 per run)
        $this->replaceCartoonImages();
        // ...and give a picture to those published while the picture services were used up (3 per run)
        $this->fillMissingImages();
        // ...and give the published ones SEBI wording (support / resistance level, no buy / sell / hold call)
        $this->sebiOlder($result);

        // Most Popular: stories several publishers are covering right now
        $popularToday = PopularNews::whereNotNull('source_url')->where('created_at', '>=', today())->count();
        $popularWanted = min($cfg['popular_per_run'], max(0, $cfg['popular_per_day'] - $popularToday));
        $popular = $this->candidates()->where('coverage', '>', 1)->where('published_at', '>=', now()->subDay())
            ->orderByDesc('coverage')->orderByDesc('score')->orderByDesc('published_at')->take(10)->get();
        $this->publish($popular, $popularWanted, PopularNews::class, 'popular', $result);

        // Business News: the freshest strong business stories
        $businessToday = LatestNews::whereNotNull('source_url')->where('created_at', '>=', today())->count();
        $businessWanted = min($cfg['business_per_run'], max(0, $cfg['business_per_day'] - $businessToday));
        $business = $this->candidates()->where('score', '>=', $cfg['min_score'])
            ->where('published_at', '>=', now()->subHours($cfg['max_age_hours']))
            ->orderByDesc('published_at')->orderByDesc('score')->take(10)->get();
        $this->publish($business, $businessWanted, LatestNews::class, 'business', $result);

        return $result;
    }

    /** Fact-check up to 5 AI-written articles that were never checked; hide (inactive) those with unsupported claims. */
    private function recheckOlder(array &$result): void
    {
        $result['hidden'] = 0;
        foreach ([LatestNews::class, PopularNews::class] as $model) {
            foreach ($model::active()->whereNotNull('source_url')->whereNull('fact_checked_at')->take(5)->get() as $article) {
                $story = NewsInboxItem::where('url_hash', sha1($article->source_url))->first();
                if (!$story) {
                    continue; // source no longer known: leave it for the admin
                }
                $problems = $this->writer->factCheck(
                    ['title' => $article->title, 'description' => (string) $article->description, 'content' => (string) $article->content],
                    $story->title, $story->summary
                );
                if ($problems && str_starts_with($problems[0], 'fact-check could not run')) {
                    return; // AI unavailable (e.g. daily allowance used up): try again next run
                }
                if ($problems) {
                    $article->update(['status' => 'inactive']);
                    Log::info('Fact-check: hid ' . class_basename($model) . ' #' . $article->id . ' (' . \Illuminate\Support\Str::limit(implode('; ', $problems), 300) . ')');
                    $result['hidden']++;
                } else {
                    $article->update(['fact_checked_at' => now()]);
                }
            }
        }
    }

    /**
     * Published AI articles (Business News / Most Popular): "stop loss" -> "support", target -> "resistance
     * level"; one that still gives a buy / sell / hold call is rewritten by the AI (3 per run), or hidden
     * when the rewrite keeps the call. Articles done are remembered by id, so each is looked at once.
     */
    private function sebiOlder(array &$result): void
    {
        $result['rewritten'] = 0;
        $rewrites = 0;
        foreach ([LatestNews::class, PopularNews::class] as $model) {
            $key = 'news-auto.sebi-checked.' . class_basename($model);
            foreach ($model::active()->whereNotNull('source_url')->where('id', '>', (int) Cache::get($key, 0))->lazyById(50) as $article) {
                $columns = ['title' => $article->title, 'description' => (string) $article->description,
                    'keywords' => (string) $article->keywords, 'content' => (string) $article->content];
                $fixed = NewsWriter::sebiDraft($columns);

                if (NewsWriter::recommendationWords(NewsWriter::draftText($fixed))) {
                    if ($rewrites >= 3) {
                        return; // the AI allowance: the rest next run
                    }
                    $rewrites++;
                    try {
                        $rewrite = $this->writer->withoutRecommendations($fixed);
                    } catch (Throwable $e) {
                        return; // AI unavailable: next run
                    }
                    if (!$rewrite) {
                        $article->update(['status' => 'inactive']);
                        Log::info('SEBI wording: hid ' . class_basename($model) . ' #' . $article->id . ' (buy / sell / hold call)');
                        $result['hidden'] = ($result['hidden'] ?? 0) + 1;
                    } else {
                        $fixed = array_intersect_key($rewrite, $columns);
                        $result['rewritten']++;
                    }
                }
                if ($fixed !== $columns && $article->status === 'active') {
                    $article->update(['keywords' => $fixed['keywords'] ?: null] + $fixed);
                }
                Cache::forever($key, $article->id);
            }
        }
    }

    /** Old AI images ("headline-news/ai-…") were cartoon-style: replace up to 3 per run with realistic photos. */
    private function replaceCartoonImages(): void
    {
        if (!config('news.auto.images')) {
            return;
        }
        $done = 0;
        foreach ([LatestNews::class, PopularNews::class] as $model) {
            foreach ($model::active()->where('image', 'like', 'headline-news/ai-%')->take(3)->get() as $article) {
                if ($done >= 3) {
                    return;
                }
                $picture = $this->picture($article->title);
                if (!$picture['image']) {
                    return; // no photo / image AI unavailable right now: next run
                }
                \Illuminate\Support\Facades\Storage::disk('public')->delete($article->image);
                $article->update($picture);
                $done++;
            }
        }
    }

    /**
     * Articles published while no picture could be made (Cloudflare's free pictures used up for the day, the other
     * services busy) all showed the same stand-in picture (owner, 27 Oct 2026). Up to 3 per run, newest first, from the
     * last 3 days, get their own picture as soon as a service works again.
     */
    private function fillMissingImages(): void
    {
        if (!config('news.auto.images')) {
            return;
        }
        $done = 0;
        foreach ([LatestNews::class, PopularNews::class] as $model) {
            $waiting = $model::active()->whereNotNull('source_url')->where(fn ($q) => $q->whereNull('image')->orWhere('image', ''))
                ->where('created_at', '>=', now()->subDays(3))->latest('id')->take(3)->get();
            foreach ($waiting as $article) {
                if ($done >= 3) {
                    return;
                }
                $picture = $this->picture($article->title);
                if (!$picture['image']) {
                    return; // still no photo / image AI: next run
                }
                $article->update($picture);
                Log::info('Auto news: picture added to ' . class_basename($model) . ' #' . $article->id);
                $done++;
            }
        }
    }

    /** Open inbox stories not tried in the last day. */
    private function candidates()
    {
        return NewsInboxItem::open()->whereNotIn('id', Cache::get('news-auto.tried', []));
    }

    private function publish($stories, int $wanted, string $model, string $key, array &$result): void
    {
        foreach ($stories as $story) {
            if ($result[$key] >= $wanted) {
                return;
            }
            if ($story->fresh()->status !== 'new') {
                continue; // used as the other section's article a moment ago
            }
            // Indian business market only: a foreign-market story (Wall Street, Dow, US Fed...) is skipped
            if (!self::isIndianMarketStory($story->title . ' ' . $story->summary)) {
                $story->update(['status' => 'dismissed']);
                continue;
            }
            // A follow-up on news already published (e.g. another publisher's later report): don't repeat it
            if ($this->collector->sameStory($story, 'used')->isNotEmpty()) {
                $story->update(['status' => 'used']);
                continue;
            }
            // Stay inside the free AI allowance: a fixed number of attempts per day
            $attemptsKey = 'news-auto.attempts.' . now()->format('Y-m-d');
            if ((int) Cache::get($attemptsKey, 0) >= (int) config('news.auto.attempts_per_day', 20)) {
                $result['message'] = "Today's auto-publish limit is reached; it continues tomorrow.";

                return;
            }
            Cache::put($attemptsKey, (int) Cache::get($attemptsKey, 0) + 1, now()->endOfDay());
            $this->remember($story->id);

            try {
                $draft = $this->writer->draft($story->title, $story->summary, $story->source);
            } catch (Throwable $e) {
                Log::warning('Auto-publish: AI draft failed for inbox #' . $story->id . ': ' . $e->getMessage());
                $result['message'] = $e->getMessage();

                return; // AI unavailable / limit reached: stop this run
            }

            // Safety check: never auto-publish a figure that is not in the original story
            if ($invented = $this->writer->newNumbers($draft, $story->title, $story->summary)) {
                Log::info('Auto-publish: held inbox #' . $story->id . ' (numbers not in source: ' . implode(', ', $invented) . ')');
                $result['held']++;
                continue;
            }
            // ...nor a buy / sell / hold call (SEBI guidelines)
            if ($calls = NewsWriter::recommendationWords(NewsWriter::draftText($draft))) {
                Log::info('Auto-publish: held inbox #' . $story->id . ' (recommendation: ' . implode(', ', $calls) . ')');
                $result['held']++;
                continue;
            }
            // ...nor an article that names a newspaper / news channel
            if ($this->writer->mentionsPublisher($draft)) {
                Log::info('Auto-publish: held inbox #' . $story->id . ' (names a news outlet)');
                $result['held']++;
                continue;
            }
            // ...and only when an independent fact-check finds nothing that isn't in the source
            if ($problems = $this->writer->factCheck($draft, $story->title, $story->summary)) {
                Log::info('Auto-publish: held inbox #' . $story->id . ' (fact-check: ' . \Illuminate\Support\Str::limit(implode('; ', $problems), 300) . ')');
                $result['held']++;
                continue;
            }

            $model::create([
                'title' => $draft['title'] ?: $story->title,
                'description' => $draft['description'] ?: \Illuminate\Support\Str::limit(strip_tags($draft['content']), 200),
                'keywords' => $draft['keywords'] ?: null,
                'content' => $draft['content'],
            ] + (config('news.auto.images') ? $this->picture($draft['title'] ?: $story->title) : ['image' => null]) + [
                'source' => $story->source,
                'source_url' => $story->url,
                'fact_checked_at' => now(),
                'published_at' => now(),
                'sort_order' => 0,
                'status' => 'active',
            ]);

            // This story, and the same news from other publishers, leave the inbox
            $story->update(['status' => 'used']);
            NewsInboxItem::whereKey($this->collector->sameStory($story)->pluck('id'))->update(['status' => 'used']);
            $result[$key]++;
        }
    }

    /**
     * False for stories about foreign markets only. Indian company news rarely names India, so a
     * story counts as Indian unless it is about foreign markets AND never mentions India's market.
     */
    public static function isIndianMarketStory(string $text): bool
    {
        $foreign = '/\b(dow( jones)?|nasdaq|s&p ?500|wall street|us stocks?|us markets?|us fed|federal reserve|fomc|treasury yields?|us jobless|nikkei|hang seng|ftse|dax|european stocks?|asian markets?|trump|white house)\b/i';
        $indian = '/\b(india|indian|sensex|nifty|nse|bse|sebi|rbi|rupee|dalal street|d-street|gift nifty|crore|lakh|gst)\b|₹|\brs\.? ?\d/i';

        return !preg_match($foreign, $text) || (bool) preg_match($indian, $text);
    }

    private function remember(int $id): void
    {
        $tried = Cache::get('news-auto.tried', []);
        $tried[] = $id;
        Cache::put('news-auto.tried', array_slice(array_unique($tried), -500), now()->addDay());
    }
}
