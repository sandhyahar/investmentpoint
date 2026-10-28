<?php

namespace App\Http\Controllers;

use App\Models\ExpertPost;
use App\Models\PageView;
use App\Models\UserBlog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class ExpertViewController extends Controller
{
    /** Tabs on the Expert's View page: key => [label, filter]. */
    public const TYPES = [
        'experts' => 'Guest Experts',
        'research' => 'Research Reports',
        'news' => 'News Headlines',
    ];

    public function index(Request $request)
    {
        $search = trim((string) $request->query('q', ''));
        $posts = ExpertPost::active() // one list of all articles (category tabs removed)
            ->when($search !== '', fn ($q) => $q->where(fn ($q) => $q
                ->where('title', 'like', '%' . addcslashes($search, '%_\\') . '%')
                ->orWhere('author', 'like', '%' . addcslashes($search, '%_\\') . '%')))
            ->orderByRaw('image IS NULL') // articles with a picture first, then the rest
            ->orderByDesc('published_on')->orderByDesc('id')
            ->paginate(13)->withQueryString();

        $counts = ['all' => ExpertPost::active()->count()];

        // Approved reader blogs (Blog -> Create Blog), newest first, at the top of the
        // unfiltered first page. This replaced the separate "Show Blog" page.
        $blogs = (!$search && $posts->onFirstPage())
            ? UserBlog::where('status', 'approved')->latest('approved_at')->latest('id')->get()
            : collect();
        $stats = $this->sectionStats();

        return view('experts-view.index', compact('posts', 'search', 'counts', 'blogs', 'stats'));
    }

    public function show(ExpertPost $expertPost)
    {
        abort_unless($expertPost->status === 'active', 404);

        $newer = ExpertPost::active()->where(fn ($q) => $q->where('published_on', '>', $expertPost->published_on)
            ->orWhere(fn ($q) => $q->where('published_on', $expertPost->published_on)->where('id', '>', $expertPost->id)))
            ->orderBy('published_on')->orderBy('id')->first();
        $older = ExpertPost::active()->where(fn ($q) => $q->where('published_on', '<', $expertPost->published_on)
            ->orWhere(fn ($q) => $q->where('published_on', $expertPost->published_on)->where('id', '<', $expertPost->id)))
            ->orderByDesc('published_on')->orderByDesc('id')->first();
        $more = ExpertPost::active()->whereKeyNot($expertPost->id)
            ->when($expertPost->author, fn ($q) => $q->where('author', $expertPost->author))
            ->orderByDesc('published_on')->take(5)->get();

        // This visit is saved after the page is sent (TrackPageView), so count it in already
        $thisVisit = config('app.track_visits') ? 1 : 0;
        $views = PageView::where('route', 'experts-view.show')->where('subject_id', (string) $expertPost->id)->count() + $thisVisit;

        return view('experts-view.show', [
            'post' => $expertPost, 'newer' => $newer, 'older' => $older, 'more' => $more,
            'views' => $views, 'stats' => $this->sectionStats(),
        ]);
    }

    /**
     * "Total Pageviews" box, like the old Blogger site: all real Expert's View page views counted
     * on this site, and views per day for the last 30 days. Cached for 10 minutes so busy
     * articles don't count the whole table on every visit.
     */
    private function sectionStats(): array
    {
        return Cache::remember('experts-view.stats', 600, function () {
            $routes = ['experts-view.index', 'experts-view.show'];
            $from = now()->subDays(29)->startOfDay();
            $perDay = PageView::whereIn('route', $routes)->where('created_at', '>=', $from)
                ->selectRaw('DATE(created_at) day, COUNT(*) views')->groupBy('day')
                ->pluck('views', 'day');

            return [
                'total' => PageView::whereIn('route', $routes)->count(),
                'days' => collect(range(0, 29))->map(fn ($i) => (int) ($perDay[$from->copy()->addDays($i)->toDateString()] ?? 0))->all(),
            ];
        });
    }

    private function filtered($query, ?string $type)
    {
        return match ($type) {
            'experts' => $query->whereNotNull('author'),
            'research' => $query->whereNull('author')->where(fn ($q) => $q->where('title', 'like', '%Research%')->orWhere('title', 'like', '%Outlook%')->orWhere('title', 'like', '%Fund Houses%')),
            'news' => $query->whereNull('author')->where('title', 'like', '%હેડલાઇન્સ%'),
            default => $query,
        };
    }
}
