<?php

namespace App\Http\Controllers;

use App\Models\DigitalNewspaper;
use Illuminate\Http\Request;

/**
 * E-Paper -> Gujarati / Hindi / English: the daily editions, managed in Admin -> E-Paper (uploaded as a PDF,
 * or imported from the language's blogspot by newspaper:import). Same table as the Weekly News Paper.
 */
class EpaperController extends Controller
{
    public function index(Request $request, string $language)
    {
        $edition = $this->language($language);
        $search = trim((string) $request->query('q', ''));

        $papers = DigitalNewspaper::active()->edition($language)
            ->when($search !== '', fn ($q) => DigitalNewspaperController::search($q, $search))
            ->latestFirst()
            ->paginate((int) config('epaper.per_page', 24))->withQueryString();

        return view('epaper.index', compact('language', 'edition', 'papers', 'search'));
    }

    public function show(string $language, string $id)
    {
        $edition = $this->language($language);
        $paper = DigitalNewspaper::active()->edition($language)->find($id);

        // Old links used the blogspot post id (…/e-paper/gujarati/1234567890123456789): send them to the edition
        if (!$paper && ($old = DigitalNewspaper::active()->edition($language)->where('blogger_id', $id)->first())) {
            return redirect($old->url, 301);
        }
        abort_unless($paper, 404);

        [$newer, $older] = DigitalNewspaperController::neighbours($paper);

        return view('epaper.show', compact('language', 'edition', 'paper', 'newer', 'older'));
    }

    private function language(string $language): array
    {
        return config('epaper.editions.' . $language) ?? abort(404);
    }
}
