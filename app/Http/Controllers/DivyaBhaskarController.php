<?php

namespace App\Http\Controllers;

use App\Models\DivyaBhaskarArticle;
use Illuminate\Http\Request;

class DivyaBhaskarController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->query('q', ''));
        $year = (int) $request->query('year', 0);

        $articles = DivyaBhaskarArticle::active()
            ->when($year > 0, fn ($q) => $q->whereYear('published_on', $year))
            ->when($search !== '', function ($q) use ($search) {
                // "21.02.2026" searches by date, anything else by headline
                if (preg_match('/^(\d{1,2})[.\-\/](\d{1,2})[.\-\/](\d{4})$/', $search, $m) && checkdate((int) $m[2], (int) $m[1], (int) $m[3])) {
                    $q->where('published_on', sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1])); // DATE column: plain = uses the index
                } else {
                    $q->where('headline', 'like', '%' . addcslashes($search, '%_\\') . '%');
                }
            })
            ->orderByDesc('published_on')->orderByDesc('id')
            ->paginate(20)->withQueryString();

        $years = DivyaBhaskarArticle::active()
            ->selectRaw('YEAR(published_on) y, COUNT(*) c')->groupBy('y')->orderByDesc('y')
            ->pluck('c', 'y')->all();

        return view('divya-bhaskar.index', compact('articles', 'search', 'year', 'years'));
    }
}
