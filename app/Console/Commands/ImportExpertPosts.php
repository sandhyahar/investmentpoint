<?php

namespace App\Console\Commands;

use App\Models\ExpertPost;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * Copies client / guest expert articles from expertview-ipoint.blogspot.com into expert_posts.
 * Already-imported posts are skipped (admin edits are kept), duplicates removed.
 *
 *   php artisan experts:import          new posts only
 *   php artisan experts:import --full   check every post on the blog
 */
class ImportExpertPosts extends Command
{
    protected $signature = 'experts:import {--full : Scan the whole blog instead of stopping at already-imported posts}';

    protected $description = "Import Expert's View articles from expertview-ipoint.blogspot.com";

    private const FEED = 'https://expertview-ipoint.blogspot.com/feeds/posts/default';
    private const PER_REQUEST = 50; // posts are long HTML, keep responses small

    public function handle(): int
    {
        $start = 1;
        $added = 0;
        $total = null;

        do {
            $feed = Http::timeout(60)->retry(3, 2000)
                ->get(self::FEED, ['alt' => 'json', 'max-results' => self::PER_REQUEST, 'start-index' => $start])
                ->throw()->json('feed');

            $total ??= (int) ($feed['openSearch$totalResults']['$t'] ?? 0);
            $entries = $feed['entry'] ?? [];

            $new = 0;
            foreach ($entries as $entry) {
                $id = preg_replace('/^.*post-/', '', $entry['id']['$t'] ?? '');
                if ($id === '' || ExpertPost::where('blogger_id', $id)->exists()) {
                    continue;
                }
                $post = ExpertPost::fromBlogPost($entry['title']['$t'] ?? '', $entry['content']['$t'] ?? '', $entry['published']['$t'] ?? now()->toIso8601String());
                if (trim(strip_tags($post['content'])) === '' && !$post['image']) {
                    continue; // empty post
                }
                ExpertPost::create($post + ['blogger_id' => $id, 'status' => 'active']);
                $new++;
            }
            $added += $new;

            $this->line(sprintf('  %d / %d checked, %d new', min($start + count($entries) - 1, $total), $total, $added));
            // Blogger trims big responses (long HTML posts), so move on by what actually came back
            $start += count($entries);

            if (!$this->option('full') && $entries && $new === 0) {
                break;
            }
        } while ($entries && $start <= $total);

        // Same headline posted twice on the same day = duplicate
        $removed = 0;
        $dupes = ExpertPost::selectRaw('title, published_on, MIN(id) keep_id, COUNT(*) c')->groupBy('title', 'published_on')->having('c', '>', 1)->get();
        foreach ($dupes as $d) {
            ExpertPost::where('title', $d->title)->where('published_on', $d->getRawOriginal('published_on'))->where('id', '!=', $d->keep_id)->get()->each(function ($p) use (&$removed) {
                $p->delete();
                $removed++;
            });
        }

        $this->info("Done. {$added} new article(s) imported, {$removed} duplicate(s) removed.");

        return self::SUCCESS;
    }
}
