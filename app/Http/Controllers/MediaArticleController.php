<?php

namespace App\Http\Controllers;

use App\Models\MediaArticle;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class MediaArticleController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->query('q', ''));
        $publication = trim((string) $request->query('publication', ''));

        $articles = MediaArticle::active()
            // Every stored spelling of the newspaper ("PAGDANDI /" too)
            ->when($publication !== '', fn ($q) => $q->whereIn('publication', MediaArticle::publicationVariants($publication)))
            ->when($search !== '', function ($q) use ($search) {
                // "20.02.2026" searches by date, anything else by title
                if (preg_match('/^(\d{1,2})[.\-\/](\d{1,2})[.\-\/](\d{4})$/', $search, $m) && checkdate((int) $m[2], (int) $m[1], (int) $m[3])) {
                    $q->where('published_on', sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1])); // DATE column: plain = uses the index
                } else {
                    $q->where('title', 'like', '%' . addcslashes($search, '%_\\') . '%');
                }
            })
            ->orderByDesc('published_on')->orderByDesc('id')
            ->paginate(24)->withQueryString();

        return view('media-articles.index', [
            'articles' => $articles,
            'search' => $search,
            'publication' => $publication,
            'publications' => $this->publications(),
        ]);
    }

    public function show(MediaArticle $mediaArticle)
    {
        abort_unless($mediaArticle->status === 'active' && !$mediaArticle->isScheduled(), 404);

        // Neighbours in the newest-first order (published_on desc, id desc)
        $newer = MediaArticle::active()
            ->where(fn ($q) => $q->where('published_on', '>', $mediaArticle->published_on)
                ->orWhere(fn ($q) => $q->where('published_on', $mediaArticle->published_on)->where('id', '>', $mediaArticle->id)))
            ->orderBy('published_on')->orderBy('id')->first();
        $older = MediaArticle::active()
            ->where(fn ($q) => $q->where('published_on', '<', $mediaArticle->published_on)
                ->orWhere(fn ($q) => $q->where('published_on', $mediaArticle->published_on)->where('id', '<', $mediaArticle->id)))
            ->orderByDesc('published_on')->orderByDesc('id')->first();
        $sameDay = MediaArticle::active()
            ->where('published_on', $mediaArticle->published_on?->toDateString()) // plain = uses the (status, published_on) index
            ->whereKeyNot($mediaArticle->id)
            ->orderBy('publication')->take(8)->get();

        return view('media-articles.show', ['article' => $mediaArticle, 'newer' => $newer, 'older' => $older, 'sameDay' => $sameDay]);
    }

    /**
     * Every newspaper for the filter (a scrollable row), most clippings first, each name once:
     * "PAGDANDI /" is counted with "PAGDANDI" (cached; the list barely changes).
     */
    private function publications()
    {
        return Cache::remember('media-articles.publications', now()->addHour(), function () {
            $counts = [];
            foreach (MediaArticle::active()->whereNotNull('publication')->selectRaw('publication, COUNT(*) as total')->groupBy('publication')->get() as $row) {
                $name = MediaArticle::normalizePublication($row->publication);
                if ($name !== '') {
                    $counts[$name] = ($counts[$name] ?? 0) + (int) $row->total;
                }
            }
            arsort($counts);

            return $counts;
        });
    }
}
