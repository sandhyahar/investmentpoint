<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\AiPictures;
use App\Services\NewsWriter;
use App\Services\StockPhotos;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * "Generate with AI" for admin image fields. Returns the image to the browser, which
 * crops it to the size the homepage needs and puts it in the form's normal file input,
 * so saving works exactly like an uploaded photo.
 *
 * Only free AI services make the pictures (Cloudflare, Pollinations, Hugging Face): see App\Services\AiPictures.
 */
class AiImageController extends Controller
{
    // Added to every prompt so images suit a financial news site
    private const STYLE = 'Professional, high quality editorial image for an Indian stock market and business news website. Clean composition, realistic lighting, no text, no letters, no watermark, no logos.';

    // Output size per shape used on the site (multiples of 32 for FLUX)
    private const SIZES = ['16:9' => [1024, 576], '16:10' => [1024, 640], '1:1' => [768, 768]];

    /** -1 for a headline about falling prices ("Nifty slips", "Sensex falls 400 points"), else +1 */
    public static function moodOf(string $headline): int
    {
        return preg_match('/\b(fall|falls|fell|falling|drop|drops|dropped|slip|slips|slipped|slide|slides|decline|declines|declined|down|lower|loss|losses|plunge|plunges|crash|crashes|tumble|tumbles|sink|sinks|weak|weaker|bear|bearish|sell-?off|red)\b/i', $headline) ? -1 : 1;
    }

    public function generate(Request $request, AiPictures $pictures)
    {
        $data = $request->validate([
            'prompt' => 'required|string|min:3|max:1000',
            'style' => 'nullable|boolean',
            'ratio' => 'nullable|in:16:9,16:10,1:1',
            'from_headline' => 'nullable|boolean',
            'look' => 'nullable|in:bull-bear',
        ]);

        $dailyKey = 'admin_ai_image_daily_' . now()->format('Y-m-d');
        $usedToday = (int) Cache::get($dailyKey, 0);
        $dailyLimit = Setting::aiImageDailyLimit();
        if ($usedToday >= $dailyLimit) {
            return response()->json([
                'message' => "Daily AI image limit reached ({$dailyLimit} images/day). Please try again tomorrow.",
            ], 429);
        }

        $prompt = trim($data['prompt']);
        if ($request->boolean('style', true)) {
            $prompt = match (true) {
                ($data['ratio'] ?? null) === '1:1' => $prompt . '. ' . self::STYLE, // round profile pictures
                // Market Trend (owner, 26 Oct 2026): the painted bull with the NSE building, like the Nifty article's
                // picture; a falling headline gets the bear instead
                ($data['look'] ?? null) === 'bull-bear' => \App\Services\NiftyDailyUpdater::pictureScene(['nifty' => ['change_pct' => self::moodOf($prompt)]]),
                // A news headline: turned into one real-looking photo scene (names would become fake logos / text)
                $request->boolean('from_headline') => NewsWriter::imagePrompt($prompt),
                // The admin's own description, shot as a realistic news photo
                default => NewsWriter::photoStyle($prompt),
            };
        }
        [$width, $height] = self::SIZES[$data['ratio'] ?? '16:9'];
        // Market Trend: the premium model, a bigger real 16:9 picture (NiftyDailyUpdater::pictureScene)
        $premium = ($data['look'] ?? null) === 'bull-bear' && $request->boolean('style', true);
        if ($premium) {
            [$width, $height] = [1280, 720];
        }

        try {
            [$bytes, $ext] = $pictures->make($prompt, $width, $height, premium: $premium);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 502);
        }
        $image = 'data:image/' . ($ext === 'jpg' ? 'jpeg' : $ext) . ';base64,' . base64_encode($bytes);

        Cache::put($dailyKey, $usedToday + 1, now()->endOfDay());
        return response()->json(['image' => $image, 'provider' => $pictures->provider]);
    }

    /**
     * "Find real photo": a real stock photo from Pixabay / Pexels, or public-domain Openverse with no key
     * (free, no daily AI allowance used).
     * Returned like an AI image, so the popup crops it and puts it in the form the same way.
     */
    public function photo(Request $request, StockPhotos $photos)
    {
        $data = $request->validate([
            'prompt' => 'required|string|min:2|max:300',
            'from_headline' => 'nullable|boolean',
        ]);

        if (!$photos->configured()) {
            return response()->json(['message' => 'Real photos are switched off: set OPENVERSE_ENABLED=true in .env (free, no key), or add a PIXABAY_API_KEY / PEXELS_API_KEY.'], 422);
        }

        // A headline becomes a topic search ("gold bars", "oil refinery"...); typed words are searched as they are
        $query = $request->boolean('from_headline') ? StockPhotos::queryFor($data['prompt']) : trim($data['prompt']);
        $photo = $photos->find($query);
        $file = $photo ? $photos->download($photo) : null;
        if (!$file) {
            return response()->json(['message' => 'No photo found for "' . $query . '". Try other words, e.g. "stock market", "gold bars", "bank building".'], 404);
        }
        [$bytes, $ext] = $file;

        return response()->json([
            'image' => 'data:image/' . ($ext === 'jpg' ? 'jpeg' : $ext) . ';base64,' . base64_encode($bytes),
            'provider' => $photos->provider(),
            'provider_name' => $photos->providerName(),
            'query' => $query,
            'credit' => $photo['credit'],
            'credit_url' => $photo['credit_url'],
        ]);
    }
}
