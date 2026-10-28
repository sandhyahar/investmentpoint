<?php

namespace Tests;

use Illuminate\Contracts\Console\Kernel;

trait CreatesApplication
{
    /**
     * Creates the application.
     *
     * @return \Illuminate\Foundation\Application
     */
    public function createApplication()
    {
        // A cached config (php artisan optimize / config:cache) makes tests ignore phpunit.xml and use the
        // real database from .env, which RefreshDatabase would then wipe. Never run tests like that.
        if (file_exists(__DIR__.'/../bootstrap/cache/config.php')) {
            throw new \RuntimeException('Tests stopped: the config is cached. Run "php artisan optimize:clear" first (a cached config would make the tests wipe the real database).');
        }

        $app = require __DIR__.'/../bootstrap/app.php';

        $app->make(Kernel::class)->bootstrap();

        if ($app['config']->get('database.default') !== 'sqlite' || $app['config']->get('database.connections.sqlite.database') !== ':memory:') {
            throw new \RuntimeException('Tests stopped: they must use the in-memory sqlite database from phpunit.xml, not ' . $app['config']->get('database.default') . '.');
        }

        return $app;
    }
}
