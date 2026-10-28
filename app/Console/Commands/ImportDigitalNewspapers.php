<?php

namespace App\Console\Commands;

use App\Models\DigitalNewspaper;
use App\Services\BloggerPublisher;
use Generator;
use Illuminate\Console\Command;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Copies E-Paper editions from their blogspots into the digital_newspapers table: the Weekly News Paper
 * (weeklynewspaper-ipoint.blogspot.com) and the daily Gujarati / Hindi / English e-papers
 * (config('epaper.editions')). A post is skipped when the website already has it: the same Blogger post, or an
 * edition of that paper with the same date and title (the same edition posted twice on the blog).
 *
 *   php artisan newspaper:import                      weekly, new posts only
 *   php artisan newspaper:import --edition=gujarati   one daily e-paper
 *   php artisan newspaper:import --edition=all        all four
 *   php artisan newspaper:import --full               check every post on the blog ("Get all editions from Blogger")
 */
class ImportDigitalNewspapers extends Command
{
    protected $signature = 'newspaper:import
        {--edition=weekly : weekly, gujarati, hindi, english or all}
        {--full : Scan the whole blog instead of stopping at already-imported posts}';

    protected $description = 'Import E-Paper editions (weekly / Gujarati / Hindi / English) from their blogspots';

    private const FEED = '/feeds/posts/default';
    private const PER_REQUEST = 150;
    // A full scan asks for this many feed pages at once (a blog of 3,000 posts in seconds instead of a minute)
    private const AT_ONCE = 6;

    public function handle(): int
    {
        $wanted = $this->option('edition');
        $editions = $wanted === 'all' ? array_keys(DigitalNewspaper::EDITIONS) : [$wanted];
        if (array_diff($editions, array_keys(DigitalNewspaper::EDITIONS))) {
            $this->error('Unknown edition. Use weekly, gujarati, hindi, english or all.');

            return self::INVALID;
        }

        foreach ($editions as $edition) {
            $blog = $edition === 'weekly' ? config('epaper.weekly_blog') : config("epaper.editions.{$edition}.blog");
            if (!$blog) {
                $this->line("{$edition}: no blog set, skipped.");
                continue;
            }
            $this->info(DigitalNewspaper::EDITIONS[$edition] . ' (' . $blog . ')');
            $this->import($edition, rtrim($blog, '/'));
        }

        return self::SUCCESS;
    }

    private function import(string $edition, string $blog): void
    {
        $full = (bool) $this->option('full');
        $added = $copies = $skipped = $checked = 0;
        $startedAt = now()->subSecond();
        if ($full && $removed = $this->removeCopies($edition)) {
            $this->line("  {$removed} copy(ies) of the same edition removed from the website (kept one of each).");
        }

        // Already on the website: Blogger posts (ids are unique over all papers) and this paper's editions (date + title)
        $known = DigitalNewspaper::whereNotNull('blogger_id')->pluck('blogger_id')->flip()->all();
        $have = [];
        foreach (DigitalNewspaper::edition($edition)->get(['title', 'published_on']) as $paper) {
            $have[DigitalNewspaper::sameEditionKey($paper->title, $paper->published_on->toDateString())] = true;
        }

        foreach ($this->feedPages($blog, $full) as [$entries, $total]) {
            $rows = $postedOn = [];
            foreach ($entries as $entry) {
                $id = preg_replace('/^.*post-/', '', $entry['id']['$t'] ?? '');
                if (isset($known[$id])) {
                    continue;
                }
                $post = DigitalNewspaper::parsePost($entry['title']['$t'] ?? '', $entry['content']['$t'] ?? '', $entry['published']['$t'] ?? null, $edition === 'weekly');
                if (!$post) {
                    $skipped++;
                    continue;
                }
                $key = DigitalNewspaper::sameEditionKey($post['title'], $post['published_on']);
                if (isset($have[$key])) {
                    $copies++;
                    continue;
                }
                $known[$id] = $have[$key] = true;
                $rows[] = array_merge($post, [
                    'edition' => $edition,
                    'pages' => json_encode($post['pages'], JSON_UNESCAPED_SLASHES),
                    'blogger_id' => $id,
                    'status' => 'active',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $postedOn[$id] = isset($entry['published']['$t']) ? Carbon::parse($entry['published']['$t'])->timezone('Asia/Kolkata')->toDateString() : null;
            }
            $new = $rows ? DigitalNewspaper::insertOrIgnore($rows) : 0;
            $added += $new;
            // A full scan ("Get all editions from Blogger", often right after posting in Blogger) gives only posts made in
            // the last 3 days their edition date: it never changes the dates of old Blogger posts
            if ($full) {
                $since = now('Asia/Kolkata')->subDays(3)->toDateString();
                $postedOn = array_filter($postedOn, fn (?string $day) => $day !== null && $day >= $since);
            }
            if ($new && $postedOn && $edition !== 'weekly') {
                $this->redate($edition, $postedOn, $startedAt);
            }

            $checked += count($entries);
            $this->line(sprintf('  %d / %d checked, %d new', min($checked, $total), $total, $added));

            if (!$full && $new === 0) {
                break;
            }
        }

        $this->line("  Done. {$added} new edition(s) imported, {$copies} copy(ies) of editions already here skipped, {$skipped} post(s) without page images skipped.");

        // Keep the weekly "Total Pageviews" counter fresh too
        if ($edition === 'weekly' && $stats = app(\App\Services\BloggerStats::class)->refresh($blog)) {
            $this->line('  Total pageviews on the blog: ' . number_format($stats['total']));
        }
    }

    /**
     * Editions imported more than once (the blog has the same edition twice): keep the one with the most pages (else the
     * oldest row) and delete the other copies from the website. Only imported copies go; nothing on Blogger changes, and
     * an edition uploaded here or posted to Blogger from here is never removed.
     */
    private function removeCopies(string $edition): int
    {
        $removed = 0;
        DigitalNewspaper::edition($edition)->orderBy('id')->get()
            ->groupBy(fn (DigitalNewspaper $p) => DigitalNewspaper::sameEditionKey($p->title, $p->published_on->toDateString()))
            ->filter(fn ($group) => $group->count() > 1)
            ->each(function ($group) use (&$removed) {
                $keep = $group->sortByDesc(fn (DigitalNewspaper $p) => count($p->pages ?? []))->first();
                foreach ($group as $paper) {
                    if ($paper->isNot($keep) && !$paper->blogger_url && !$paper->uploadedPages()) {
                        $paper->delete();
                        $removed++;
                    }
                }
            });

        return $removed;
    }

    /**
     * The blog's feed, newest post first, one page (up to PER_REQUEST posts) at a time: [entries, total posts].
     * A full scan fetches AT_ONCE pages together.
     */
    private function feedPages(string $blog, bool $full): Generator
    {
        $first = $this->feed($blog, 1);
        $total = (int) ($first['openSearch$totalResults']['$t'] ?? 0);
        $entries = $first['entry'] ?? [];
        yield [$entries, $total];

        $start = 1 + count($entries);
        while ($entries && $start <= $total) {
            $starts = [];
            for ($s = $start; $s <= $total && count($starts) < ($full ? self::AT_ONCE : 1); $s += self::PER_REQUEST) {
                $starts[] = $s;
            }
            $responses = count($starts) > 1
                ? Http::pool(fn (Pool $pool) => array_map(fn ($s) => $pool->timeout(30)->get($blog . self::FEED, $this->query($s)), $starts))
                : [];
            foreach ($starts as $i => $s) {
                $response = $responses[$i] ?? null;
                // Not fetched together (or that request failed): on its own, with retries
                $feed = $response instanceof Response && $response->successful() ? ($response->json('feed') ?? []) : $this->feed($blog, $s);
                $entries = $feed['entry'] ?? [];
                yield [$entries, $total];
                $start = $s + count($entries); // Blogger may return fewer than asked; continue from what came back
                if (count($entries) < self::PER_REQUEST) {
                    break;
                }
            }
        }
    }

    private function feed(string $blog, int $start): array
    {
        return Http::timeout(30)->retry(3, 2000)->get($blog . self::FEED, $this->query($start))->throw()->json('feed') ?? [];
    }

    private function query(int $start): array
    {
        return ['alt' => 'json', 'max-results' => self::PER_REQUEST, 'start-index' => $start];
    }

    /**
     * Daily papers are added in Blogger, often several at once (e.g. by email), and Blogger dates a post by when it was
     * made. A new post whose Blogger date is not its edition date (from the title) is moved to the edition date, so the
     * blog lists the newest edition first. Needs Blogger connected (Admin -> Site Settings -> Blogger).
     *
     * @param  array<string, ?string>  $postedOn  post id => Blogger date (India time)
     */
    private function redate(string $edition, array $postedOn, Carbon $since): void
    {
        $blogger = app(BloggerPublisher::class);
        $blogId = BloggerPublisher::blogs()[$edition]['id'] ?? null;
        if (!$blogId || !$blogger->isConnected()) {
            return;
        }
        $papers = DigitalNewspaper::whereIn('blogger_id', array_keys($postedOn))->where('created_at', '>=', $since)->get();
        foreach ($papers as $paper) {
            if ($postedOn[$paper->blogger_id] === $paper->published_on->toDateString()) {
                continue; // posted on its own day
            }
            try {
                $blogger->redate($paper, $blogId);
                $this->line('  Blogger date set to ' . $paper->published_on->format('d.m.Y') . ': ' . $paper->title);
            } catch (Throwable $e) {
                $this->warn('  Could not change the Blogger date of "' . $paper->title . '": ' . $e->getMessage());
            }
        }
    }
}
