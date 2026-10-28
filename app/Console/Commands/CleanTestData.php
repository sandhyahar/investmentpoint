<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * One-time clean-up before the site goes live: removes what was added while building and testing.
 *   php artisan site:clean-test-data            (only lists what would be deleted)
 *   php artisan site:clean-test-data --force    (deletes it)
 * Deleted: demo News + Technical Analysis + Member Picks, every subscription request and member login,
 * page views / likes / view counts, press contacts and their send records, reader blogs.
 * Kept: the admin login, settings, e-papers, media articles, Divya Bhaskar columns, Expert's View,
 * Editor's View, YouTube videos, Latest / Popular News and the News Inbox.
 * Refuses to run once a real payment exists, so it can never wipe real subscribers.
 */
class CleanTestData extends Command
{
    protected $signature = 'site:clean-test-data {--force : Really delete (without it, only the counts are shown)}';

    protected $description = 'Before launch: delete demo content, test subscriptions, page views and other test data';

    // Emptied completely
    private const TABLES = ['news', 'technical_analyses', 'member_picks', 'subscription_requests', 'page_views', 'likes',
        'news_clicks', 'press_deliveries', 'press_contacts', 'user_blogs'];

    // Uploaded pictures of the deleted rows: table => column
    private const PICTURES = ['news' => 'image', 'technical_analyses' => 'image', 'user_blogs' => 'author_photo'];

    public function handle(): int
    {
        $real = DB::table('subscription_requests')->where('payment_status', 'paid')->whereIn('payment_mode', ['live', 'manual'])->count();
        if ($real) {
            $this->error("Stopped: {$real} real paid subscription(s) exist. This clean-up is only for before the launch.");

            return self::FAILURE;
        }

        $force = $this->option('force');
        $files = [];
        foreach (self::PICTURES as $table => $column) {
            foreach (DB::table($table)->pluck($column)->filter() as $file) {
                if (!Str::startsWith($file, ['http://', 'https://'])) {
                    $files[] = $file;
                }
            }
        }

        $rows = [];
        DB::transaction(function () use ($force, &$rows) {
            foreach (self::TABLES as $table) {
                $rows[] = [$table, $force ? DB::table($table)->delete() : DB::table($table)->count()];
            }
            $members = DB::table('users')->whereNotIn('role', ['admin', 'staff']);
            $rows[] = ['users (members, not the admin-panel logins)', $force ? $members->delete() : $members->count()];
            foreach (['latest_news', 'popular_news'] as $table) {
                $viewed = DB::table($table)->where('views', '>', 0);
                $rows[] = ["{$table}: view counts set to 0", $force ? $viewed->update(['views' => 0]) : $viewed->count()];
            }
        });
        $rows[] = ['uploaded pictures of the deleted rows', count($files)];

        $this->table(['What', $force ? 'Deleted' : 'Would be deleted'], $rows);

        if (!$force) {
            $this->warn('Nothing was deleted. Run again with --force to delete.');

            return self::SUCCESS;
        }

        Storage::disk('public')->delete($files);
        Cache::flush();
        $this->info('Test data deleted. Admin logins kept: ' . DB::table('users')->where('role', 'admin')->count());

        return self::SUCCESS;
    }
}
