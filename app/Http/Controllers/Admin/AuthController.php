<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\AdminPages;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    /**
     * Show admin login form.
     */
    public function showLogin()
    {
        if (Auth::check() && Auth::user()->canSignInToAdmin()) {
            return redirect()->to(AdminPages::home(Auth::user()));
        }

        return view('admin.auth.login');
    }

    /**
     * Handle admin login. Only the Super Admin and the active admin-panel users made in User & Role Management ("staff") can sign in, and after config('admin.max_attempts')
     * wrong passwords for a username from one IP the login is locked for a while (stops password guessing).
     */
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'username' => 'required|string|max:100',
            'password' => 'required|string|max:200',
        ]);

        // Computer / laptop only: the login page reports the real screen + mouse (catches "Desktop site" mode on phones)
        if (config('admin.desktop_only') && !str_starts_with((string) $request->input('device'), 'desktop|')) {
            Log::warning('Admin login refused: not a computer', ['username' => $credentials['username'], 'ip' => $request->ip(), 'device' => $request->input('device')]);

            return back()->withErrors(['username' => 'The admin panel can only be used on a computer or laptop.'])->onlyInput('username');
        }

        $key = 'admin-login|' . Str::lower($credentials['username']) . '|' . $request->ip();
        $ipKey = 'admin-login-ip|' . $request->ip(); // many usernames from one IP

        if (RateLimiter::tooManyAttempts($key, config('admin.max_attempts')) || RateLimiter::tooManyAttempts($ipKey, 20)) {
            $minutes = (int) ceil(max(RateLimiter::availableIn($key), RateLimiter::availableIn($ipKey)) / 60);
            Log::warning('Admin login locked', ['username' => $credentials['username'], 'ip' => $request->ip()]);

            return back()->withErrors([
                'username' => "Too many wrong attempts. For your security the login is locked - try again in {$minutes} minute(s).",
            ])->onlyInput('username');
        }

        if (Auth::attempt($credentials + ['role' => fn ($query) => $query->whereIn('role', ['admin', 'staff'])])) {
            RateLimiter::clear($key);
            // Right password, but the Super Admin switched this login (or its role) off
            if (!Auth::user()->canSignInToAdmin()) {
                Auth::logout();
                Log::warning('Admin login refused: switched off', ['username' => $credentials['username'], 'ip' => $request->ip()]);

                return back()->withErrors(['username' => 'This login (or its role) has been switched off. Please contact the Super Admin.'])->onlyInput('username');
            }
            $request->session()->regenerate();
            Log::info('Admin login', ['username' => $credentials['username'], 'ip' => $request->ip()]);

            // Team users without the dashboard always land on the Welcome page, not straight in one of their pages
            // (owner, 23 Oct 2026); the Super Admin goes back to the page they asked for, else the dashboard
            return Auth::user()->isAdmin()
                ? redirect()->intended(AdminPages::home(Auth::user()))
                : redirect()->to(AdminPages::home(Auth::user()));
        }

        RateLimiter::hit($key, config('admin.lockout_minutes') * 60);
        RateLimiter::hit($ipKey, config('admin.lockout_minutes') * 60);
        Log::warning('Admin login failed', ['username' => $credentials['username'], 'ip' => $request->ip()]);

        $left = config('admin.max_attempts') - RateLimiter::attempts($key);

        return back()->withErrors([
            'username' => 'The provided credentials do not match our records.'
                . ($left > 0 && $left <= 3 ? " {$left} attempt(s) left before the login is locked." : ''),
        ])->onlyInput('username');
    }

    /**
     * Handle admin logout.
     */
    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }
}
