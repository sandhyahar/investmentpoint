<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** How many readers opened a live headline from the homepage "Latest News" box. */
class NewsClick extends Model
{
    protected $fillable = ['url_hash', 'url', 'clicks'];

    public static function hashOf(string $url): string
    {
        return sha1($url);
    }

    public static function record(string $url): void
    {
        if (!config('app.track_visits')) {
            return;
        }
        $hash = self::hashOf($url);
        $row = self::firstOrCreate(['url_hash' => $hash], ['url' => $url]);
        $row->increment('clicks');
    }

    /** [url => clicks] for the given urls. */
    public static function countsFor($urls)
    {
        $urls = collect($urls)->filter()->values();
        $byHash = $urls->keyBy(fn ($u) => self::hashOf($u));

        return self::whereIn('url_hash', $byHash->keys())->pluck('clicks', 'url_hash')
            ->mapWithKeys(fn ($clicks, $hash) => [$byHash[$hash] => (int) $clicks]);
    }
}
