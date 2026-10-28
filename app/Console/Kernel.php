<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        // Pull new posts from the Media Article blogspot into Admin -> Media Articles
        $schedule->command('media:import')->hourly()->withoutOverlapping();
        // ...and new columns from the Divya Bhaskar blogspot into Admin -> Divya Bhaskar
        $schedule->command('divya:import')->hourly()->withoutOverlapping();
        // ...and new client / guest expert articles into Admin -> Expert's View
        $schedule->command('experts:import')->hourly()->withoutOverlapping();
        // ...and new e-paper editions (weekly + daily Gujarati / Hindi / English) into Admin -> E-Paper
        // (the daily papers may also be emailed to Blogger by hand, so they are checked every 5 minutes)
        $schedule->command('newspaper:import', ['--edition' => 'weekly'])->hourly()->withoutOverlapping();
        // (in the background, like market:refresh: they take up to a minute and would hold up the e-paper email queue below)
        foreach (['gujarati', 'hindi', 'english'] as $paper) {
            $schedule->command('newspaper:import', ['--edition' => $paper])->everyFiveMinutes()->withoutOverlapping(10)->runInBackground();
        }
        // The other way round: E-Paper editions and Media Articles uploaded in admin go to their Blogger blogs
        // (right after saving; this posts any that were missed, failed once or were scheduled for later)
        $schedule->command('blogger:publish')->everyFiveMinutes()->withoutOverlapping(10)->runInBackground();
        // Daily E-Papers go to their blog by email, one at a time: send the next, and once Blogger has made the post,
        // give it the edition date and layout and list it on the website
        // (every 20 seconds, so an edition shows soon after Blogger posts it and the next email is not kept waiting)
        $schedule->call(fn () => app(\App\Services\EpaperByEmail::class)->run())
            ->everyTwentySeconds()->name('epaper-email-finish')->withoutOverlapping(10);
        // Business headlines from publishers' RSS feeds into Admin -> News Inbox
        $schedule->command('news:collect')->everyFifteenMinutes()->withoutOverlapping();
        // ...and publish the best of them as original AI-written articles (limits: config news.auto)
        $schedule->command('news:auto-publish')->everyThirtyMinutes()->withoutOverlapping();
        // The Nifty Derivative Market Trend Analysis (investmentpoint.in/nifty), rewritten from the market figures
        // at the opening (9:30 AM), the close (3:30 PM) and in the evening (5 PM)
        foreach (config('news.nifty_daily.times') as $session => $time) {
            $schedule->command('nifty:daily-update', ['--session' => $session])->weekdays()->dailyAt($time)->timezone('Asia/Kolkata')->withoutOverlapping(30);
        }
        // A scheduled E-Paper edition or Media Article just went live: open pages refresh to show it, and it goes to
        // Blogger at once (blogger:publish would also post it, within 5 minutes)
        $schedule->call(function () {
            $justLive = fn (string $model) => $model::where('status', 'active')->whereBetween('publish_at', [now()->subMinutes(2), now()])->exists();
            $media = $justLive(\App\Models\MediaArticle::class);
            if ($media || $justLive(\App\Models\DigitalNewspaper::class)) {
                if ($media) {
                    \App\Models\MediaArticle::forgetPublicationCaches();
                }
                \App\Support\ContentVersion::bump();
                app(\App\Services\BloggerPublisher::class)->publishPending();
            }
        })->everyMinute()->name('newspaper-scheduled-publish')->withoutOverlapping();
        // Keep only current news: delete old Business News / Most Popular / inbox headlines (config news.keep_days)
        $schedule->command('news:prune')->dailyAt('03:30')->timezone('Asia/Kolkata')->withoutOverlapping();
        // Live market quotes, fetched in the background so visitors never wait for Yahoo / Google
        // (NSE/BSE hours, Mon-Fri; outside them the last session's values are kept)
        // How often: config market.cache_seconds (the same pace the pages poll at)
        $market = $schedule->command('market:refresh');
        match (true) {
            config('market.cache_seconds') <= 5 => $market->everyFiveSeconds(),
            config('market.cache_seconds') <= 10 => $market->everyTenSeconds(),
            config('market.cache_seconds') <= 15 => $market->everyFifteenSeconds(),
            config('market.cache_seconds') <= 30 => $market->everyThirtySeconds(),
            default => $market->everyMinute(),
        };
        $market->weekdays()->timezone('Asia/Kolkata')->between('9:00', '15:45')->withoutOverlapping(1);
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
