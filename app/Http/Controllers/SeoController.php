<?php

namespace App\Http\Controllers;

use App\Models\DigitalNewspaper;
use App\Models\ExpertPost;
use App\Models\LatestNews;
use App\Models\MediaArticle;
use App\Models\News;
use App\Models\PopularNews;
use App\Models\TechnicalAnalysis;
use App\Models\UserBlog;
use Illuminate\Support\Facades\Cache;

class SeoController extends Controller
{
    /** Tells search engines what to crawl and where the sitemap is. */
    public function robots()
    {
        // A custom admin address (ADMIN_PATH) is kept secret: not listed here, admin pages send "noindex" instead
        $lines = array_merge(['User-agent: *'], config('admin.path') === 'admin' ? ['Disallow: /admin'] : [], [
            'Disallow: /api/',
            'Disallow: /content-version',
            'Disallow: /market-data',
            'Disallow: /latest-news',
            '',
            'Sitemap: ' . route('seo.sitemap'),
        ]);

        return response(implode("\n", $lines) . "\n", 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    /** Every public page Google should know about. Cached for an hour. */
    public function sitemap()
    {
        $xml = Cache::remember('seo.sitemap', now()->addHour(), function () {
            $urls = [];
            $add = function (string $loc, $lastmod = null, string $freq = 'weekly', string $priority = '0.6') use (&$urls) {
                $urls[] = ['loc' => $loc, 'lastmod' => $lastmod ? $lastmod->toDateString() : null, 'freq' => $freq, 'priority' => $priority];
            };

            // Main pages
            $add(route('home'), now(), 'hourly', '1.0');
            foreach (['news.index', 'analysis.index', 'experts-view.index', 'editors-desk.index', 'media-articles.index', 'divya-bhaskar.index'] as $name) {
                $add(route($name), now(), 'daily', '0.8');
            }
            foreach (array_keys(config('epaper.editions')) as $language) {
                $add(route('epaper.index', $language), now(), 'daily', '0.8');
            }
            $add(route('digital-newspaper.index'), now(), 'weekly', '0.8');
            foreach (['about', 'contact', 'subscription', 'profile', 'terms', 'privacy', 'refund', 'disclaimer', 'cookies'] as $name) {
                $add(route($name), null, 'monthly', '0.4');
            }

            // Articles
            foreach (News::latest('updated_at')->take(2000)->get() as $n) {
                $add($n->url, $n->updated_at, 'weekly', '0.7');
            }
            foreach ([LatestNews::class, PopularNews::class] as $model) {
                foreach ($model::active()->latest('updated_at')->take(2000)->get() as $n) {
                    $add($n->url, $n->updated_at, 'weekly', '0.7');
                }
            }
            foreach (TechnicalAnalysis::where('status', 'active')->latest('updated_at')->take(2000)->get() as $a) {
                $add($a->url, $a->updated_at, 'weekly', '0.7');
            }
            foreach (\App\Models\EditorArticle::active()->latestFirst()->take(2000)->get(['id', 'title', 'updated_at']) as $e) {
                $add($e->url, $e->updated_at, 'weekly', '0.7');
            }
            foreach (ExpertPost::active()->latest('published_on')->take(2000)->get() as $p) {
                $add($p->url, $p->updated_at, 'monthly', '0.6');
            }
            foreach (UserBlog::where('status', 'approved')->latest('approved_at')->take(2000)->get() as $b) {
                $add(route('blog.show', $b->slug), $b->updated_at, 'monthly', '0.5');
            }
            foreach (DigitalNewspaper::active()->latestFirst()->take(5000)->get(['id', 'edition', 'title', 'updated_at']) as $d) {
                $add($d->url, $d->updated_at, 'yearly', '0.5');
            }
            foreach (MediaArticle::active()->latest('published_on')->take(5000)->get(['id', 'title', 'updated_at']) as $m) {
                $add($m->url, $m->updated_at, 'yearly', '0.4');
            }

            return view('seo.sitemap', ['urls' => $urls])->render();
        });

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }
}
