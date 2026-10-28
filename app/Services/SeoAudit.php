<?php

namespace App\Services;

use App\Models\EditorArticle;
use App\Models\ExpertPost;
use App\Models\LatestNews;
use App\Models\News;
use App\Models\PopularNews;
use App\Models\Setting;
use App\Models\TechnicalAnalysis;
use App\Support\Html;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

/**
 * On-site SEO health check for the admin dashboard. Every check looks at the real site
 * (settings, routes, and the content in the database) and returns pass / warn / fail with a tip.
 * This is not Google's ranking - that comes from Google Search Console once the site is live.
 */
class SeoAudit
{
    /** @return array{score: int, grade: string, checks: array<int, array{label: string, status: string, weight: int, detail: string, tip: ?string}>} */
    public function run(): array
    {
        $layout = @file_get_contents(resource_path('views/layouts/app.blade.php')) ?: '';
        $checks = [];

        $https = Str::startsWith(config('app.url'), 'https://');
        $checks[] = $this->check('Secure HTTPS address', $https ? 'pass' : 'fail', 10,
            $https ? 'APP_URL uses https.' : 'APP_URL is ' . config('app.url'),
            'On the live server set APP_URL=https://investmentpoint.in in .env and install an SSL certificate.');

        $checks[] = $this->check('Sitemap for Google (sitemap.xml)', Route::has('seo.sitemap') ? 'pass' : 'fail', 10,
            Route::has('seo.sitemap') ? 'Available at ' . route('seo.sitemap') : 'Missing',
            'Submit ' . url('sitemap.xml') . ' in Google Search Console after going live.');

        $checks[] = $this->check('robots.txt with sitemap link', Route::has('seo.robots') && !file_exists(public_path('robots.txt')) ? 'pass' : 'warn', 5,
            'Admin pages are blocked from search engines; the sitemap is listed.', null);

        $meta = Str::contains($layout, ['og:title', 'rel="canonical"', 'twitter:card', "meta_description"]);
        $checks[] = $this->check('Page titles, descriptions, canonical & share tags', $meta ? 'pass' : 'fail', 10,
            $meta ? 'Every page has a title, description, canonical link and Open Graph / Twitter tags.' : 'Missing in the layout', null);

        $checks[] = $this->check('Structured data (Google rich results)', Str::contains($layout, 'application/ld+json') ? 'pass' : 'fail', 5,
            'NewsMediaOrganization details are added to every page.', null);

        $checks[] = $this->check('Mobile friendly', Str::contains($layout, 'name="viewport"') ? 'pass' : 'fail', 10,
            'Responsive layout with a mobile viewport.', null);

        $verified = (bool) Setting::googleVerification();
        $checks[] = $this->check('Google Search Console connected', $verified ? 'pass' : 'warn', 10,
            $verified ? 'Verification tag is on every page.' : 'Not connected yet.',
            'Google Search Console → Add property → HTML tag, then paste the tag in Admin → Settings → Google (SEO).');

        $analytics = (bool) Setting::googleAnalyticsId();
        $checks[] = $this->check('Google Analytics connected', $analytics ? 'pass' : 'warn', 5,
            $analytics ? 'Visitors are measured in Google Analytics.' : 'Not connected yet.',
            'analytics.google.com → Admin → Create property → Web stream, then paste the Measurement ID (G-...) in Admin → Settings → Google (SEO).');

        $articleSchema = file_exists(resource_path('views/partials/article-schema.blade.php'));
        $checks[] = $this->check('Article details for Google (NewsArticle)', $articleSchema ? 'pass' : 'fail', 5,
            'Every news, analysis, Expert’s View, media article and blog page tells Google its headline, date, picture and author.', null);

        // Content quality: every news article page of the site (Market Trend + Latest News + Popular News), newest 200 of each
        $news = News::where('status', 'active')->latest()->take(200)->get()
            ->concat(LatestNews::active()->latest('id')->take(200)->get())
            ->concat(PopularNews::active()->latest('id')->take(200)->get());
        $goodTitles = $news->filter(fn ($n) => mb_strlen($n->title) >= 20 && mb_strlen($n->title) <= 110)->count();
        $ratio = $news->count() ? $goodTitles / $news->count() : 0;
        $checks[] = $this->check('News headlines of good length (20-110 characters)', $ratio >= .9 ? 'pass' : ($ratio >= .6 || $news->isEmpty() ? 'warn' : 'fail'), 10,
            $news->count() ? round($ratio * 100) . '% of ' . $news->count() . ' news articles' : 'No news articles yet',
            'Very short or very long headlines rank worse. Edit them in Admin → Market Trend or Latest News (the "Suggest SEO titles" button helps).');

        // What each article page really gives Google as its description (summary, continued with the article when short)
        $described = $news->filter(fn ($n) => mb_strlen(Html::metaDescription($n->description, $n->body)) >= 70)->count();
        $ratio = $news->count() ? $described / $news->count() : 0;
        $checks[] = $this->check('News with a proper Google description (70+ characters)', $ratio >= .9 ? 'pass' : ($ratio >= .6 || $news->isEmpty() ? 'warn' : 'fail'), 10,
            $news->count() ? round($ratio * 100) . '% of ' . $news->count() . ' news articles' : 'No news articles yet',
            'Google shows this text under your link. It is taken from the first lines of the Full Article, so write the Full Article for each news item.');

        $latest = max(
            optional(News::max('created_at'), fn ($d) => \Carbon\Carbon::parse($d))?->timestamp ?? 0,
            optional(TechnicalAnalysis::max('created_at'), fn ($d) => \Carbon\Carbon::parse($d))?->timestamp ?? 0,
            optional(ExpertPost::max('published_on'), fn ($d) => \Carbon\Carbon::parse($d))?->timestamp ?? 0,
            optional(LatestNews::active()->max('created_at'), fn ($d) => \Carbon\Carbon::parse($d))?->timestamp ?? 0,
            optional(EditorArticle::max('created_at'), fn ($d) => \Carbon\Carbon::parse($d))?->timestamp ?? 0,
        );
        $days = $latest ? (int) floor((time() - $latest) / 86400) : null;
        $checks[] = $this->check('Fresh content (new article in the last 7 days)', $days !== null && $days <= 7 ? 'pass' : ($days !== null && $days <= 30 ? 'warn' : 'fail'), 10,
            $days === null ? 'No articles yet' : ($days === 0 ? 'Last article added today' : "Last article added {$days} day(s) ago"),
            'Search engines favour sites that publish regularly.');

        // Score: pass = full weight, warn = half
        $total = array_sum(array_column($checks, 'weight'));
        $earned = array_sum(array_map(fn ($c) => $c['status'] === 'pass' ? $c['weight'] : ($c['status'] === 'warn' ? $c['weight'] / 2 : 0), $checks));
        $score = (int) round($earned / $total * 100);

        return [
            'score' => $score,
            'grade' => $score >= 90 ? 'Excellent' : ($score >= 75 ? 'Good' : ($score >= 50 ? 'Needs work' : 'Poor')),
            'checks' => $checks,
        ];
    }

    private function check(string $label, string $status, int $weight, string $detail, ?string $tip): array
    {
        return compact('label', 'status', 'weight', 'detail') + ['tip' => $status === 'pass' ? null : $tip];
    }
}
