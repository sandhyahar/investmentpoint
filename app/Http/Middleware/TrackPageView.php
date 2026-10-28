<?php

namespace App\Http\Middleware;

use App\Models\PageView;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Counts page views for the admin dashboard. Only real visitors opening public pages are counted:
 * no admin pages, background refreshes, JSON/polling calls, errors, search-engine bots or scanners,
 * and not the logged-in admin looking at the website. A browser that has been used for the admin panel
 * is remembered for a year (cookie) and never counted, also when logged out or logged in as a member:
 * the owner checking and testing the site is not a reader. An address opening pages faster than a
 * person can read them (a crawler) stops being counted for that minute.
 * The row is written after the response has been sent, so pages don't get slower.
 */
class TrackPageView
{
    private const COOKIE = 'ip_vid';

    /** Set by the admin panel (AdminSecurity) on the owner's browsers: their visits are not counted */
    public const STAFF_COOKIE = 'ip_staff';

    /** Background / helper routes that are not page visits */
    private const SKIP_ROUTES = ['content.version', 'news.latest', 'market.data', 'queries.store', 'blog.store'];

    /** Page views one visitor may add per minute; more than this is a crawler, not a reader */
    private const MAX_PER_MINUTE = 12;

    private ?array $pending = null;

    public function handle(Request $request, Closure $next)
    {
        $this->pending = null;
        $visitorId = $request->cookie(self::COOKIE);
        if (!$visitorId || strlen($visitorId) !== 32) {
            $visitorId = Str::random(32);
        }

        $response = $next($request);

        if ($this->shouldTrack($request, $response)) {
            // Keep the anonymous visitor id for a year so returning visitors count once per day
            Cookie::queue(self::COOKIE, $visitorId, 60 * 24 * 365);
            $route = $request->route();
            $this->pending = [
                'route' => mb_substr($route->getName(), 0, 60),
                'subject_id' => $this->subject($route->parameters()),
                'path' => mb_substr('/' . ltrim($request->path(), '/'), 0, 255),
                'visitor' => substr(hash('sha256', $visitorId . config('app.key')), 0, 16),
                'referrer_host' => $this->referrerHost($request),
                'device' => preg_match('/Mobile|Android|iPhone|iPad/i', (string) $request->userAgent()) ? 'mobile' : 'desktop',
                'created_at' => now(),
            ];
        }

        return $response;
    }

    public function terminate(Request $request, $response): void
    {
        // Per returning visitor (cookie); a visitor without the cookie yet is counted by address
        $key = 'page-views:' . ($request->cookie(self::COOKIE) ?: $request->ip());
        if ($this->pending && !RateLimiter::tooManyAttempts($key, self::MAX_PER_MINUTE)) {
            RateLimiter::hit($key, 60);
            try {
                PageView::insert($this->pending);
            } catch (Throwable $e) {
                report($e); // never break the site because of statistics
            }
        }
    }

    private function shouldTrack(Request $request, $response): bool
    {
        $route = $request->route();

        return config('app.track_visits')
            && $request->isMethod('GET')
            && $route && $route->getName()
            && !Str::startsWith($route->getName(), 'admin.')
            && !in_array($route->getName(), self::SKIP_ROUTES, true)
            && !$request->headers->has('X-Live-Update')         // the page refreshing its own content
            && !$request->expectsJson()
            && !auth('web')->user()?->canUseAdminPanel()        // the admin (or an admin-panel user) checking the website
            && !$request->cookie(self::STAFF_COOKIE)            // ...or their browser, logged out / testing as a member
            && $request->headers->get('Accept-Language')        // every real browser sends it; most scanners don't
            && $response instanceof Response && $response->getStatusCode() === 200
            && Str::contains((string) $response->headers->get('Content-Type'), 'text/html')
            && !$this->isBot((string) $request->userAgent());
    }

    /** The article shown: the first route parameter (model id, slug or edition id). */
    private function subject(array $parameters): ?string
    {
        foreach ($parameters as $name => $value) {
            if ($name === 'language') {
                continue; // e-paper: count the edition id, not the language
            }
            $value = $value instanceof Model ? $value->getKey() : $value;

            return is_scalar($value) ? mb_substr((string) $value, 0, 60) : null;
        }

        return null;
    }

    private function referrerHost(Request $request): ?string
    {
        $host = parse_url((string) $request->headers->get('referer'), PHP_URL_HOST);
        if (!$host || $host === $request->getHost()) {
            return null; // direct visit or a click inside the site
        }

        return mb_substr(preg_replace('/^www\./', '', strtolower($host)), 0, 120);
    }

    private function isBot(string $agent): bool
    {
        return $agent === '' || (bool) preg_match('/bot|crawl|spider|slurp|facebookexternalhit|whatsapp|preview|lighthouse|headless|phantom|selenium|puppeteer|playwright|curl|wget|python|http-client|go-http|okhttp|java\/|axios|node-fetch|libwww|httpx|aiohttp|postman|scan|censys|zgrab|expanse|netcraft|semrush|ahrefs|bytespider|dataprovider|monitor|uptime|pingdom|statuscake|site24x7/i', $agent);
    }
}
