<?php

namespace App\Http\Controllers;

use App\Models\DigitalNewspaper;
use App\Services\BloggerStats;
use Illuminate\Http\Request;

/** E-Paper -> Digital News Paper: the weekly editions (Admin -> Digital News Paper). */
class DigitalNewspaperController extends Controller
{
    public function index(Request $request, BloggerStats $stats)
    {
        $pageviews = $stats->get(config('epaper.weekly_blog'));
        $search = trim((string) $request->query('q', ''));
        $year = (int) $request->query('year', 0);

        $papers = DigitalNewspaper::active()->edition('weekly')
            ->when($year > 0, fn ($q) => $q->whereYear('published_on', $year))
            ->when($search !== '', fn ($q) => self::search($q, $search))
            ->latestFirst()
            ->paginate((int) config('epaper.per_page', 24))->withQueryString();

        // "Blog Archive": editions per year, newest year first (counted in PHP: works on any database)
        $years = DigitalNewspaper::active()->edition('weekly')->pluck('published_on')
            ->countBy(fn ($date) => (int) $date->format('Y'))
            ->sortKeysDesc();

        return view('digital-newspaper.index', compact('papers', 'search', 'year', 'years', 'pageviews'));
    }

    public function show(BloggerStats $stats, int $id, ?string $slug = null)
    {
        $pageviews = $stats->get(config('epaper.weekly_blog'));
        $paper = DigitalNewspaper::active()->edition('weekly')->findOrFail($id);
        [$newer, $older] = self::neighbours($paper);

        return view('digital-newspaper.show', compact('paper', 'newer', 'older', 'pageviews'));
    }

    /** The next newer and older edition of the same paper. @return array{0: ?DigitalNewspaper, 1: ?DigitalNewspaper} */
    public static function neighbours(DigitalNewspaper $paper): array
    {
        $same = fn () => DigitalNewspaper::active()->edition($paper->edition);

        return [
            $same()->where(fn ($q) => $q->where('published_on', '>', $paper->published_on)
                ->orWhere(fn ($q) => $q->where('published_on', $paper->published_on)->where('id', '>', $paper->id)))
                ->orderBy('published_on')->orderBy('id')->first(),
            $same()->where(fn ($q) => $q->where('published_on', '<', $paper->published_on)
                ->orWhere(fn ($q) => $q->where('published_on', $paper->published_on)->where('id', '<', $paper->id)))
                ->latestFirst()->first(),
        ];
    }

    /** Title words, or a date like 18.08.2026 / 18-08-2026 that falls inside an edition's week (or is its date). */
    public static function search($query, string $search)
    {
        if (preg_match('/^(\d{1,2})[.\-\/](\d{1,2})[.\-\/](\d{4})$/', $search, $m) && checkdate((int) $m[2], (int) $m[1], (int) $m[3])) {
            $date = sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]);

            return $query->where(fn ($q) => $q->whereDate('published_on', $date)
                ->orWhere(fn ($q) => $q->where('week_from', '<=', $date)->where('week_to', '>=', $date)));
        }

        return $query->where('title', 'like', '%' . addcslashes($search, '%_\\') . '%');
    }
}
