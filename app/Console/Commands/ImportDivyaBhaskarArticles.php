<?php

namespace App\Console\Commands;

use App\Models\DivyaBhaskarArticle;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * Copies Nikhil Bhatt's Divya Bhaskar columns from db-ipoint.blogspot.com into the
 * divya_bhaskar_articles table. Already-imported posts are skipped, duplicates removed.
 *
 *   php artisan divya:import          new posts only
 *   php artisan divya:import --full   check every post on the blog
 */
class ImportDivyaBhaskarArticles extends Command
{
    protected $signature = 'divya:import {--full : Scan the whole blog instead of stopping at already-imported posts}';

    protected $description = 'Import Divya Bhaskar columns from db-ipoint.blogspot.com';

    private const FEED = 'https://db-ipoint.blogspot.com/feeds/posts/default';
    private const PER_REQUEST = 150;

    public function handle(): int
    {
        $start = 1;
        $added = $skipped = 0;
        $total = null;

        do {
            $feed = Http::timeout(30)->retry(3, 2000)
                ->get(self::FEED, ['alt' => 'json', 'max-results' => self::PER_REQUEST, 'start-index' => $start])
                ->throw()->json('feed');

            $total ??= (int) ($feed['openSearch$totalResults']['$t'] ?? 0);
            $entries = $feed['entry'] ?? [];

            $rows = [];
            foreach ($entries as $entry) {
                $post = DivyaBhaskarArticle::parsePost($entry['title']['$t'] ?? '', $entry['content']['$t'] ?? '', $entry['published']['$t'] ?? null);
                if (!$post || !$post['published_on']) {
                    $skipped++;
                    continue;
                }
                $rows[] = $post + [
                    'blogger_id' => preg_replace('/^.*post-/', '', $entry['id']['$t'] ?? ''),
                    'status' => 'active',
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
            $new = DivyaBhaskarArticle::insertOrIgnore($rows);
            $added += $new;

            $this->line(sprintf('  %d / %d checked, %d new', min($start + count($entries) - 1, $total), $total, $added));
            $start += count($entries); // Blogger may return fewer than asked; continue from what came back

            if (!$this->option('full') && $entries && $new === 0) {
                break;
            }
        } while ($entries && $start <= $total);

        $removed = DivyaBhaskarArticle::removeDuplicates();
        $this->info("Done. {$added} new column(s) imported, {$removed} duplicate(s) removed, {$skipped} post(s) without a Divya Bhaskar link skipped.");

        return self::SUCCESS;
    }
}
