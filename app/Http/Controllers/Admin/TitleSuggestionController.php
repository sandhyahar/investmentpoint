<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\NewsWriter;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * "Suggest SEO titles" under the Title box of the article forms (admin.partials.title-suggest):
 * five search-friendly titles from what the admin typed, written by the free AI.
 */
class TitleSuggestionController extends Controller
{
    public function __invoke(Request $request, NewsWriter $writer)
    {
        $data = $request->validate([
            'title' => 'required|string|min:2|max:255',
            'type' => 'required|in:' . implode(',', array_keys(NewsWriter::TITLE_TYPES)),
            'support' => 'nullable|string|max:50',
            'resistance' => 'nullable|string|max:50',
        ]);

        // A separate, generous daily allowance: one request is a small fraction of an article
        $key = 'admin_ai_titles_' . now()->format('Y-m-d');
        $used = (int) cache($key, 0);
        if ($used >= 200) {
            return response()->json(['message' => 'Daily limit for title suggestions reached. Please try again tomorrow.'], 429);
        }

        try {
            $result = $writer->titles($data['title'], $data['type'], $data['support'] ?? null, $data['resistance'] ?? null);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 502);
        }

        cache()->put($key, $used + 1, now()->endOfDay());

        return response()->json($result);
    }
}
