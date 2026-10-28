<?php

namespace App\Http\Controllers;

use App\Models\LatestNews;
use App\Models\MarketIndex;
use App\Models\News;
use App\Models\PopularNews;
use App\Services\LiveNewsService;
use Illuminate\Http\Request;

class NewsPageController extends Controller
{
    /**
     * "View All" news: everything in Admin -> Latest News, newest first.
     * (The live Economic Times / LiveMint headlines are disabled - see the commented line.)
     */
    public function index(Request $request, LiveNewsService $liveNews)
    {
        $search = trim((string) $request->query('q', ''));

        // Live API - disabled: $news = $search !== '' ? $liveNews->search($search) : $liveNews->latest();
        $news = LatestNews::active()
            ->when($search !== '', fn ($q) => $q->where(fn ($q) => $q
                ->where('title', 'like', '%' . addcslashes($search, '%_\\') . '%')
                ->orWhere('description', 'like', '%' . addcslashes($search, '%_\\') . '%')))
            ->ordered()
            ->paginate(12)->withQueryString();

        return view('news.index', [
            'news' => $news,
            'search' => $search,
            'popular' => $this->popular(),
            'tickerIndices' => $this->tickerIndices(),
        ]);
    }

    public function show(News $news, ?string $slug = null)
    {
        abort_unless($news->status === 'active', 404);

        if (config('app.track_visits')) {
            $news->increment('views');
        }

        $related = News::where('status', 'active')
            ->whereKeyNot($news->getKey())
            ->latest()
            ->take(3)
            ->get();

        return view('news.show', [
            'news' => $news,
            'related' => $related,
            'popular' => $this->popular(),
            'tickerIndices' => $this->tickerIndices(),
        ]);
    }

    /** One item from Admin -> Latest News. */
    public function showLatest(int $id, ?string $slug = null)
    {
        // Old articles are deleted daily (news:prune): their links open the news list instead of an error page
        $item = LatestNews::active()->find($id);
        if (!$item) {
            return redirect()->route('news.index', [], 301);
        }

        return $this->showHeadline($item, LatestNews::active()->ordered(), 'Business News');
    }

    /** One item from Admin -> Most Popular News. */
    public function showPopular(int $id, ?string $slug = null)
    {
        $item = PopularNews::active()->find($id);
        if (!$item) {
            return redirect()->route('news.index', [], 301);
        }

        return $this->showHeadline($item, PopularNews::active()->ordered(), 'Most Popular');
    }

    private function showHeadline($item, $others, string $section)
    {
        if (config('app.track_visits')) {
            $item->increment('views');
        }

        return view('news.headline', [
            'item' => $item,
            'section' => $section,
            'related' => $others->whereKeyNot($item->getKey())->take(3)->get(),
            'popular' => $this->popular(),
            'tickerIndices' => $this->tickerIndices(),
        ]);
    }

    /** Sidebar "Most Popular": Admin -> Most Popular News, in the admin's order. */
    private function popular()
    {
        return PopularNews::active()->ordered()->take(5)->get();
    }

    private function tickerIndices()
    {
        $tickerNames = ['NIFTY 50', 'SENSEX', 'BANK NIFTY', 'INDIA VIX'];

        return MarketIndex::whereIn('name', $tickerNames)->get()->sortBy(function ($index) use ($tickerNames) {
            return array_search($index->name, $tickerNames, true);
        })->values();
    }
}
