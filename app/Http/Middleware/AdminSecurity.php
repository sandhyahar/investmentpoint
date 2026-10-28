<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Extra protection for every admin page (and the login page):
 * - computer / laptop only (config admin.desktop_only): phones and tablets are refused and signed out
 * - logs the admin out after config('admin.idle_minutes') without activity
 * - admin pages are never cached, so "Back" after logout can't show them
 * - search engines are told never to index them
 */
class AdminSecurity
{
    private const LAST_ACTIVE = 'admin_last_active';

    /** Phones and tablets, as named in the browser's User-Agent */
    public const MOBILE_AGENT = '/Android|iPhone|iPad|iPod|Mobile|Tablet|Opera Mini|IEMobile|BlackBerry|BB10|webOS|Windows Phone|Silk|Kindle|PlayBook/i';

    public function handle(Request $request, Closure $next): Response
    {
        // Computer / laptop only: a phone or tablet gets an explanation page and is signed out
        if (config('admin.desktop_only') && preg_match(self::MOBILE_AGENT, (string) $request->userAgent())) {
            if (Auth::check()) {
                Log::warning('Admin panel opened on a mobile device - signed out', ['username' => Auth::user()->username, 'ip' => $request->ip()]);
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();
            }

            return $this->noStore(response()->view('admin.auth.desktop-only', [], 403));
        }

        if (Auth::check()) {
            $last = (int) $request->session()->get(self::LAST_ACTIVE, 0);
            $idle = config('admin.idle_minutes') * 60;

            if ($last && $idle > 0 && time() - $last > $idle) {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return redirect()->route('admin.login')
                    ->withErrors(['username' => 'You were logged out after ' . config('admin.idle_minutes') . ' minutes of inactivity. Please sign in again.']);
            }
            $request->session()->put(self::LAST_ACTIVE, time());
        }

        $response = $next($request);

        // This browser belongs to the owner / staff: its visits to the website are never counted as readers
        if (Auth::check() && !$request->cookie(TrackPageView::STAFF_COOKIE)) {
            Cookie::queue(TrackPageView::STAFF_COOKIE, '1', 60 * 24 * 365);
        }

        // Activity log: every change made in the admin panel (who, what, from where) - storage/logs
        if (Auth::check() && !$request->isMethodSafe() && !$request->routeIs('admin.login.submit')) {
            Log::info('Admin action', [
                'username' => Auth::user()->username,
                'action' => $request->method() . ' ' . ($request->route()?->getName() ?? $request->path()),
                'target' => collect($request->route()?->parameters() ?? [])->map(fn ($p) => $p instanceof \Illuminate\Database\Eloquent\Model ? $p->getKey() : $p)->all(),
                'status' => $response->getStatusCode(),
                'ip' => $request->ip(),
            ]);
        }

        return $this->noStore($response);
    }

    private function noStore(Response $response): Response
    {
        $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, private');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow');

        return $response;
    }
}
