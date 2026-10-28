<?php

namespace App\Console\Commands;

use App\Services\BloggerPublisher;
use Illuminate\Console\Command;

/** Post new E-Paper editions and Media Articles uploaded in admin to their Blogger blogs (see App\Services\BloggerPublisher). */
class PublishToBlogger extends Command
{
    protected $signature = 'blogger:publish
        {--fix-dates : Move the posts already made from here to their edition dates (newest first on the blog)}';

    protected $description = 'Post new E-Paper editions and Media Articles to their Blogger blogs';

    public function handle(BloggerPublisher $blogger): int
    {
        if ($this->option('fix-dates')) {
            $r = $blogger->fixDates();
            $this->info("Moved {$r['moved']} post(s) to their edition date" . ($r['failed'] ? ", {$r['failed']} failed (see the log)" : '') . '.');

            return self::SUCCESS;
        }

        $r = $blogger->publishPending();
        $this->info("Posted {$r['posted']} to Blogger" . ($r['failed'] ? ", {$r['failed']} failed (see Admin)" : '') . '.');
        if ($r['message']) {
            $this->warn($r['message']);
        }

        return self::SUCCESS;
    }
}
