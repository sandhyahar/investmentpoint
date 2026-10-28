<?php

namespace Tests\Feature;

use App\Models\LatestNews;
use App\Models\NewsInboxItem;
use App\Models\User;
use App\Services\NewsCollector;
use App\Services\NewsWriter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class NewsInboxTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::forceCreate([
            'name' => 'Administrator', 'username' => 'admin-inbox', 'email' => 'admin-inbox@example.com',
            'role' => 'admin', 'password' => bcrypt('secret'),
        ]);
    }

    private static function feed(array $items): string
    {
        $date = now()->toRfc2822String();
        $xml = '';
        foreach ($items as [$title, $link]) {
            $xml .= "<item><title><![CDATA[{$title}]]></title><link>{$link}</link><description><![CDATA[About {$title}]]></description><pubDate>{$date}</pubDate></item>";
        }

        return '<?xml version="1.0"?><rss version="2.0"><channel><title>F</title>' . $xml . '</channel></rss>';
    }

    public function test_collector_keeps_business_stories_and_ranks_shared_ones_as_popular(): void
    {
        config(['news.inbox_feeds' => [
            ['source' => 'Economic Times', 'url' => 'https://et.example/rss'],
            ['source' => 'Mint', 'url' => 'https://mint.example/rss'],
        ]]);
        Http::swap(new Factory());
        Http::fake([
            'et.example/*' => Http::response(self::feed([
                ['Fortis Healthcare shares fall after Supreme Court allows forensic audit', 'https://et.example/a'],
                ['ITC Share Price Live Updates: ITC Stock Details', 'https://et.example/live'],
                ['Bollywood actor buys new car', 'https://et.example/b'],
            ])),
            'mint.example/*' => Http::response(self::feed([
                ['Supreme Court allows Fortis Healthcare forensic audit; shares in focus', 'https://mint.example/a'],
                ['Weather will be sunny tomorrow', 'https://mint.example/w'],
            ])),
        ]);

        $result = app(NewsCollector::class)->collect();

        $this->assertSame(2, $result['added']); // live-update page, celebrity story and non-business story skipped
        $this->assertSame([2, 2], NewsInboxItem::pluck('coverage')->all()); // same story at 2 publishers

        // Running again adds nothing new
        $this->assertSame(0, app(NewsCollector::class)->collect()['added']);
    }

    public function test_inbox_page_and_writing_an_article_from_a_story(): void
    {
        $story = NewsInboxItem::create([
            'source' => 'Economic Times', 'url' => 'https://et.example/story', 'url_hash' => sha1('https://et.example/story'),
            'title' => 'RBI keeps repo rate unchanged', 'summary' => 'The central bank held rates.', 'published_at' => now(), 'score' => 4, 'coverage' => 3,
        ]);
        $this->actingAs($this->admin());

        $this->get(route('admin.news-inbox.index'))->assertOk()->assertSee('RBI keeps repo rate unchanged')->assertSee('3 sources');
        $this->get(route('admin.news-inbox.index', ['tab' => 'popular']))->assertOk()->assertSee('RBI keeps repo rate unchanged');

        $this->get(route('admin.latest-news.create', ['from' => $story->id]))->assertOk()
            ->assertSee('Writing from · Economic Times')->assertSee('https://et.example/story', false);

        $this->post(route('admin.latest-news.store'), [
            'title' => 'RBI holds repo rate steady', 'description' => 'Our summary', 'content' => '<p>Our own words.</p>',
            'source' => 'Economic Times', 'source_url' => 'https://et.example/story', 'status' => 'active', 'inbox_id' => $story->id,
        ])->assertRedirect(route('admin.latest-news.index'));

        $this->assertSame('used', $story->fresh()->status);
        $this->get(route('admin.news-inbox.index'))->assertDontSee('RBI keeps repo rate unchanged');

        // Published as Investment Point's own report: the source is kept for the admin only
        $this->get(LatestNews::firstOrFail()->url)->assertOk()->assertSee('RBI holds repo rate steady')
            ->assertDontSee('https://et.example/story', false)->assertDontSee('Source:')->assertDontSee('📰 Economic Times');
    }

    public function test_write_with_ai_returns_an_original_draft_from_gemini(): void
    {
        config(['services.gemini_ai.key' => 'AIzaTestKey0123456789012345678901234567']);
        $article = str_repeat('The Reserve Bank of India kept its key lending rate unchanged at the policy meeting. ', 5);
        Http::swap(new Factory());
        Http::fake(['generativelanguage.googleapis.com/*' => self::geminiSays("TITLE: RBI holds rates steady as the central bank keeps its policy unchanged\nSUMMARY: The RBI kept its key rate unchanged.\nKEYWORDS: RBI repo rate, RBI policy, interest rates, bank stocks\nARTICLE:\n{$article}\n\nWhat it means for investors: borrowing costs stay the same.")]);

        $this->actingAs($this->admin())
            ->postJson(route('admin.news-writer'), ['headline' => 'RBI keeps repo rate unchanged', 'summary' => 'The central bank held rates.', 'source' => 'ET'])
            ->assertOk()
            ->assertJson(['title' => 'RBI holds rates steady as the central bank keeps its policy unchanged', 'description' => 'The RBI kept its key rate unchanged.',
                'keywords' => 'RBI repo rate, RBI policy, interest rates, bank stocks', 'provider' => 'gemini'])
            // AI paragraphs come justified (owner, 27 Oct 2026)
            ->assertJsonFragment(['content' => '<p style="text-align: justify;">' . trim($article) . "</p>\n<p style=\"text-align: justify;\">What it means for investors: borrowing costs stay the same.</p>"]);

        Http::assertSent(fn ($request) => str_contains($request->url(), '/models/gemini-3.1-flash-lite:generateContent')
            && $request->hasHeader('x-goog-api-key', 'AIzaTestKey0123456789012345678901234567')
            && str_contains($request['systemInstruction']['parts'][0]['text'], 'Never invent numbers'));
    }

    public function test_market_trend_nifty_title_is_written_in_the_fixed_nifty_format(): void
    {
        config(['services.gemini_ai.key' => 'AIzaTestKey0123456789012345678901234567']);
        Http::swap(new Factory());
        Http::fake(['generativelanguage.googleapis.com/*' => self::geminiSays("HEADLINE: Understanding Market Momentum and Key Levels\n"
            . "INTRO: The Nifty closed at [Nifty close] as traders watched \"open interest\" closely.\n"
            . "GLOBAL: Asian markets were mixed.\nFII: FIIs sold [₹ crore] in cash, about 1,250 crore.\nDII: DIIs were buyers.\n"
            . "SECTOR1: Banking : Bank stocks moved in a narrow range.\nSECTOR2: IT : Technology stocks were steady.\n"
            . "OUTLOOK: The Nifty may stay range bound between [Level] and [Level].")]);

        $this->actingAs($this->admin())
            ->postJson(route('admin.news-writer'), ['headline' => 'Nifty Derivative Market Trend Analysis', 'form' => 'market-trend'])
            ->assertOk()
            ->assertJson(['title' => 'Nifty Derivative Market Trend Analysis : Understanding Market Momentum and Key Levels'])
            ->assertJsonPath('content', fn ($html) => str_starts_with($html, '<p style="text-align: justify;">Dear Investors,</p>')
                && str_contains($html, 'as traders watched open interest closely') // no inverted commas
                && str_contains($html, '<strong>B. FIIs Activity :</strong> FIIs sold [₹ crore] in cash, about 1250 crore.') // no comma in amounts
                && str_contains($html, '<strong>Banking :</strong> Bank stocks')
                && str_contains($html, e(NewsWriter::NIFTY_SUPPORT)) && str_contains($html, e(NewsWriter::NIFTY_RESISTANCE))
                && str_contains($html, '<strong>Report Date :</strong> ' . now('Asia/Kolkata')->format('j F Y | l'))
                && str_ends_with($html, '<strong>Recommendation :</strong> Neutral Editorial Assessment – No Buy / Sell Fully Recommendation.</p>'))
            ->assertJsonPath('warnings.1', 'Check these figures - the AI has no live market data: 1250');

        // Headline News (or a non-Nifty title) keeps the normal news draft
        $this->assertFalse(NewsWriter::isNiftyTrend('Nifty Bank shares rise as RBI holds rates'));
        $this->assertTrue(NewsWriter::isNiftyTrend('NIFTY derivative market trend analysis : key levels'));
    }

    public function test_auto_publish_writes_articles_and_holds_invented_numbers(): void
    {
        \Illuminate\Support\Facades\Storage::fake('public');
        config(['services.gemini_ai.key' => 'AIzaTestKey0123456789012345678901234567']);
        $make = fn ($title, $summary, $coverage) => NewsInboxItem::create([
            'source' => 'Mint', 'url' => 'https://mint.example/' . md5($title), 'url_hash' => sha1('https://mint.example/' . md5($title)),
            'title' => $title, 'summary' => $summary, 'published_at' => now(), 'score' => 6, 'coverage' => $coverage,
        ]);
        $good = $make('Nalco shares fall 3.89% as metal stocks slide', 'Nalco fell 3.89%.', 1);
        $bad = $make('Tata Steel shares fall as metal stocks slide lower', 'Tata Steel fell.', 1);

        $body = fn ($extra) => str_repeat('Metal stocks traded lower on the exchanges today as investors turned cautious about the sector. ', 14) . $extra;
        Http::swap(new Factory());
        self::cloudflareOn();
        Http::fake(['api.cloudflare.com/*' => self::cloudflarePicture('jpeg-bytes')]);
        Http::fake(fn ($request) => self::geminiSays(str_contains(json_encode($request->data()), 'fact-checker')
            ? 'OK' // independent fact-check passes
            : (str_contains(json_encode($request->data()), 'Tata Steel')
            ? "TITLE: Tata Steel shares slide 12% as the metal index falls in trade\nSUMMARY: Tata Steel fell.\nARTICLE:\n" . $body('It fell 12%.')
            : "TITLE: Nalco shares fall 3.89% as metal stocks slide across the market\nSUMMARY: Nalco fell 3.89%.\nARTICLE:\n" . $body('Nalco fell 3.89%.'))));

        $r = app(\App\Services\NewsAutoPublisher::class)->run();

        $this->assertSame(1, $r['business'], json_encode($r));
        $this->assertSame(1, $r['held']); // "12%" is not in the Tata Steel story
        $article = LatestNews::firstOrFail();
        $this->assertSame('Nalco shares fall 3.89% as metal stocks slide across the market', $article->title);
        $this->assertSame($good->url, $article->source_url);
        $this->assertSame('used', $good->fresh()->status);
        $this->assertSame('new', $bad->fresh()->status); // left in the inbox for the admin
        \Illuminate\Support\Facades\Storage::disk('public')->assertExists($article->image);
    }

    /**
     * Published while no picture could be made (Cloudflare's free pictures used up, the others busy): it showed the same
     * stand-in picture as every other such article (owner, 27 Oct 2026). A later run gives it its own picture.
     */
    public function test_an_auto_article_without_a_picture_gets_one_on_a_later_run(): void
    {
        \Illuminate\Support\Facades\Storage::fake('public');
        config(['services.gemini_ai.key' => 'AIzaTestKey0123456789012345678901234567', 'services.huggingface_ai.space' => null, 'services.pollinations.enabled' => false]);
        $article = LatestNews::create(['title' => 'Power sector earnings outlook highlights NTPC and Tata Power', 'source_url' => 'https://mint.example/power', 'description' => 'Outlook for power companies.',
            'status' => 'active', 'published_at' => now()]);
        $admin = LatestNews::create(['title' => 'Added by the admin without a picture', 'description' => 'Admin story.', 'status' => 'active', 'published_at' => now()]);
        Http::swap(new Factory());
        self::cloudflareOn();
        Http::fake(['api.cloudflare.com/*' => self::cloudflarePicture('jpeg-bytes'), '*' => self::geminiSays('OK')]);

        app(\App\Services\NewsAutoPublisher::class)->run();

        $this->assertNotNull($article->fresh()->image);
        \Illuminate\Support\Facades\Storage::disk('public')->assertExists($article->fresh()->image);
        $this->assertNull($admin->fresh()->image); // only the auto-published ones: the admin's own articles are left as they are
    }

    public function test_fact_check_holds_unsupported_claims_and_hides_unchecked_older_articles(): void
    {
        \Illuminate\Support\Facades\Storage::fake('public');
        config(['services.gemini_ai.key' => 'AIzaTestKey0123456789012345678901234567', 'news.auto.images' => false]);
        $story = NewsInboxItem::create([
            'source' => 'Mint', 'url' => 'https://mint.example/sail', 'url_hash' => sha1('https://mint.example/sail'),
            'title' => 'SAIL shares fall as steel prices weaken', 'summary' => 'SAIL fell.', 'published_at' => now(), 'score' => 6, 'coverage' => 1,
        ]);
        // An older article written before the fact-check existed, from a known story
        $old = LatestNews::create(['title' => 'Old article', 'description' => 'x', 'content' => '<p>SAIL fell because its chairman resigned.</p>',
            'source_url' => 'https://mint.example/sail', 'status' => 'active']);

        $body = str_repeat('Steel companies traded lower as investors grew cautious about the sector outlook. ', 16);
        Http::swap(new Factory());
        Http::fake(fn ($request) => self::geminiSays(str_contains(json_encode($request->data()), 'fact-checker')
            ? 'UNSUPPORTED: the chairman resigned'
            : "TITLE: SAIL shares fall as steel prices weaken across the domestic market\nSUMMARY: SAIL fell.\nARTICLE:\n{$body}"));

        $r = app(\App\Services\NewsAutoPublisher::class)->run();

        $this->assertSame(0, $r['business']);
        $this->assertSame(1, $r['held']);                    // new draft not published
        $this->assertSame(1, $r['hidden']);                  // older article hidden
        $this->assertSame('inactive', $old->fresh()->status);
        $this->assertSame(1, LatestNews::count());

        // Fact-check can't run (e.g. daily allowance used up) -> still nothing published
        Http::swap(new Factory());
        Http::fake(fn ($request) => str_contains(json_encode($request->data()), 'fact-checker')
            ? self::geminiLimit()
            : self::geminiSays("TITLE: SAIL shares fall as steel prices weaken across the domestic market\nSUMMARY: SAIL fell.\nARTICLE:\n{$body}"));
        \Illuminate\Support\Facades\Cache::flush();
        $this->assertSame(0, app(\App\Services\NewsAutoPublisher::class)->run()['business']);
    }

    public function test_fact_check_ignores_general_explanations_and_omissions(): void
    {
        config(['services.gemini_ai.key' => 'AIzaTestKey0123456789012345678901234567']);
        Http::swap(new Factory());
        Http::fake(['*' => self::geminiSays(
            'UNSUPPORTED: The article does not mention the opening price; This is a general statement about IPOs; Trump rejects Iran proposal; the chairman resigned'
        )]);

        $problems = app(NewsWriter::class)->factCheck(
            ['title' => 'Dow futures fall', 'description' => 'x', 'content' => '<p>Dow futures fell as Trump rejects Iran proposal.</p>'],
            'Dow futures fall close to 200 points, oil rises as Trump rejects Iran proposal', null
        );

        // Only the invented event is left: omissions, "general" notes and source text are dropped
        $this->assertSame(['the chairman resigned'], $problems);
    }

    public function test_only_indian_market_stories_are_auto_published(): void
    {
        $indian = fn ($t) => \App\Services\NewsAutoPublisher::isIndianMarketStory($t);

        $this->assertFalse($indian('Dow futures fall close to 200 points, oil prices rise as Trump rejects Iran proposal. An important week lies ahead for Wall Street'));
        $this->assertFalse($indian('Nasdaq hits record as US Fed signals rate cut'));
        // Indian company news rarely says "India": still Indian
        $this->assertTrue($indian('HDFC Bank shares fall 2% as banking stocks decline'));
        // A global story that is about India's market counts
        $this->assertTrue($indian('Sensex falls 500 points as Wall Street selloff and US Fed worries hit Dalal Street'));
        $this->assertTrue($indian('Rupee slips as the US Fed holds rates'));
    }

    public function test_stock_news_says_support_and_resistance_level_and_spots_buy_sell_hold_calls(): void
    {
        $this->assertSame('Brokerage sees a resistance level of ₹400 and support at ₹310 for Eternal',
            NewsWriter::sebiWording('Brokerage sees a target of ₹400 and stop loss at ₹310 for Eternal'));
        $this->assertSame('Eternal Resistance Level Raised; resistance levels at Rs 1,250 and stop-gap measures',
            NewsWriter::sebiWording('Eternal Target Price Raised; targets at Rs 1,250 and stop-gap measures'));
        // A business target is not a share price
        $this->assertSame('a revenue target of ₹10,000 crore and an inflation target of 4%',
            NewsWriter::sebiWording('a revenue target of ₹10,000 crore and an inflation target of 4%'));

        $this->assertSame(['maintains buy'], NewsWriter::recommendationWords('Motilal Oswal maintains Buy on Eternal shares.'));
        $this->assertNotEmpty(NewsWriter::recommendationWords('<p>The brokerage has a \'sell\' rating on the stock.</p>'));
        $this->assertNotEmpty(NewsWriter::recommendationWords('Investors should accumulate the stock.'));
        $this->assertNotEmpty(NewsWriter::recommendationWords('UBS upgraded the stock to outperform.'));
        // Real deals and policy wording are not calls
        $this->assertSame([], NewsWriter::recommendationWords('The promoter will sell a 2% stake. RBI keeps rates on hold and retained a neutral stance; rates hold steady. Shareholding rose.'));
    }

    public function test_auto_publish_uses_sebi_wording_and_fixes_older_articles(): void
    {
        config(['services.gemini_ai.key' => 'AIzaTestKey0123456789012345678901234567', 'news.auto.images' => false]);
        NewsInboxItem::create([
            'source' => 'Mint', 'url' => 'https://mint.example/eternal', 'url_hash' => sha1('https://mint.example/eternal'),
            'title' => 'Motilal Oswal keeps buy on Eternal with target of ₹400', 'summary' => 'Target ₹400.', 'published_at' => now(), 'score' => 6, 'coverage' => 1,
        ]);
        $older = LatestNews::create(['title' => 'Older call', 'description' => 'x', 'content' => '<p>Stop loss at ₹300.</p>',
            'source_url' => 'https://mint.example/old', 'status' => 'active', 'fact_checked_at' => now()]);
        $call = LatestNews::create(['title' => 'Brokerage keeps a buy rating', 'description' => 'x', 'content' => '<p>It keeps a buy rating.</p>',
            'source_url' => 'https://mint.example/call', 'status' => 'active', 'fact_checked_at' => now()]);

        $body = str_repeat('Eternal shares were in focus on the exchanges today as investors watched the stock closely. ', 12);
        Http::swap(new Factory());
        Http::fake(fn ($request) => self::geminiSays(match (true) {
            str_contains(json_encode($request->data()), 'fact-checker') => 'OK',
            str_contains(json_encode($request->data()), 'published news article') => 'TITLE: Brokerage keeps a buy rating', // keeps the call
            str_contains(json_encode($request->data()), 'gives a recommendation') => "TITLE: Motilal Oswal sees a resistance level of ₹400 for Eternal shares\nSUMMARY: Eternal resistance level at ₹400.\nARTICLE:\n{$body}",
            default => "TITLE: Motilal Oswal maintains Buy on Eternal shares with a target of ₹400\nSUMMARY: Target ₹400.\nARTICLE:\n{$body}",
        }));

        $r = app(\App\Services\NewsAutoPublisher::class)->run();

        $this->assertSame(1, $r['business'], json_encode($r));
        $this->assertSame('Motilal Oswal sees a resistance level of ₹400 for Eternal shares', LatestNews::latest('id')->first()->title);
        $this->assertSame('<p>Support at ₹300.</p>', $older->fresh()->content);
        $this->assertSame('inactive', $call->fresh()->status); // the rewrite kept the buy call
    }

    public function test_write_with_ai_explains_when_no_free_key_is_set(): void
    {
        config(['services.gemini_ai.key' => null, 'services.huggingface_ai.token' => null]);
        $this->assertFalse(app(NewsWriter::class)->isConfigured());

        $this->actingAs($this->admin())
            ->postJson(route('admin.news-writer'), ['headline' => 'x'])
            ->assertStatus(502)->assertJsonFragment(['message' => \App\Services\NewsWriter::NOT_SET]);
    }
}
