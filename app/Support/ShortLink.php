<?php

namespace App\Support;

use App\Models\News;
use App\Models\TechnicalAnalysis;
use App\Models\UserBlog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

/**
 * Short share links: investmentpoint.in/nifty, investmentpoint.in/hdfc-bank. The name comes from the
 * article's title (the index or company), and the link always opens the NEWEST active Market Trend /
 * Technical Analysis article with that name (the owner's choice: a link shared yesterday shows today's
 * article). An approved blog with the same address keeps it; site pages (news, subscription, ...) too.
 * The admin can give an article its own name (Share URL on the form / in the Share box): it is shared with
 * that name, which wins over the names made from titles; the title's name keeps working as well.
 */
class ShortLink
{
    /** Market Trend titles: the index they are about (first match wins: "Bank Nifty" before "Nifty") */
    public const INDICES = ['bank nifty' => 'bank-nifty', 'banknifty' => 'bank-nifty', 'fin nifty' => 'finnifty', 'finnifty' => 'finnifty',
        'midcap nifty' => 'midcap-nifty', 'nifty' => 'nifty', 'sensex' => 'sensex'];

    /** The short name of an article, or null when the title gives none */
    public static function nameFor(Model $article): ?string
    {
        $title = trim((string) $article->title);
        if ($article instanceof News) {
            $lower = mb_strtolower($title);
            foreach (self::INDICES as $words => $name) {
                if (preg_match('/\b' . preg_quote($words, '/') . '\b/u', $lower)) {
                    return $name;
                }
            }

            return null; // a general market article ("Indian Stock Market Remains Volatile ...")
        }
        // Technical Analysis: the company before "Technical Analysis / View" ("HDFC Bank Technical Analysis : ...")
        if (!preg_match('/^(.+?)\s+technical\s+(?:analysis|view)\b/iu', $title, $m)) {
            return null;
        }
        $name = Str::slug($m[1]);

        return $name !== '' && strlen($name) <= 40 ? $name : null;
    }

    /** The admin's own short name of an article (Share URL), or null */
    public static function customFor(Model $article): ?string
    {
        return self::normalize($article->share_name);
    }

    /** What the admin typed as Share URL -> a short name: "investmentpoint.in/HDFC Bank 21" -> "hdfc-bank-21" (empty -> null) */
    public static function normalize(?string $input): ?string
    {
        $input = preg_replace('#^\s*(?:https?://)?(?:www\.)?investmentpoint\.in/#i', '', (string) $input);
        $name = Str::slug($input);

        return $name !== '' ? $name : null;
    }

    /** Validation of the Share URL box (saved as normalize() of it) */
    public static function rule(): array
    {
        return ['nullable', 'string', 'max:100', function ($attribute, $value, $fail) {
            $name = self::normalize($value);
            if ($name && strlen($name) > 60) {
                $fail('The Share URL can be at most 60 letters.');
            } elseif ($name && self::taken($name)) {
                $fail("investmentpoint.in/{$name} is already a page or blog of the site — choose another Share URL.");
            }
        }];
    }

    /** name => [the newest active article's full link, its class, its id] (rebuilt after any admin change) */
    private static function targets(): array
    {
        return Cache::remember('short-links.' . ContentVersion::current(), 3600, function () {
            $all = News::where('status', 'active')->get(['id', 'title', 'share_name', 'created_at'])
                ->concat(TechnicalAnalysis::where('status', 'active')->get(['id', 'title', 'share_name', 'created_at']))
                ->sortByDesc(fn ($a) => [$a->created_at?->timestamp ?? 0, $a->id]);
            $custom = $targets = [];
            foreach ($all as $article) {
                $own = self::customFor($article);
                if ($own && !isset($custom[$own]) && !self::taken($own)) {
                    $custom[$own] = [$article->url, $article::class, $article->id];
                }
                $name = self::nameFor($article);
                if ($name && !isset($targets[$name]) && !self::taken($name)) {
                    $targets[$name] = [$article->url, $article::class, $article->id];
                }
            }

            return $custom + $targets; // the admin's name wins over a name made from a title
        });
    }

    /** The address belongs to a site page or an approved blog */
    private static function taken(string $name): bool
    {
        static $paths = null;
        $paths ??= collect(Route::getRoutes()->getRoutes())
            ->map(fn ($r) => strtolower(explode('/', trim($r->uri(), '/'))[0]))
            ->reject(fn ($s) => $s === '' || str_starts_with($s, '{'))
            ->merge(['storage', 'css', 'js', 'images', 'data', 'build', 'vendor', config('admin.path')])
            ->flip()->all();

        return isset($paths[$name]) || UserBlog::where('slug', $name)->where('status', 'approved')->exists();
    }

    /** Where investmentpoint.in/{name} goes, or null */
    public static function resolve(string $name): ?string
    {
        return self::targets()[strtolower($name)][0] ?? null;
    }

    /**
     * The link to share for an article: the short one when this article is the one it opens (the newest
     * with its name; the admin's Share URL first), else the article's full link (an older Nifty article keeps
     * its own address).
     */
    public static function urlFor(Model $article): string
    {
        foreach (array_filter([self::customFor($article), self::nameFor($article)]) as $name) {
            $target = self::targets()[$name] ?? null;
            if ($target && $target[1] === $article::class && $target[2] === $article->id) {
                return url('/' . $name);
            }
        }

        return $article->url;
    }
}
