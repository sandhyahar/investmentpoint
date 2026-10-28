<?php

namespace Tests\Feature;

use App\Models\News;
use App\Models\NewsInboxItem;
use App\Services\NewsWriter;
use App\Services\NiftyDayData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** The 5 PM rewrite of the Nifty Derivative Market Trend Analysis from the day's real market figures */
class NiftyDailyUpdateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        self::geminiOn();
        self::cloudflareOn();
        // Thursday 22 Oct 2026, 5 PM India time
        Carbon::setTestNow(Carbon::parse('2026-10-22 17:00', 'Asia/Kolkata'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** A Yahoo daily chart: yesterday (close $prev) and today */
    private static function chart(float $prev, float $open, float $high, float $low, float $close, string $day = '2026-10-22')
    {
        $t = fn ($d) => Carbon::parse($d . ' 09:15', 'Asia/Kolkata')->timestamp;

        return Http::response(['chart' => ['result' => [[
            'meta' => ['exchangeTimezoneName' => 'Asia/Kolkata'],
            'timestamp' => [$t(Carbon::parse($day)->subWeekday()->toDateString()), $t($day)],
            'indicators' => ['quote' => [['open' => [$prev, $open], 'high' => [$prev, $high], 'low' => [$prev, $low], 'close' => [$prev, $close]]]],
        ]]]]);
    }

    /** $aiReply: the AI's text, or a ready response (e.g. Http::sequence()) */
    private function fakeMarket($aiReply, string $day = '2026-10-22'): void
    {
        Http::fake([
            '*/chart/%5ENSEI*' => self::chart(22700, 22687.75, 22697.55, 22554.85, 22650.25, $day),
            '*/chart/%5ENSEBANK*' => self::chart(54500, 54734, 54776, 54459, 54620.8, $day),
            '*/chart/%5ECNXMETAL*' => self::chart(12070, 12000, 12010, 11600, 11661.45, $day),
            '*/chart/%5EINDIAVIX*' => self::chart(15.5, 15.68, 16.67, 14.32, 16.24, $day),
            '*/chart/%5EBSESN*' => self::chart(73850, 73700, 73900, 73000, 73185.92, $day),
            '*/chart/BZ%3DF*' => self::chart(108.8, 109, 112, 108, 111.71, $day),
            '*/chart/*' => Http::response(['chart' => ['result' => null]], 404),
            'www.nseindia.com/*' => Http::response([['category' => 'DII', 'date' => '21-Oct-2026', 'netValue' => '1614.32'], ['category' => 'FII/FPI', 'date' => '21-Oct-2026', 'netValue' => '-1022.32']]),
            'generativelanguage.googleapis.com/*' => is_string($aiReply) ? self::geminiSays($aiReply) : $aiReply,
            'api.cloudflare.com/*' => self::cloudflarePicture(self::squareJpeg()), // a square picture, like FLUX's
        ]);
    }

    /** A reply in the owner's full market-trend structure, using only figures from the FACTS of fakeMarket() */
    private const GOOD = "HEADLINE: Banking Holds Firm While Metals Drag The Market Lower\n"
        . "INTRO: The Nifty 50 closed at 22650.25 on Thursday, down 49.75 points or 0.22%, while the Sensex ended at 73185.92 and the Bank Nifty held at 54620.8. Sentiment stayed cautious as traders weighed global cues and sector rotation through the session.\n"
        . "GLOBAL: Asian markets were weak, which may have weighed on sentiment at home, while cues from Wall Street and Europe were mixed.\n"
        . "FII: Foreign institutional investors were net sellers of ₹1022 crore in the cash market in the latest NSE data, which could keep liquidity tight for large caps.\n"
        . "DII: Domestic institutional investors bought ₹1614 crore, generally cushioning the fall.\n"
        . "MACRO: Brent crude at 111.71 may keep input costs in focus for oil importers, while currency moves could influence foreign flows. Central bank commentary may remain a background factor.\n"
        . "GAINERS: Banking : The Banking index gained 0.22% as select lenders saw buying interest | IT : the IT pack was steady\n"
        . "LOSERS: Metal : The Metal index fell 3.38% as commodity worries weighed on the group\n"
        . "BREADTH: The India VIX rose to 16.24, which may indicate rising caution among traders.\n"
        . "DOWNSIDE: Immediate support : 22630 and 22570 | Breakdown zone : a sustained move below 22570 may indicate weakening momentum\n"
        . "UPSIDE: Immediate resistance : 22710 and 22780 | Breakout zone : a sustained move above 22780 may indicate improving momentum\n"
        . "TRIGGERS: Global cues : overnight moves on Wall Street | Institutional flows : FII and DII activity\n"
        . "OUTLOOK: The Nifty may stay range bound in the next session as traders watch global cues and institutional flows with caution.";

    /** The manual "Write with AI" button's reply format (Nifty report with [ ] blanks) */
    private const MANUAL = "HEADLINE: Banking Holds Firm While Metals Slide\n"
        . "INTRO: The Nifty 50 closed at [Nifty close] on Thursday. The session stayed within a narrow range.\n"
        . "GLOBAL: Asian markets were weak.\nFII: Foreign institutional investors were net sellers.\nDII: Domestic institutional investors bought.\n"
        . "SECTOR1: Banking : The Banking index gained.\nSECTOR2: Metal : The Metal index was the weakest.\n"
        . "OUTLOOK: For the next session the Nifty may find support near [Level] and resistance near [Level].";

    private static function squareJpeg(): string
    {
        $im = imagecreatetruecolor(512, 512);
        imagefill($im, 0, 0, imagecolorallocate($im, 255, 255, 255));
        imagefilledrectangle($im, 100, 200, 400, 320, imagecolorallocate($im, 200, 30, 30));
        ob_start();
        imagejpeg($im, null, 85);

        return (string) ob_get_clean();
    }

    private function article(): News
    {
        return News::create(['title' => 'Nifty Derivative Market Trend Analysis : Understanding Market Momentum', 'share_name' => 'nifty',
            'content' => '<p>Old report</p>', 'description' => 'Old report', 'image' => 'news/old.jpg', 'status' => 'active']);
    }

    public function test_pivot_levels_are_the_standard_floor_pivots(): void
    {
        // P = (22697.55 + 22554.85 + 22650.25) / 3 = 22634.22
        $pivots = NiftyDayData::pivotLevels(22697.55, 22554.85, 22650.25);
        $this->assertSame(['pivot' => 22630, 's1' => 22570, 's2' => 22490, 's3' => 22430, 'r1' => 22710, 'r2' => 22780, 'r3' => 22860], $pivots);
        // The article: the two nearest levels below the price are support, the two nearest above are resistance
        $this->assertSame(['s1' => 22630, 's2' => 22570, 'r1' => 22710, 'r2' => 22780], NiftyDayData::levelsAround(22650.25, $pivots));
        // After a big gap down the classic S1 / S2 are above the price: never called support then
        $this->assertSame(['s1' => 22430, 's2' => 22400, 'r1' => 22490, 'r2' => 22570], NiftyDayData::levelsAround(22450, $pivots));
    }

    public function test_each_update_is_a_new_article_in_the_full_structure_from_real_figures_with_a_new_picture(): void
    {
        $this->fakeMarket(self::GOOD);
        NewsInboxItem::create(['source' => 'Mint', 'url' => 'https://mint.example/rbi', 'url_hash' => sha1('rbi'), 'title' => 'RBI keeps the repo rate unchanged at its policy meeting',
            'published_at' => now()->subHours(3), 'score' => 5, 'coverage' => 3]);
        Storage::disk('public')->put('news/old.jpg', 'old');
        $old = $this->article();
        $old->forceFill(['created_at' => now()->subDays(5), 'is_featured' => true])->save();

        $this->artisan('nifty:daily-update')->assertSuccessful();

        // A NEW article (owner, 23 Oct 2026): the earlier one stays exactly as it was, with its picture
        $news = News::latest('id')->firstOrFail();
        $this->assertNotSame($old->id, $news->id);
        $this->assertSame(['<p>Old report</p>', 'news/old.jpg', false], [$old->fresh()->content, $old->fresh()->image, $old->fresh()->is_featured]);
        Storage::disk('public')->assertExists('news/old.jpg');
        // The new one takes the short link investmentpoint.in/nifty and the homepage hero
        $this->assertSame(['nifty', true, 'active'], [$news->share_name, $news->is_featured, $news->status]);
        $this->get('/nifty')->assertRedirect($news->url);
        $this->assertSame('Nifty Derivative Market Trend Analysis : Banking Holds Firm While Metals Drag The Market Lower', $news->title);
        foreach (['closed at 22650.25', 'Market Behaviour &amp; Activity :', 'A. Global Market Influence :', 'B. FIIs Activity :', 'C. DIIs Activity :',
            'Macroeconomic, Energy &amp; Currency Drivers :', 'Sector Wise Performance :', 'Top Gainers', '<li><strong>Banking :</strong> The Banking index gained 0.22%',
            'Top Losers', 'Market Sentiment &amp; Technical Analysis :', 'Market Breadth &amp; Volatility :', 'A. Downside Levels :', '<li><strong>Immediate support :</strong> 22630 and 22570</li>',
            'B. Upside Levels :', 'Next Session Outlook &amp; Key Triggers :', 'closed at 22650.25, down 49.75 points', 'No Buy / Sell Fully Recommendation.'] as $part) {
            $this->assertStringContainsString($part, $news->content);
        }
        // No date, day or time anywhere in the article (owner, 23 Oct 2026): "on Thursday" in the AI reply is taken out
        foreach (['Report Date', 'Updated', 'Thursday', 'October', '2026'] as $part) {
            $this->assertStringNotContainsString($part, $news->content);
        }
        $this->assertStringNotContainsString('[', $news->content);
        // Every paragraph justified, as the owner sets it in the editor (27 Oct 2026)
        $this->assertStringContainsString('<p style="text-align: justify;">', $news->content);
        $this->assertStringNotContainsString('<p>', $news->content);
        $this->assertTrue($news->created_at->isToday());
        $this->assertStringStartsWith('news/nifty-', $news->image);
        Storage::disk('public')->assertExists($news->image);
        // Full 16:9 width and height, no blurred side bands (the AI picture is square)
        [$w, $h] = getimagesize(Storage::disk('public')->path($news->image));
        $this->assertEqualsWithDelta(16 / 9, $w / $h, 0.02);

        // The AI got the real figures and the real headlines, and the owner's full structure
        Http::assertSent(function (Request $r) {
            $prompt = json_encode($r->data(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            return str_contains($r->url(), 'generativelanguage') && str_contains($prompt, 'Nifty 50 close: 22650.25, change: -49.75 points (-0.22%)')
                && str_contains($prompt, 'Banking +0.22%; Metal -3.38%') && str_contains($prompt, 'FIIs net sold ₹1022 crore') && !str_contains($prompt, '21 October') && !str_contains($prompt, '22 October') && str_contains($prompt, 'Never mention a date')
                && str_contains($prompt, 'Sensex: 73185.92') && str_contains($prompt, 'Bank Nifty: 54620.8') && str_contains($prompt, 'Brent crude (US$ per barrel) 111.71')
                && str_contains($prompt, 'RBI keeps the repo rate unchanged at its policy meeting')
                && str_contains($prompt, 'catchy, SEO friendly headline') && str_contains($prompt, 'MACRO:') && str_contains($prompt, 'Never mention Gift Nifty')
                && str_contains($prompt, 'no price targets');
        });
    }

    public function test_an_ai_reply_with_invented_figures_is_never_published(): void
    {
        // 23000 is not in the market data, also not after the corrective retry
        $this->fakeMarket(str_replace('22710 and 22780', '22710 and 23000', self::GOOD));
        $news = $this->article();

        $this->artisan('nifty:daily-update')->expectsOutputToContain('not in the market data')->assertSuccessful();

        $this->assertSame('<p>Old report</p>', $news->fresh()->content);
        $this->assertSame('news/old.jpg', $news->fresh()->image);
    }

    public function test_the_facts_say_which_way_the_rupee_moved_and_where_the_averages_are(): void
    {
        $day = Carbon::parse('2026-10-22');
        $facts = ['date' => $day, 'session' => 'close', 'vix' => null, 'sectors' => [], 'global' => [], 'fii_dii' => null,
            'nifty' => ['close' => 22685.25, 'change' => -203.7, 'change_pct' => -0.89, 'prev_close' => 22888.95, 'open' => 22687.75, 'high' => 22785, 'low' => 22554.85],
            'levels' => ['s1' => 22680, 's2' => 22570, 'r1' => 22800, 'r2' => 22910],
            'macro' => [['name' => 'Rupee (₹ per US$)', 'close' => 94.83, 'change_pct' => 0.13, 'date' => $day]],
            'moving_averages' => [20 => 22781.39, 50 => 22500.0]];

        $text = NewsWriter::niftyFacts($facts);
        $this->assertStringContainsString('Rupee (₹ per US$) 94.83 (+0.13%, the rupee weakened against the US dollar)', $text);
        $this->assertStringContainsString('20-day 22781.39 (above the Nifty, so overhead resistance)', $text);
        $this->assertStringContainsString('50-day 22500 (below the Nifty, so support)', $text);
    }

    public function test_dates_and_clock_times_are_taken_out_of_the_market_trend_text(): void
    {
        $this->assertSame('Indian equity markets displayed a cautious start as the Nifty 50 opened at 22742.2. The index traded at 22719.45, a gain of 0.14 percent.',
            NewsWriter::withoutDateTime('Indian equity markets displayed a cautious start on 23 October 2026 as the Nifty 50 opened at 22742.2. By 9:30 AM, the index traded at 22719.45, a gain of 0.14 percent.'));
        $this->assertSame('Traders may watch global cues in the session, and the index closed lower.',
            NewsWriter::withoutDateTime("Traders may watch global cues in Monday's session, and the index closed lower at 3:30 PM IST on Friday."));
        $this->assertSame('In May the Nifty may stay range bound near 22500.', NewsWriter::withoutDateTime('In May the Nifty may stay range bound near 22500.'));
    }

    public function test_a_busy_ai_is_asked_again(): void
    {
        config(['news.nifty_daily.retry_seconds' => 0]);
        // First answer: high demand; the second try works
        $this->fakeMarket(Http::sequence()
            ->push(['error' => ['code' => 503, 'message' => 'This model is currently experiencing high demand. Please try again later.', 'status' => 'UNAVAILABLE']], 503)
            ->push(['candidates' => [['content' => ['role' => 'model', 'parts' => [['text' => self::GOOD]]], 'finishReason' => 'STOP']]]));
        $news = $this->article();

        $this->artisan('nifty:daily-update')->assertSuccessful();
        $this->assertStringContainsString('closed at 22650.25', News::latest('id')->first()->content); // the new article
        $this->assertCount(2, Http::recorded(fn (Request $r) => str_contains($r->url(), 'generativelanguage')));
    }

    public function test_nothing_changes_on_a_day_without_a_nifty_session(): void
    {
        $this->fakeMarket(self::GOOD, '2026-10-21'); // last candle is yesterday: a market holiday today
        $news = $this->article();

        $this->artisan('nifty:daily-update')->expectsOutputToContain('No Nifty trading session today')->assertSuccessful();
        $this->assertSame('<p>Old report</p>', $news->fresh()->content);
        Http::assertNotSent(fn (Request $r) => str_contains($r->url(), 'generativelanguage'));
    }

    public function test_dry_run_and_switch_off_save_nothing(): void
    {
        $this->fakeMarket(self::GOOD);
        $news = $this->article();

        $this->artisan('nifty:daily-update', ['--dry-run' => true])->expectsOutputToContain('Dry run: nothing saved.')->assertSuccessful();
        $this->assertSame('<p>Old report</p>', $news->fresh()->content);

        config(['news.nifty_daily.enabled' => false]);
        $this->artisan('nifty:daily-update')->expectsOutputToContain('switched off')->assertSuccessful();
        $this->assertSame('<p>Old report</p>', $news->fresh()->content);
    }

    public function test_only_the_nifty_article_is_touched_and_runs_at_1111_202_and_5_on_weekdays(): void
    {
        $this->fakeMarket(self::GOOD);
        $other = News::create(['title' => 'Sensex weekly wrap', 'content' => '<p>Other</p>', 'description' => 'Other', 'status' => 'active']);
        $this->article();

        $this->artisan('nifty:daily-update')->assertSuccessful();
        $this->assertSame('<p>Other</p>', $other->fresh()->content);

        // The owner's times (23 Oct 2026)
        $events = collect(app(\Illuminate\Console\Scheduling\Schedule::class)->events())->filter(fn ($e) => str_contains($e->command, 'nifty:daily-update'));
        $this->assertSame([
            '--session="open"' => '11 11 * * 1-5',    // 11:11 AM, morning
            '--session="midday"' => '2 14 * * 1-5',   // 2:02 PM, afternoon
            '--session="close"' => '0 17 * * 1-5',    // 5:00 PM, after the close (final)
        ], $events->mapWithKeys(fn ($e) => [\Illuminate\Support\Str::after($e->command, 'nifty:daily-update ') => $e->expression])->all());
        $this->assertSame(['Asia/Kolkata'], $events->pluck('timezone')->unique()->values()->all());
    }

    public function test_the_picture_is_a_bull_when_the_nifty_is_up_and_a_bear_when_it_is_down(): void
    {
        // The owner's art style (26 Oct 2026): a premium real-looking scene, not low-poly; today's side fills it
        $facts = fn (float $pct, string $day = '2026-10-23') => ['date' => Carbon::parse($day), 'nifty' => ['change_pct' => $pct]];
        $up = \App\Services\NiftyDailyUpdater::pictureScene($facts(0.42));
        $down = \App\Services\NiftyDailyUpdater::pictureScene($facts(-0.05));
        foreach ([$up, $down] as $scene) {
            $this->assertStringContainsString('Hyper-realistic cinematic financial news image', $scene);
            $this->assertStringContainsString('Left foreground: the real modern National Stock Exchange of India building', $scene);
            $this->assertStringContainsString('showing only the letters NSE', $scene);
            $this->assertStringContainsString('realistic city skyline', $scene);
            $this->assertStringContainsString('16:9', $scene);
            $this->assertStringNotContainsString('low-poly', $scene);
            $this->assertSame(1, substr_count($scene, ' arrow ')); // one arrow only: two get mixed up
        }
        $this->assertStringContainsString('muscular fighting bull', $up);
        $this->assertStringContainsString('true bull anatomy', $up);
        $this->assertStringNotContainsString('bear', $up);
        $this->assertStringContainsString('green arrow that starts low near the ground and shoots upward, its arrowhead pointing at the sky', $up);
        $this->assertStringContainsString('true bear anatomy', $down);
        $this->assertStringNotContainsString('bull', $down);
        $this->assertStringContainsString('red arrow that starts high at the top and plunges downward, its arrowhead pointing at the ground', $down);
        $this->assertStringContainsString('arrowhead pointing at the sky', \App\Services\NiftyDailyUpdater::pictureScene($facts(0.0))); // unchanged = bull
        $this->assertStringNotContainsString('photograph', $up);

        // The update asks the picture service for it (GOOD: the Nifty is down 0.22%)
        $this->fakeMarket(self::GOOD);
        $this->article();
        $this->artisan('nifty:daily-update')->assertSuccessful();
        Http::assertSent(fn (Request $r) => str_contains($r->url(), 'api.cloudflare.com') && str_contains(json_encode($r->data()), 'true bear anatomy'));
    }

    /** 27 Oct 2026: FLUX.2 kept answering "Internal server error", and the square backup picture cut to 16:9 lost the bear's head */
    public function test_when_the_premium_model_is_down_it_waits_longer_and_the_square_backup_keeps_the_scene_in_the_middle(): void
    {
        config(['services.cloudflare_ai.account_id' => 'acc', 'services.cloudflare_ai.token' => 'tok']);
        \Illuminate\Support\Sleep::fake();
        Http::fake([
            '*flux-2*' => Http::response(['success' => false, 'errors' => [['message' => 'AiError: Internal server error']]], 500),
            'api.cloudflare.com/*' => Http::response(['result' => ['image' => base64_encode('square')], 'success' => true]),
        ]);

        $pictures = app(\App\Services\AiPictures::class);
        $this->assertSame(['square', 'jpg'], $pictures->make('A bear scene', 1280, 720, false, premium: true));
        $this->assertSame('cloudflare', $pictures->provider);
        Http::assertSentCount(5); // FLUX.2 4 times, ...
        \Illuminate\Support\Sleep::assertSlept(fn ($d) => $d->totalMilliseconds >= 3000, 3); // ... about half a minute in all
        Http::assertSent(fn (Request $r) => str_contains($r->url(), 'flux-1') && str_starts_with($r['prompt'], 'A bear scene ')
            && str_contains($r['prompt'], 'sits inside the middle half of the picture height'));

        // Other pictures (not Market Trend) keep their own prompt
        $pictures->make('A bank building', 1280, 720, false);
        Http::assertSent(fn (Request $r) => str_contains($r->url(), 'flux-1') && $r['prompt'] === 'A bank building');
    }

    public function test_the_202_update_is_the_afternoon_session_so_far(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-22 14:02', 'Asia/Kolkata'));
        $this->fakeMarket(str_replace(
            ['closed at 22650.25 on Thursday, down 49.75 points', 'Immediate support : 22630 and 22570', 'below 22570', 'Immediate resistance : 22710 and 22780', 'above 22780'],
            ['trades at 22650.25, down 49.75 points', 'Immediate support : 22600 and 22500', 'below 22500', 'Immediate resistance : 22700 and 22800', 'above 22800'],
            self::GOOD));
        $news = $this->article();

        $this->artisan('nifty:daily-update', ['--session' => 'midday'])->assertSuccessful();

        $this->assertStringContainsString('Session Outlook &amp; Key Triggers :', News::latest('id')->first()->content);
        $this->assertStringNotContainsString('Next Session Outlook', News::latest('id')->first()->content);
        Http::assertSent(fn (Request $r) => str_contains($r->url(), 'generativelanguage')
            && str_contains($json = json_encode($r->data(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 'Session: the afternoon session')
            && str_contains($json, 'Nifty 50 in afternoon trade: 22650.25') && str_contains($json, 'Write the afternoon session Nifty market trend article'));
    }

    public function test_the_1111_update_describes_the_morning_with_todays_levels_from_the_previous_session(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-22 11:11', 'Asia/Kolkata'));
        // Previous session: high = low = close = 22700, so every pivot is 22700: support = the round levels below the price
        $this->fakeMarket(str_replace(
            ['closed at 22650.25 on Thursday, down 49.75 points', 'Immediate support : 22630 and 22570', 'below 22570', 'Immediate resistance : 22710 and 22780', 'above 22780'],
            ['opened at 22687.75, a gap down of 12.25 points, and trades at 22650.25 at 9:30 AM, down 49.75 points', 'Immediate support : 22600 and 22500', 'below 22500', 'Immediate resistance : 22700 and 22800', 'above 22800'],
            self::GOOD));
        $news = $this->article();

        $this->artisan('nifty:daily-update', ['--session' => 'open'])->assertSuccessful();

        $news = News::latest('id')->first(); // the new article
        $this->assertStringContainsString('Session Outlook &amp; Key Triggers :', $news->content);
        $this->assertStringNotContainsString('Next Session Outlook', $news->content);
        $this->assertStringNotContainsString('9:30', $news->content);
        $this->assertStringContainsString('trades at 22650.25, down 49.75 points', $news->content);
        $this->assertStringContainsString('<li><strong>Immediate support :</strong> 22600 and 22500</li>', $news->content);
        Http::assertSent(fn (Request $r) => str_contains($r->url(), 'generativelanguage')
            && str_contains($json = json_encode($r->data(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 'Session: the morning session')
            && !str_contains($json, 'report at 9:30') && str_contains($json, 'Nifty 50 in morning trade: 22650.25') && str_contains($json, 'a gap down of 12.25 points')
            && str_contains($json, "today's session (pivot points of the previous session): 22600 and 22500; resistance levels: 22700 and 22800")
            && str_contains($json, 'sentiment so far today'));
    }

    public function test_the_manual_write_button_still_leaves_blanks_for_the_editor(): void
    {
        Http::fake(['generativelanguage.googleapis.com/*' => self::geminiSays(self::MANUAL)]);

        $draft = app(NewsWriter::class)->niftyTrend('Nifty Derivative Market Trend Analysis');
        $this->assertStringContainsString('[S Level 1]', $draft['content']);
        $this->assertStringContainsString('Fill in every [ ] placeholder', $draft['warnings'][0]);
    }
}
