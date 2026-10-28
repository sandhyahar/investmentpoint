<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

/**
 * A number that changes whenever the admin saves or deletes something.
 * Open public pages poll it and refresh their content (without a reload) when it changes.
 */
class ContentVersion
{
    private const KEY = 'site.content_version';

    public static function current(): string
    {
        return (string) Cache::rememberForever(self::KEY, fn () => (string) now()->getTimestampMs());
    }

    public static function bump(): void
    {
        Cache::forever(self::KEY, (string) now()->getTimestampMs());
    }
}
