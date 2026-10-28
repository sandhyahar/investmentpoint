<?php

namespace App\Console\Commands;

use App\Models\MediaArticle;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * Copies posts from the Media Article blogspot into the media_articles table.
 * Already-imported posts are skipped (and admin edits to them are kept).
 *
 *   php artisan media:import          new posts only (stops at the first page with nothing new)
 *   php artisan media:import --full   check every post on the blog
 */
class ImportMediaArticles extends Command
{
    protected $signature = 'media:import {--full : Scan the whole blog instead of stopping at already-imported posts}';

    protected $description = 'Import newspaper clippings from mediaarticle-ipoint.blogspot.com';

    private const FEED = 'https://mediaarticle-ipoint.blogspot.com/feeds/posts/default';
    private const PER_REQUEST = 150;

    public function handle(): int
    {
        $start = 1;
        $added = 0;
        $total = null;

        do {
            $feed = Http::timeout(30)->retry(3, 2000)
                ->get(self::FEED, ['alt' => 'json', 'max-results' => self::PER_REQUEST, 'start-index' => $start])
                ->throw()->json('feed');

            $total ??= (int) ($feed['openSearch$totalResults']['$t'] ?? 0);
            $entries = $feed['entry'] ?? [];

            $rows = array_filter(array_map(fn ($entry) => $this->row($entry), $entries));
            $new = MediaArticle::insertOrIgnore($rows); // unique blogger_id -> existing posts are skipped
            $added += $new;

            $this->line(sprintf('  %d / %d checked, %d new', min($start + count($entries) - 1, $total), $total, $added));
            $start += count($entries); // Blogger may return fewer than asked; continue from what came back

            if (!$this->option('full') && $entries && $new === 0) {
                break; // newest first: nothing new on this page means we're caught up
            }
        } while ($entries && $start <= $total);

        $removed = MediaArticle::removeDuplicates(); // the blogspot has some clippings posted more than once
        $this->info("Done. {$added} new media article(s) imported, {$removed} duplicate(s) removed.");

        return self::SUCCESS;
    }

    private function row(array $entry): ?array
    {
        $id = preg_replace('/^.*post-/', '', $entry['id']['$t'] ?? '');
        $html = $entry['content']['$t'] ?? '';
        preg_match('/<img[^>]+src="(https:\/\/[^"]+)"/i', $html, $img);
        if ($id === '' || empty($img[1])) {
            return null;
        }

        $title = trim(preg_replace('/\s+/u', ' ', $entry['title']['$t'] ?? '')) ?: 'Media Article';
        $parts = MediaArticle::parseTitle($title);
        $text = trim(html_entity_decode(strip_tags(preg_replace('/<br\s*\/?>/i', "\n", $html)), ENT_QUOTES | ENT_HTML5, 'UTF-8'), " \n\t\u{00A0}");
        $now = now();

        return [
            'blogger_id' => $id,
            'title' => mb_substr($title, 0, 255),
            'column_name' => $parts['column_name'] ? mb_substr($parts['column_name'], 0, 120) : null,
            'publication' => $parts['publication'] ? mb_substr($parts['publication'], 0, 120) : null,
            'published_on' => $parts['published_on'] ?? (isset($entry['published']['$t']) ? Carbon::parse($entry['published']['$t'])->toDateString() : null),
            'image' => $img[1],
            'description' => mb_strlen($text) > 10 ? $text : null,
            'status' => 'active',
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }
}
