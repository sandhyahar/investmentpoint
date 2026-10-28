<?php

namespace App\Services;

use App\Models\News;
use App\Support\ContentVersion;
use App\Support\Html;
use App\Support\ImageOptimizer;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Every trading day at 11:11 AM and 2:02 PM (the session so far) and 5:00 PM (after the close) (php artisan
 * nifty:daily-update --session=open|midday|close): a NEW Market Trend article (Nifty Derivative Market Trend
 * Analysis) is written from that day's real market figures (App\Services\NiftyDayData) in the owner's fixed format,
 * with a fresh AI picture (bull and bear, the day's winner bigger). It takes over the short link
 * investmentpoint.in/nifty and the homepage hero from the previous one; the earlier articles stay as they were.
 * Anything missing or doubtful (no market data, a holiday, an AI reply with a figure that is not in the data)
 * publishes nothing.
 */
class NiftyDailyUpdater
{
    public function __construct(private NewsWriter $writer, private NiftyDayData $data, private AiPictures $pictures)
    {
    }

    /** The latest Nifty article (the new one copies its short link and hero): the newest with the "nifty" short link, else the newest Nifty trend article */
    public function article(): ?News
    {
        $name = config('news.nifty_daily.share_name');

        return News::where('share_name', $name)->latest('id')->first()
            ?? News::where('title', 'like', NewsWriter::NIFTY_TITLE . '%')->latest('id')->first();
    }

    /**
     * @return array{updated: bool, message: string, news?: News, draft?: array}
     * @param  bool  $force  run on a day without a Nifty session too (uses the last session)
     * @param  bool  $dryRun  write and check everything, save nothing
     * @param  string  $session  open = 11:11 AM, midday = 2:02 PM (the session so far; today's levels from the previous
     *                           session), close = 5:00 PM (the day's close; next session's levels), evening = like close
     */
    public function run(bool $force = false, bool $dryRun = false, string $session = 'close'): array
    {
        if (!config('news.nifty_daily.enabled') && !$force) {
            return ['updated' => false, 'message' => 'The daily Nifty update is switched off (NIFTY_DAILY_UPDATE=false).'];
        }
        $news = $this->article();
        if (!$news) {
            return ['updated' => false, 'message' => 'No Market Trend article with the short link "' . config('news.nifty_daily.share_name') . '" was found.'];
        }

        $facts = $this->data->latest();
        if (!$facts) {
            Log::warning('Nifty daily update skipped: no market data');

            return ['updated' => false, 'message' => 'The Nifty market figures could not be fetched, so the article was not changed.'];
        }
        $facts['session'] = in_array($session, ['open', 'midday', 'close', 'evening'], true) ? $session : 'close';
        if (NewsWriter::isIntraday($facts)) {
            $facts['levels'] = $facts['levels_today']; // the market is still trading: today's levels
        }
        if (!$facts['date']->isSameDay(now('Asia/Kolkata')) && !$force) {
            return ['updated' => false, 'message' => 'No Nifty trading session today (last session: ' . $facts['date']->format('j F Y') . '), so the article was not changed.'];
        }

        // The owner's full market-trend structure, written only from the real figures. When the AI is only busy
        // ("high demand", rate limit, no answer) it is asked again a little later, up to 3 times in all.
        $tries = max(1, (int) config('news.nifty_daily.tries', 3));
        for ($try = 1; ; $try++) {
            try {
                $draft = $this->writer->marketTrendAuto($facts);
                break;
            } catch (RuntimeException $e) {
                $busy = (bool) preg_match('/high demand|try again|limit reached|could not reach|overloaded|unavailable/i', $e->getMessage());
                if ($busy && $try < $tries) {
                    Log::info('Nifty daily update: the AI is busy, trying again (' . $try . ')');
                    sleep((int) config('news.nifty_daily.retry_seconds', 20));
                    continue;
                }
                Log::warning('Nifty daily update skipped: ' . $e->getMessage());

                return ['updated' => false, 'message' => $e->getMessage() . ' The article was not changed.'];
            }
        }

        $picture = $this->picture($facts);
        if ($dryRun) {
            return ['updated' => false, 'message' => 'Dry run: nothing saved.' . ($picture ? '' : ' (no new picture)'), 'news' => $news, 'draft' => $draft + ['facts' => NewsWriter::niftyFacts($facts)]];
        }

        // Full width and height (16:9, filled), not a square picture framed by blurred sides (owner, 23 Oct 2026)
        $base = 'news/nifty-' . now('Asia/Kolkata')->format('Ymd-His');
        $image = $picture ? ImageOptimizer::putFilled($picture[0], $picture[1], $base) : null;
        if (!$image && $news->image && !str_starts_with($news->image, 'http') && Storage::disk('public')->exists($news->image)) {
            // Picture services busy: a copy of the last picture (each article owns its file; deleting one keeps the other's)
            $image = $base . '.' . pathinfo($news->image, PATHINFO_EXTENSION);
            Storage::disk('public')->copy($news->image, $image);
        }

        // Every update is a NEW article (owner, 23 Oct 2026); the earlier ones stay as they are. The new one takes the
        // short link (investmentpoint.in/nifty opens the newest article with it) and the homepage hero.
        $new = new News();
        $new->forceFill([
            'title' => $draft['title'],
            // Every paragraph is justified by the writer (NewsWriter::justified), as the owner sets articles by hand
            'content' => $draft['content'],
            'description' => Html::summary(null, $draft['content']) ?: $news->description,
            'image' => $image ?? $news->image,
            'share_name' => $news->share_name,
            'status' => 'active',
            'is_featured' => $news->is_featured,
        ])->save();
        if ($news->is_featured) {
            News::whereKeyNot($new->id)->where('is_featured', true)->update(['is_featured' => false]);
        }
        ContentVersion::bump();
        Log::warning('Nifty daily update published', ['session' => $facts['session'], 'news' => $new->id, 'previous' => $news->id, 'title' => $new->title, 'provider' => $draft['provider'], 'picture' => (bool) $picture]);

        return ['updated' => true, 'message' => 'Published "' . $new->title . '" as a new article' . ($picture ? ' with a new picture.' : ' (the picture services were busy, so it has a copy of the last picture).'), 'news' => $new, 'draft' => $draft];
    }

    /**
     * The picture prompt for the Nifty article and the Market Trend "Generate with AI" button (Admin\AiImageController),
     * like the owner's example (26 Oct 2026, replacing the cartoon-like low-poly look): a premium, real-looking scene (the
     * NSE building, trees, sky and city look real), made by the premium model (AiPictures, FLUX.2). Today's side fills the
     * picture: a charging bull with a real bull's
     * body, a rising green arrow and climbing candles when the Nifty is up or unchanged; a roaring bear, a falling red
     * arrow and dropping candles when it is down. The NSE building stands in the left foreground, tiny city buildings
     * line the bottom, and the sky is a little creative.
     */
    public static function pictureScene(array $facts): string
    {
        $up = $facts['nifty']['change_pct'] >= 0;
        // Like the owner's example: a real-looking scene (building, trees, sky, city) with the animal as the one bold,
        // stylised element. A real animal's build (a fighting bull, not a cow), so it does not come out as a cartoon.
        $animal = $up
            ? 'a powerful muscular fighting bull with true bull anatomy, massive shoulder hump, thick neck and long sharp curved horns, '
                . 'leaping forward to the right in mid-air with dust and small rocks flying off its hooves; its whole hide is mottled emerald green and dark forest green, like green camouflage'
            : 'a huge grizzly bear with true bear anatomy, broad shoulder hump and big clawed paws, rearing up and roaring, '
                . 'with dust and small rocks flying around it; its thick fur is deep crimson red';
        // Only ONE arrow and one set of candles: picture models mix up two arrows. The falling wording is the one the owner
        // liked (26 Oct 2026); "zig-zagging DOWN" and "pointing DOWN" were drawn pointing up
        $trend = $up
            ? 'Behind it on the right, a soaring stock chart: one big glossy 3D green arrow that starts low near the ground and shoots upward, its arrowhead pointing at the sky near the top right corner, with green candlestick bars getting taller beside it.'
            : 'Behind it on the right, a crashing stock chart: one big glossy 3D red arrow that starts high at the top and plunges downward, its arrowhead pointing at the ground near the bottom right corner, with red candlestick bars getting shorter beside it.';
        $sky = $up
            ? 'Bright blue sky with real white clouds and golden sunlight rays.'
            : 'Dark stormy sky with real grey clouds and a red evening glow.';

        // Framing: the model drew the bear's head touching the top edge (27 Oct 2026); the whole animal is kept inside
        return 'Hyper-realistic cinematic financial news image, stock market, wide 16:9, medium-wide shot. '
            . "In the centre {$animal}. The whole animal is fully inside the frame from the top of its head to its feet, "
            . 'with clear sky above its head and space on every side: nothing is cut off at any edge. '
            . "{$trend} "
            . 'Left foreground: the real modern National Stock Exchange of India building, glass and white panels, with a blue sign showing only the letters NSE, '
            . 'an Indian flag, green trees and a paved plaza in front. '
            . 'Bottom right: a realistic city skyline of small glass skyscrapers. '
            . "{$sky} "
            . 'Photorealistic buildings, trees and sky, vivid saturated colours, light splashes of paint around the animal only, sharp focus, high detail, '
            . 'professional premium magazine cover quality, no other text.';
    }

    private function picture(array $facts): ?array
    {
        $scene = self::pictureScene($facts);

        try {
            // An illustration, not the realistic photo style of the news pictures (NewsWriter::photoStyle)
            return $this->pictures->make($scene, 1280, 720, false, premium: true);
        } catch (RuntimeException $e) {
            Log::warning('Nifty daily picture failed: ' . $e->getMessage());

            return null;
        }
    }
}
