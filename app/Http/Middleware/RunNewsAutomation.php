<?php

namespace App\Http\Middleware;

use App\Services\NewsAutoPublisher;
use App\Services\NewsCollector;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Backup for the cron job: if the scheduler hasn't run the news automation in the last 45 minutes,
 * a page visit starts it (collect stories + auto-publish), at most once every 30 minutes.
 * It runs after the page has been sent to the visitor, so nobody waits for it.
 */
class RunNewsAutomation
{
    public const CRON_KEY = 'news-auto.cron-ran-at';

    public function handle(Request $request, Closure $next)
    {
        return $next($request);
    }

    public function terminate(Request $request, $response): void
    {
        if (!config('news.auto.enabled') || !$request->isMethod('GET') || $request->is(config('admin.path') . '*')) {
            return;
        }
        // Only where the page is already delivered before this runs (PHP-FPM / LiteSpeed hosting).
        // On `php artisan serve` the visitor would wait - use `php artisan schedule:work` there instead.
        if (!function_exists('fastcgi_finish_request') && !function_exists('litespeed_finish_request')) {
            return;
        }
        $cronAt = Cache::get(self::CRON_KEY);
        // abs(): newer Carbon returns signed differences
        if ($cronAt && abs(now()->diffInMinutes($cronAt)) < 45) {
            return; // the cron job is doing it
        }
        if (!Cache::add('news-auto.web-run', true, now()->addMinutes(30))) {
            return; // ran in the last 30 minutes
        }

        @set_time_limit(600);
        ignore_user_abort(true);
        try {
            app(NewsCollector::class)->collect();
            app(NewsAutoPublisher::class)->run();
        } catch (Throwable $e) {
            Log::warning('News automation (page-visit backup) failed: ' . $e->getMessage());
        }
    }
}
