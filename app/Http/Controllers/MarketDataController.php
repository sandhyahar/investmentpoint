<?php

namespace App\Http\Controllers;

use App\Services\MarketDataService;
use Illuminate\Http\Request;

class MarketDataController extends Controller
{
    /**
     * Live snapshot for this website's own ticker (/market-data). Only answers the site's own pages:
     * browsers mark same-site requests (Sec-Fetch-Site), so other websites can't read it.
     */
    public function __invoke(Request $request, MarketDataService $market)
    {
        $site = $request->headers->get('Sec-Fetch-Site');
        if ($site !== null && !in_array($site, ['same-origin', 'same-site', 'none'], true)) {
            abort(403);
        }

        return response()->json(MarketDataService::withGainLoss($market->snapshot()))
            ->header('Cache-Control', 'no-store');
    }
}
