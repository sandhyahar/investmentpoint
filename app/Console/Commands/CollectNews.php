<?php

namespace App\Console\Commands;

use App\Services\NewsCollector;
use Illuminate\Console\Command;

/** Collect business headlines from the publishers' RSS feeds into Admin -> News Inbox. */
class CollectNews extends Command
{
    protected $signature = 'news:collect';

    protected $description = 'Collect business news headlines from RSS feeds into the admin News Inbox';

    public function handle(NewsCollector $collector): int
    {
        $result = $collector->collect();
        $this->info("Done. {$result['found']} headline(s) read, {$result['added']} new business stor" . ($result['added'] === 1 ? 'y' : 'ies') . ' added to the News Inbox.');

        return self::SUCCESS;
    }
}
