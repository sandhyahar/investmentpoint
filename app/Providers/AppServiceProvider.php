<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        // Same instance for handle() and terminate(), so the page view recorded in handle() is saved after the response
        $this->app->singleton(\App\Http\Middleware\TrackPageView::class);
        $this->app->singleton(\App\Http\Middleware\RunNewsAutomation::class);
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        // The site and admin panel use Bootstrap, not Tailwind (Laravel's default pagination look)
        \Illuminate\Pagination\Paginator::useBootstrapFive();

        // A session signed out because the password changed elsewhere goes to the right login page
        // (same rule as App\Http\Middleware\Authenticate; without it Laravel looks for a route named "login")
        \Illuminate\Session\Middleware\AuthenticateSession::redirectUsing(
            fn ($request) => $request->routeIs('member.*') ? route('member.login') : route('admin.login'));

        // Live site safety locks: links always https (so secure cookies work behind the host's proxy),
        // and never debug mode: its error pages show .env passwords and keys to anyone.
        // https only on a real domain: this computer (127.0.0.1 / localhost) and other PCs / phones on the
        // same Wi-Fi (http://192.168.x.x:8000) have no https.
        if ($this->app->environment('production')) {
            $host = (string) ($this->app->runningInConsole() ? parse_url((string) config('app.url'), PHP_URL_HOST) : request()->getHost());
            $localIp = filter_var(trim($host, '[]'), FILTER_VALIDATE_IP) && !filter_var(trim($host, '[]'), FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
            if (!$localIp && $host !== 'localhost' && !str_ends_with($host, '.localhost')) {
                \Illuminate\Support\Facades\URL::forceScheme('https');
            }
            config(['app.debug' => false]);
        }

        // Plan prices set in Admin -> Site Settings replace the defaults in config/subscription.php
        try {
            \App\Models\SubscriptionRequest::applySavedPrices();
        } catch (\Throwable) {
            // Database not reachable yet (fresh install / maintenance): keep the default price
        }
        // ...and the Gmail App Password saved there replaces MAIL_PASSWORD from .env
        try {
            \App\Models\Setting::applyMailPassword();
        } catch (\Throwable) {
            // Database not reachable yet: the .env password is used
        }
    }
}
