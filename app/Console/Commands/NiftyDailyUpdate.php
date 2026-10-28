<?php

namespace App\Console\Commands;

use App\Services\NiftyDailyUpdater;
use Illuminate\Console\Command;

/** The Nifty Derivative Market Trend Analysis rewrites: 11:11 AM, 2:02 PM and 5:00 PM (see App\Services\NiftyDailyUpdater) */
class NiftyDailyUpdate extends Command
{
    protected $signature = 'nifty:daily-update
        {--session=close : open (11:11 AM) or midday (2:02 PM): the session so far; close (5:00 PM): the day\'s close; evening: like close}
        {--force : also run when there was no Nifty session today (uses the last one)}
        {--dry-run : write and check the article and picture, but save nothing}';

    protected $description = 'Rewrite the Nifty Derivative Market Trend Analysis from today\'s market figures, with a new AI picture';

    public function handle(NiftyDailyUpdater $updater): int
    {
        $result = $updater->run((bool) $this->option('force'), (bool) $this->option('dry-run'), (string) $this->option('session'));

        if (!empty($result['draft']) && $this->option('dry-run')) {
            $this->line('<comment>Market data given to the AI:</comment>');
            $this->line($result['draft']['facts']);
            $this->newLine();
            $this->line('<comment>Title:</comment> ' . $result['draft']['title']);
            $this->line('<comment>Written by:</comment> ' . $result['draft']['provider'] . ' · ' . $result['draft']['words'] . ' words');
            $this->line(strip_tags(str_replace('</p>', "</p>\n", $result['draft']['content'])));
        }
        $result['updated'] || !empty($result['draft']) ? $this->info($result['message']) : $this->warn($result['message']);

        return self::SUCCESS;
    }
}
