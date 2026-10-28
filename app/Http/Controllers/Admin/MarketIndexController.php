<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MarketIndex;
use App\Services\MarketDataService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class MarketIndexController extends Controller
{
    public function index(MarketDataService $market)
    {
        $indices = MarketIndex::orderBy('id')->paginate(20);
        $tickerIds = MarketIndex::tickers()->pluck('id')->all();
        $live = $market->cachedSnapshot()['indices'] ?? [];

        return view('admin.market-indices.index', compact('indices', 'tickerIds', 'live'));
    }

    public function create()
    {
        return view('admin.market-indices.create');
    }

    public function store(Request $request, MarketDataService $market)
    {
        MarketIndex::create($this->validated($request, $market));

        return redirect()->route('admin.market-indices.index')->with('success', 'Market index created successfully.');
    }

    public function edit(MarketIndex $marketIndex)
    {
        return view('admin.market-indices.edit', compact('marketIndex'));
    }

    public function update(Request $request, MarketIndex $marketIndex, MarketDataService $market)
    {
        $marketIndex->update($this->validated($request, $market, $marketIndex));

        return redirect()->route('admin.market-indices.index')->with('success', 'Market index updated successfully.');
    }

    public function destroy(MarketIndex $marketIndex)
    {
        $marketIndex->delete();

        return redirect()->route('admin.market-indices.index')->with('success', 'Market index deleted successfully.');
    }

    public function bulkDestroy(Request $request)
    {
        $validated = $request->validate(['ids' => 'required|array', 'ids.*' => 'integer']);
        $deleted = MarketIndex::whereIn('id', $validated['ids'])->delete();
        MarketDataService::flush(); // query delete skips model events
        return redirect()->route('admin.market-indices.index')->with('success', $deleted . ' market index(es) deleted successfully.');
    }

    /**
     * Name is required; the Yahoo symbol is looked up from it unless the admin typed one.
     * The symbol must return a live price, and that price is stored as the fallback value.
     */
    private function validated(Request $request, MarketDataService $market, ?MarketIndex $existing = null): array
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'symbol' => 'nullable|string|max:40',
        ]);

        $symbol = strtoupper(trim($data['symbol'] ?? ''));
        // Renamed but the symbol box still holds the old symbol -> look up the new name
        if ($existing && $symbol === $existing->symbol && $data['name'] !== $existing->name) {
            $symbol = '';
        }
        $symbol = $symbol ?: $market->resolveSymbol($data['name']);

        if (!$symbol) {
            throw ValidationException::withMessages([
                'symbol' => 'Could not find a stock or index called "' . $data['name'] . '". Enter its Yahoo symbol, e.g. MRF.NS or ^NSEI.',
            ]);
        }

        $quote = $market->quote($symbol);
        if (!$quote) {
            throw ValidationException::withMessages([
                'symbol' => 'No live price found for ' . $symbol . '. Check the symbol (NSE stocks end in .NS, e.g. MRF.NS).',
            ]);
        }

        return [
            'name' => $data['name'],
            'symbol' => $symbol,
            'value' => $quote['price'],
            'change_percent' => abs($quote['change_percent']),
            'direction' => $quote['change_percent'] < 0 ? 'down' : 'up',
        ];
    }
}
