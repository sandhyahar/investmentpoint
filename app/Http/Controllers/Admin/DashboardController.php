<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ExpertPost;
use App\Models\MediaArticle;
use App\Models\News;
use App\Models\PageView;
use App\Models\ReaderQuery;
use App\Models\SubscriptionRequest;
use App\Models\TechnicalAnalysis;
use App\Models\UserBlog;
use App\Models\YoutubeVideo;
use App\Services\SeoAudit;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /** Team users without the Dashboard land here after login: a hello, their role and username, and their pages */
    public function welcome()
    {
        $user = \Illuminate\Support\Facades\Auth::user();

        return view('admin.welcome', [
            'user' => $user,
            'role' => $user->isAdmin() ? 'Super Admin' : ($user->adminRole->name ?? 'No role'),
            'pages' => \App\Support\AdminPages::pagesFor($user),
        ]);
    }

    public function index(SeoAudit $seo)
    {
        $totalNews = News::count();
        $totalTechnicalAnalysis = TechnicalAnalysis::count();
        $totalYoutubeVideos = YoutubeVideo::count();

        // Visitors: page views and unique visitors for each period
        $periods = [
            'today' => ['Today', now()->startOfDay()],
            'week' => ['Last 7 days', now()->subDays(6)->startOfDay()],
            'month' => ['Last 30 days', now()->subDays(29)->startOfDay()],
            'all' => ['All time', null],
        ];
        $traffic = [];
        foreach ($periods as $key => [$label, $from]) {
            $q = PageView::query()->when($from, fn ($q) => $q->where('created_at', '>=', $from));
            $traffic[$key] = ['label' => $label, 'views' => (clone $q)->count(), 'visitors' => (clone $q)->distinct()->count('visitor')];
        }

        // Last 14 days, one bar per day
        $rows = PageView::where('created_at', '>=', now()->subDays(13)->startOfDay())
            ->selectRaw('DATE(created_at) d, COUNT(*) views, COUNT(DISTINCT visitor) visitors')
            ->groupBy('d')->get()->keyBy('d');
        $daily = collect(range(13, 0))->map(function ($ago) use ($rows) {
            $day = now()->subDays($ago)->toDateString();

            return ['date' => Carbon::parse($day), 'views' => (int) ($rows[$day]->views ?? 0), 'visitors' => (int) ($rows[$day]->visitors ?? 0)];
        });

        $pages = PageView::selectRaw('route, COUNT(*) views')->groupBy('route')->orderByDesc('views')->take(10)->get();
        $referrers = PageView::whereNotNull('referrer_host')->selectRaw('referrer_host, COUNT(*) views')->groupBy('referrer_host')->orderByDesc('views')->take(6)->get();
        $devices = PageView::selectRaw('device, COUNT(*) views')->groupBy('device')->pluck('views', 'device');

        // Most read articles (news & technical analysis views are shown on their own admin lists)
        $expertViews = PageView::viewsPerArticle('expert');
        $topExperts = $this->topOf(ExpertPost::class, $expertViews, fn ($p) => ['title' => $p->title, 'url' => $p->url, 'date' => $p->published_on]);

        $mediaViews = PageView::viewsPerArticle('media');
        $topMedia = $this->topOf(MediaArticle::class, $mediaViews, fn ($m) => ['title' => $m->title, 'url' => $m->url, 'date' => $m->published_on]);

        $blogViews = PageView::viewsPerArticle('blog');
        $topBlogs = UserBlog::whereIn('slug', $blogViews->keys())->get()
            ->map(fn ($b) => ['title' => $b->title, 'url' => route('blog.show', $b->slug), 'date' => $b->approved_at, 'views' => (int) $blogViews[$b->slug]])
            ->sortByDesc('views')->take(10)->values();

        $seo = $seo->run();

        // "Needs your attention" cards at the top
        $attention = [
            'subscriptions' => SubscriptionRequest::where('status', 'new')->count(),
            'activeSubscriptions' => SubscriptionRequest::running()->count(),
            'blogs' => UserBlog::where('status', 'pending')->count(),
            'queries' => ReaderQuery::whereNull('read_at')->count(),
        ];

        return view('admin.dashboard', compact(
            'attention',
            'totalNews', 'totalTechnicalAnalysis', 'totalYoutubeVideos',
            'traffic', 'daily', 'pages', 'referrers', 'devices',
            'topExperts', 'topMedia', 'topBlogs', 'seo'
        ));
    }

    /** The 10 most viewed items of a model, from a [id => views] list. */
    private function topOf(string $model, $views, callable $fields)
    {
        $ids = $views->sortDesc()->take(10)->keys();

        return $model::whereIn('id', $ids)->get()
            ->map(fn ($item) => $fields($item) + ['views' => (int) $views[$item->id]])
            ->sortByDesc('views')->values();
    }
}
