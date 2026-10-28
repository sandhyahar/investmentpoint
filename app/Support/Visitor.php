<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;

/**
 * Anonymous visitor id (a random id in a cookie, stored only as a hash) - the same one the
 * page-view statistics use, so a reader is recognised without any login.
 */
class Visitor
{
    public const COOKIE = 'ip_vid';

    /** Hashed id of this visitor, or null if they have no id yet (unless $create). */
    public static function id(Request $request, bool $create = false): ?string
    {
        $raw = $request->cookie(self::COOKIE);
        if (!$raw || strlen($raw) !== 32) {
            if (!$create) {
                return null;
            }
            $raw = Str::random(32);
            Cookie::queue(self::COOKIE, $raw, 60 * 24 * 365);
        }

        return self::hash($raw);
    }

    public static function hash(string $raw): string
    {
        return substr(hash('sha256', $raw . config('app.key')), 0, 16);
    }
}
