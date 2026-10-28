<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * The path to the "home" route for your application.
     *
     * Typically, users are redirected here after authentication.
     *
     * @var string
     */
    public const HOME = '/home';

    /**
     * Define your route model bindings, pattern filters, and other route configuration.
     *
     * @return void
     */
    public function boot()
    {
        $this->configureRateLimiting();

        $this->routes(function () {
            Route::middleware('api')
                ->prefix('api')
                ->group(base_path('routes/api.php'));

            Route::middleware('web')
                ->group(base_path('routes/web.php'));
        });
    }

    /**
     * Configure the rate limiters for the application.
     *
     * @return void
     */
    protected function configureRateLimiting()
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        // Per-route limits. Plain "throttle:N,1" shares ONE counter per visitor across every throttled
        // route, so the ticker's background polling used up the 5/min allowance of the forms and
        // visitors got "Too Many Requests" when submitting. Here each route counts on its own.
        RateLimiter::for('site', function (Request $request) {
            $perMinute = [
                'blog.store' => 5,
                'queries.store' => 5,
                'subscription.store' => 5,
                'member.otp.send' => 5,
                'member.otp.verify' => 10,
                'content.version' => 30,
                'news.latest' => 60,
                'market.data' => 120,
                'news.click' => 20,
                'blog.react' => 20,
                'admin.ai-image' => 20,
                'admin.stock-photo' => 30,
            ];
            $route = $request->route()?->getName() ?? 'other';

            return Limit::perMinute($perMinute[$route] ?? 60)->by($route . '|' . ($request->user()?->id ?: $request->ip()));
        });
    }
}
