<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Members (WhatsApp OTP login) stay signed in while they keep visiting, and are signed out when they
 * have not opened a page for more than config('subscription.member_idle_days') days (1 by default).
 *
 * The OTP login sets a "remember me" cookie, so the 2-hour session running out does not sign a member
 * out. This middleware notes when a member was last here (users.last_seen_at, at most one write every
 * 10 minutes) and signs out a member who comes back after the limit; they log in again with a new OTP.
 * Admins are not handled here: the admin panel has its own 60-minute idle logout.
 *
 * Members have a login of their own (guard "member"), separate from the admin login (guard "web").
 * A member still signed in the old way (both shared "web" until 13 Oct 2026) is moved over here.
 */
class KeepMemberSignedIn
{
    private const NOTE_EVERY_SECONDS = 600;

    public function handle(Request $request, Closure $next)
    {
        // Polling routes run without a session: they are not visits
        if (!$request->hasSession()) {
            return $next($request);
        }

        $members = Auth::guard('member');
        $old = Auth::guard('web');
        if ($old->user()?->role === 'user') {
            $moved = $old->user();
            $old->logoutCurrentDevice();
            if (!$members->check()) {
                $members->login($moved, true);
            }
        }
        if (!$members->check()) {
            return $next($request);
        }

        $member = $members->user();
        $days = (int) config('subscription.member_idle_days', 1);
        $lastSeen = $member->last_seen_at;

        if ($days > 0 && $lastSeen && $lastSeen->lt(now()->subDays($days))) {
            // Only this browser; the note is cleared so another device isn't signed out for the same absence twice
            DB::table('users')->where('id', $member->id)->update(['last_seen_at' => null]);
            $members->logoutCurrentDevice();
            Auth::guard('web')->check() ? $request->session()->regenerate() : $request->session()->invalidate();
            $request->session()->regenerateToken();
            $request->session()->flash('success', 'You were logged out because you did not visit for more than ' . ($days === 1 ? '1 day' : "{$days} days") . '. Please log in again.');

            return $request->routeIs('member.*') ? redirect()->route('member.login') : $next($request);
        }

        if (!$lastSeen || $lastSeen->lt(now()->subSeconds(self::NOTE_EVERY_SECONDS))) {
            // Straight to the table: "updated_at" and model events stay untouched
            DB::table('users')->where('id', $member->id)->update(['last_seen_at' => now()]);
        }

        // Signed back in by the remember cookie (it lasts 400 days, the browser maximum): renew it,
        // so a member who visits regularly is never signed out just because the cookie got old
        if ($members->viaRemember()) {
            $members->login($member, true);
        }

        return $next($request);
    }
}
