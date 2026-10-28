<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PageView extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['route', 'subject_id', 'path', 'visitor', 'referrer_host', 'device', 'created_at'];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    /** Friendly names for the admin dashboard's "Most visited pages". */
    public const ROUTE_LABELS = [
        'home' => 'Home page',
        'news.index' => 'News list',
        'news.show' => 'News articles',
        'analysis.index' => 'Technical Analysis list',
        'analysis.show' => 'Technical Analysis posts',
        'experts-view.index' => "Expert's View list",
        'experts-view.show' => "Expert's View articles",
        'media-articles.index' => 'Media Articles list',
        'media-articles.show' => 'Media Articles',
        'divya-bhaskar.index' => 'Divya Bhaskar',
        'epaper.index' => 'E-Paper list',
        'epaper.show' => 'E-Paper editions',
        'digital-newspaper.index' => 'Weekly News Paper list',
        'digital-newspaper.show' => 'Weekly News Paper editions',
        'blog.index' => 'Blogs list',
        'blog.show' => 'Blog posts',
        'blog.short' => 'Blog posts',
        'blog.create' => 'Create Blog',
        'editors-desk.index' => "Editor's View list",
        'editors-desk.show' => "Editor's View articles",
        'subscription' => 'Subscription',
        'about' => 'About Us',
        'contact' => 'Contact Us',
        'profile' => 'Profile',
        'terms' => 'Terms & Conditions',
        'privacy' => 'Privacy Policy',
        'refund' => 'Refund Policy',
        'disclaimer' => 'Disclaimer',
        'cookies' => 'Cookie Policy',
        'latest-news.show' => 'Latest News articles',
        'popular-news.show' => 'Most Popular News articles',
        'member.dashboard' => 'Member dashboard',
        'member.login' => 'Member login',
        'member.register' => 'Member login',
        'subscription.pay' => 'Subscription payment',
    ];

    /** Public page of each admin section, to count views per article. */
    public const ARTICLE_ROUTES = [
        'analysis' => ['analysis.show'],
        'expert' => ['experts-view.show'],
        'media' => ['media-articles.show'],
        'blog' => ['blog.show', 'blog.short'],
        'newspaper' => ['digital-newspaper.show'],
        'epaper' => ['epaper.show'],
        'editor' => ['editors-desk.show'],
    ];

    /**
     * Views of one article page, counting the current visit too (TrackPageView saves it after the page is sent).
     * $subject is the page's first route parameter: the id, or the slug for blogs.
     */
    public static function viewsOf(array $routes, $subject): int
    {
        return static::whereIn('route', $routes)->where('subject_id', (string) $subject)->count()
            + (config('app.track_visits') ? 1 : 0);
    }

    /**
     * Views per article as [subject_id => views]. $section is a key of ARTICLE_ROUTES;
     * pass $subjects (ids or slugs) to count only those articles, e.g. one admin page.
     */
    public static function viewsPerArticle(string $section, $subjects = null)
    {
        return static::whereIn('route', self::ARTICLE_ROUTES[$section])
            ->when($subjects !== null, fn ($q) => $q->whereIn('subject_id', collect($subjects)->map(fn ($s) => (string) $s)))
            ->whereNotNull('subject_id')
            ->selectRaw('subject_id, COUNT(*) views')->groupBy('subject_id')
            ->pluck('views', 'subject_id');
    }
}
