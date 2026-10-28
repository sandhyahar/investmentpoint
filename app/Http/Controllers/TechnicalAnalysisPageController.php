<?php

namespace App\Http\Controllers;

use App\Models\MarketIndex;
use App\Models\TechnicalAnalysis;
use Illuminate\Http\Request;

class TechnicalAnalysisPageController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->query('q', ''));

        $analyses = TechnicalAnalysis::where('status', 'active')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('title', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhere('content', 'like', "%{$search}%");
                });
            })
            ->newest()
            ->paginate(12)
            ->withQueryString();

        return view('analysis.index', [
            'analyses' => $analyses,
            'search' => $search,
            'latest' => $this->latest(),
            'tickerIndices' => $this->tickerIndices(),
        ]);
    }

    public function show(TechnicalAnalysis $analysis, ?string $slug = null)
    {
        abort_unless($analysis->status === 'active', 404);

        return view('analysis.show', [
            'analysis' => $analysis,
            'latest' => $this->latest($analysis->getKey()),
            'tickerIndices' => $this->tickerIndices(),
        ]);
    }

    private function latest(?int $exceptId = null)
    {
        return TechnicalAnalysis::where('status', 'active')
            ->when($exceptId, fn ($query) => $query->whereKeyNot($exceptId))
            ->newest()
            ->take(5)
            ->get();
    }

    private function tickerIndices()
    {
        $tickerNames = ['NIFTY 50', 'SENSEX', 'BANK NIFTY', 'INDIA VIX'];

        return MarketIndex::whereIn('name', $tickerNames)->get()->sortBy(function ($index) use ($tickerNames) {
            return array_search($index->name, $tickerNames, true);
        })->values();
    }
}
