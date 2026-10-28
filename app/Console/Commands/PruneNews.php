<?php

namespace App\Console\Commands;

use App\Models\LatestNews;
use App\Models\NewsInboxItem;
use App\Models\PopularNews;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Keeps only current news in the database: Business News and Most Popular older than
 * config news.keep_days are deleted with their pictures (the newest ones shown on the
 * homepage are always kept), old News Inbox headlines are removed, and AI pictures that
 * no article uses any more are deleted from disk. Runs daily.
 */
class PruneNews extends Command
{
    protected $signature = 'news:prune {--dry-run : Only show what would be deleted}';

    protected $description = 'Delete old Business News, Most Popular and News Inbox items';

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');
        $cutoff = now()->subDays((int) config('news.keep_days', 7));
        $keepNewest = (int) config('news.limit', 10);

        foreach ([LatestNews::class => 'Business News', PopularNews::class => 'Most Popular'] as $model => $label) {
            $keep = $model::active()->ordered()->take($keepNewest)->pluck('id');
            $old = $model::whereNotIn('id', $keep)
                ->where(fn ($q) => $q->where('published_at', '<', $cutoff)
                    ->orWhere(fn ($q) => $q->whereNull('published_at')->where('created_at', '<', $cutoff)))
                ->get();
            if (!$dry) {
                $old->each->delete(); // one by one, so each article's picture is deleted too
            }
            $this->line(($dry ? 'Would delete ' : 'Deleted ') . $old->count() . " {$label} article(s) older than {$cutoff->toDateString()}.");
        }

        $inbox = NewsInboxItem::where('created_at', '<', now()->subDays((int) config('news.inbox_keep_days', 3)));
        $count = $dry ? $inbox->count() : $inbox->delete();
        $this->line(($dry ? 'Would delete ' : 'Deleted ') . "{$count} old News Inbox headline(s).");

        // AI pictures on disk that no article points to any more (e.g. replaced ones). Files under an
        // hour old are left alone: auto-publish saves a picture a moment before creating its article.
        $used = LatestNews::pluck('image')->merge(PopularNews::pluck('image'))->filter()->flip();
        $disk = Storage::disk('public');
        $orphans = collect($disk->files('headline-news'))
            ->reject(fn ($f) => isset($used[$f]) || $disk->lastModified($f) > now()->subHour()->getTimestamp())
            ->values();
        if (!$dry) {
            Storage::disk('public')->delete($orphans->all());
        }
        $this->line(($dry ? 'Would delete ' : 'Deleted ') . $orphans->count() . ' unused news picture(s).');

        return self::SUCCESS;
    }
}
