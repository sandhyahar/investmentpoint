<?php

namespace App\Http\Middleware;

use App\Support\AdminPages;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Every admin-panel request, checked on the server:
 * - only admin-panel logins; a login (or its role) switched off since sign-in is signed out at once
 * - "must change password": nothing but My Account until a new password is set
 * - the Super Admin opens everything; a staff user only the page + action their role allows
 */
class EnsureAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        abort_unless($user?->canUseAdminPanel(), 403);

        if (!$user->canSignInToAdmin()) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('admin.login')->withErrors(['username' => 'This login (or its role) has been switched off by the Super Admin.']);
        }

        // Team users keep the password the Super Admin gave them: no "set your own password first" step (owner, 23 Oct 2026)
        $route = (string) $request->route()?->getName();

        if (AdminPages::allows($user, $route, $request->wantsJson())) {
            return $next($request);
        }

        // The dashboard is where the login sends everyone: a staff user without it goes to their first page
        if ($route === 'admin.dashboard') {
            return redirect()->to(AdminPages::home($user));
        }

        abort(403, 'Your role does not allow this. Ask the Super Admin to give you access.');
    }
}
