<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\NewsInboxItem;
use App\Services\NewsCollector;
use App\Services\NewsWriter;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Admin -> News Inbox: business headlines collected from publishers' RSS feeds.
 * Pick one, write an original short article from it (optionally with a free AI draft),
 * and publish it to Business News or Most Popular.
 */
class NewsInboxController extends Controller
{
    public function index(Request $request, NewsWriter $writer)
    {
        $tab = $request->query('tab') === 'popular' ? 'popular' : 'latest';
        $search = trim((string) $request->query('q', ''));
        $source = (string) $request->query('source', '');

        $items = NewsInboxItem::open()
            ->when($search !== '', fn ($q) => $q->where(fn ($q) => $q
                ->where('title', 'like', '%' . addcslashes($search, '%_\\') . '%')
                ->orWhere('summary', 'like', '%' . addcslashes($search, '%_\\') . '%')))
            ->when($source !== '', fn ($q) => $q->where('source', $source))
            // Popular: stories most publishers are covering (last 2 days), then by keyword score
            ->when($tab === 'popular', fn ($q) => $q->where('published_at', '>=', now()->subDays(2))->where('coverage', '>', 1)
                ->orderByDesc('coverage')->orderByDesc('score'))
            ->orderByDesc('published_at')
            ->paginate(20)->withQueryString();

        $sources = NewsInboxItem::open()->distinct()->orderBy('source')->pluck('source');
        $aiReady = $writer->isConfigured();

        return view('admin.news-inbox.index', compact('items', 'tab', 'search', 'source', 'sources', 'aiReady'));
    }

    /** "Check for new stories" button: run the collector now instead of waiting for the schedule. */
    public function refresh(NewsCollector $collector)
    {
        $result = $collector->collect();

        return back()->with('success', $result['added'] . ' new stor' . ($result['added'] === 1 ? 'y' : 'ies') . ' added.');
    }

    /** "Auto-publish now" button: run the auto-publisher at once instead of waiting 30 minutes. */
    public function autoPublish(\App\Services\NewsAutoPublisher $publisher)
    {
        @set_time_limit(300); // several AI drafts + images
        $r = $publisher->run();
        $message = "Published {$r['business']} Business News and {$r['popular']} Most Popular article(s)"
            . ($r['held'] ? ", {$r['held']} held (failed a safety check - they stay here for you)" : '')
            . (($r['hidden'] ?? 0) ? ", {$r['hidden']} older article(s) hidden after failing the fact-check" : '') . '.';

        return back()->with($r['message'] && !$r['business'] && !$r['popular'] ? 'error' : 'success', $message . ($r['message'] ? ' ' . $r['message'] : ''));
    }

    public function dismiss(NewsInboxItem $item)
    {
        $item->update(['status' => 'dismissed']);

        return back()->with('success', 'Story removed from the inbox.');
    }

    public function bulkDismiss(Request $request)
    {
        $validated = $request->validate(['ids' => 'required|array', 'ids.*' => 'integer']);
        $count = NewsInboxItem::whereIn('id', $validated['ids'])->update(['status' => 'dismissed']);

        return back()->with('success', $count . ' stor' . ($count === 1 ? 'y' : 'ies') . ' removed from the inbox.');
    }

    /** "Write with AI" in the Business News / Most Popular form: returns a draft as JSON. */
    public function write(Request $request, NewsWriter $writer)
    {
        $data = $request->validate([
            'headline' => 'required|string|max:500',
            'summary' => 'nullable|string|max:2000',
            'source' => 'nullable|string|max:120',
            'form' => 'nullable|string|max:20',
        ]);

        $key = 'admin_ai_writer_' . now()->format('Y-m-d');
        $used = (int) cache($key, 0);
        $limit = (int) config('news.writer.daily_limit', 60);
        if ($used >= $limit) {
            return response()->json(['message' => "Daily AI writing limit reached ({$limit} articles). Please try again tomorrow."], 429);
        }

        try {
            // Market Trend "Nifty Derivative Market Trend Analysis": the owner's fixed Nifty format
            $draft = ($data['form'] ?? null) === 'market-trend' && NewsWriter::isNiftyTrend($data['headline'])
                ? $writer->niftyTrend($data['headline'])
                : $writer->draft($data['headline'], $data['summary'] ?? null, $data['source'] ?? null);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 502);
        }

        cache()->put($key, $used + 1, now()->endOfDay());

        return response()->json($draft);
    }
}
