<?php

namespace App\Console\Commands;

use App\Services\NewsAutoPublisher;
use Illuminate\Console\Command;

/** Write (free AI) and publish the best News Inbox stories to Business News / Most Popular. */
class AutoPublishNews extends Command
{
    protected $signature = 'news:auto-publish';

    protected $description = 'Auto-publish the best News Inbox stories as original AI-written articles';

    public function handle(NewsAutoPublisher $publisher): int
    {
        // Tells the page-visit backup (RunNewsAutomation) that the cron job is working
        \Illuminate\Support\Facades\Cache::put(\App\Http\Middleware\RunNewsAutomation::CRON_KEY, now(), now()->addHours(2));

        $r = $publisher->run();
        $this->info("Published {$r['business']} Business News and {$r['popular']} Most Popular article(s); {$r['held']} held for review"
            . (($r['hidden'] ?? 0) ? "; {$r['hidden']} older article(s) hidden after failing the fact-check or giving a buy / sell / hold call" : '')
            . (($r['rewritten'] ?? 0) ? "; {$r['rewritten']} older article(s) rewritten without a buy / sell / hold call" : '') . '.');
        if ($r['message']) {
            $this->warn($r['message']);
        }

        return self::SUCCESS;
    }
}
