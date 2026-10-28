<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\NewsWriter;
use App\Services\Translator;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * "Auto-translate Gujarati / Hindi to English" on the article forms (admin.partials.auto-english):
 * the text of one box (title, or the editor's HTML) is translated with Google Translate and sent back
 * (the free AI is the backup when Google does not answer).
 */
class TranslationController extends Controller
{
    public function __invoke(Request $request, Translator $translator)
    {
        $data = $request->validate([
            'text' => 'required|string|max:60000',
            'html' => 'nullable|boolean',
        ]);

        if (!NewsWriter::hasIndic($data['text'])) {
            return response()->json(['text' => $data['text'], 'translated' => false]);
        }

        // A generous daily cap, only so a runaway script can't hammer the translation service
        $key = 'admin_ai_translations_' . now()->format('Y-m-d');
        $used = (int) cache($key, 0);
        if ($used >= 500) {
            return response()->json(['message' => 'Daily limit for translations reached. Please try again tomorrow.'], 429);
        }

        @set_time_limit(240); // a long article is several requests
        try {
            $english = $translator->toEnglish($data['text'], (bool) ($data['html'] ?? false));
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 502);
        }

        cache()->put($key, $used + 1, now()->endOfDay());

        return response()->json(['text' => $english, 'translated' => true]);
    }
}
