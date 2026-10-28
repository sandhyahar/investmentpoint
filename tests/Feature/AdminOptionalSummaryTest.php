<?php

namespace Tests\Feature;

use App\Models\LatestNews;
use App\Models\News;
use App\Models\TechnicalAnalysis;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Admin forms have no "Short Summary" box: the short text on cards and lists is the start of the full article. */
class AdminOptionalSummaryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::forceCreate(['name' => 'Administrator', 'username' => 'admin-sum', 'email' => 'admin-sum@example.com', 'role' => 'admin', 'password' => bcrypt('secret')]));
    }

    public function test_market_trend_news_can_be_saved_without_a_summary(): void
    {
        $this->get(route('admin.news.create'))->assertOk()->assertDontSee('Short Summary *')
            ->assertSee('Write Full Article with AI')->assertSee('data-url="' . route('admin.news-writer') . '"', false);

        $this->post(route('admin.news.store'), ['title' => 'Sensex closes higher on strong banking stocks', 'status' => 'active',
            'content' => '<p>Banking shares led the <b>market</b> higher today.</p><p>Nifty also gained.</p>'])
            ->assertSessionHasNoErrors();
        $this->assertSame('Banking shares led the market higher today. Nifty also gained.', News::first()->description);

        // Nothing typed at all: saved with an empty summary
        $this->post(route('admin.news.store'), ['title' => 'Headline only', 'status' => 'active', 'description' => '<p><br></p>'])->assertSessionHasNoErrors();
        $this->assertSame('', News::where('title', 'Headline only')->first()->description);

        // A typed summary is kept as it is
        $this->put(route('admin.news.update', News::first()), ['title' => 'Sensex closes higher on strong banking stocks', 'status' => 'active', 'description' => '<p>My own summary</p>', 'content' => '<p>Body</p>'])
            ->assertSessionHasNoErrors();
        $this->assertSame('<p>My own summary</p>', News::first()->description);

        $this->get(route('admin.news.edit', News::first()))->assertOk()->assertSee('Write Full Article with AI');
        $this->get(route('home'))->assertOk();
        $this->get(route('news.index'))->assertOk();
    }

    public function test_the_forms_have_no_short_summary_box_and_an_edit_keeps_what_was_saved(): void
    {
        $this->get(route('admin.news.create'))->assertOk()->assertDontSee('Short Summary')->assertDontSee('name="description"', false);
        $this->get(route('admin.technical-analysis.create'))->assertOk()->assertDontSee('Short Summary')->assertDontSee('name="description"', false);
        $this->get(route('admin.latest-news.create'))->assertOk()->assertDontSee('Short Summary')->assertDontSee('name="description"', false);

        // An older analysis with a hand-written summary and no article: saving the form again keeps the summary
        $analysis = TechnicalAnalysis::create(['title' => 'SBI Cards: key levels', 'description' => 'Hand-written summary', 'status' => 'active']);
        $this->get(route('admin.technical-analysis.edit', $analysis))->assertOk()->assertDontSee('Short Summary');
        $this->put(route('admin.technical-analysis.update', $analysis), ['title' => 'SBI Cards: key levels', 'status' => 'active', 'support' => '618'])->assertSessionHasNoErrors();
        $this->assertSame('Hand-written summary', $analysis->fresh()->description);

        // With an article, the summary follows the article
        $this->put(route('admin.technical-analysis.update', $analysis), ['title' => 'SBI Cards: key levels', 'status' => 'active', 'content' => '<p>The stock is holding above its support zone.</p>'])->assertSessionHasNoErrors();
        $this->assertSame('The stock is holding above its support zone.', $analysis->fresh()->description);
    }

    public function test_technical_analysis_has_a_cmp_shown_with_support_and_resistance(): void
    {
        $this->get(route('admin.technical-analysis.create'))->assertOk()->assertSee('name="cmp"', false);

        $this->post(route('admin.technical-analysis.store'), ['title' => 'Godrej Properties: key chart levels', 'status' => 'active', 'cmp' => '1555', 'support' => '1533', 'resistance' => '1574 - 1580'])
            ->assertSessionHasNoErrors();
        $analysis = TechnicalAnalysis::first();
        $this->assertSame('1555', $analysis->cmp);

        $this->get(route('admin.technical-analysis.edit', $analysis))->assertOk()->assertSee('name="cmp" value="1555"', false);
        $this->put(route('admin.technical-analysis.update', $analysis), ['title' => 'Godrej Properties: key chart levels', 'status' => 'active', 'cmp' => '1560', 'support' => '1533'])
            ->assertSessionHasNoErrors();
        $this->post(route('admin.technical-analysis.store'), ['title' => 'Bad price', 'status' => 'active', 'cmp' => 'abc'])->assertSessionHasErrors('cmp');

        $this->get(route('admin.technical-analysis.index'))->assertOk()->assertSee('CMP ₹1,560');
        // Home card: the CMP only, without the support / resistance boxes
        $this->get(route('home'))->assertOk()->assertSee('class="ta-c"', false)->assertSee('₹1,560')
            ->assertDontSee('class="ta-s"', false)->assertDontSee('class="ta-r"', false)->assertDontSee('₹1,533');
        $this->get(route('analysis.index'))->assertOk()->assertSee('CMP ₹1,560');
        $this->get($analysis->fresh()->url)->assertOk()->assertSee('ta-keylevel cmp', false)->assertSee('₹1,560');
    }

    public function test_the_admin_chooses_the_date_shown_on_a_technical_analysis(): void
    {
        \Illuminate\Support\Carbon::setTestNow('2026-10-13 05:00:00'); // 10:30 AM in India

        // The Date box is filled with today, ready to change
        $this->get(route('admin.technical-analysis.create'))->assertOk()->assertSee('name="published_on" value="2026-10-13"', false);

        $this->post(route('admin.technical-analysis.store'), ['title' => 'Adani Ports: key chart levels', 'status' => 'active', 'cmp' => '1755', 'published_on' => '2026-10-09'])->assertSessionHasNoErrors();
        $this->post(route('admin.technical-analysis.store'), ['title' => 'JSW Steel: key chart levels', 'status' => 'active', 'cmp' => '1173', 'published_on' => '2026-10-12'])->assertSessionHasNoErrors();
        $this->post(route('admin.technical-analysis.store'), ['title' => 'No date analysis', 'status' => 'active', 'cmp' => '100', 'published_on' => ''])->assertSessionHasNoErrors();
        $this->post(route('admin.technical-analysis.store'), ['title' => 'Bad date', 'status' => 'active', 'published_on' => '12/10/2026'])->assertSessionHasErrors('published_on');

        $adani = TechnicalAnalysis::where('title', 'Adani Ports: key chart levels')->first();
        $this->assertSame('2026-10-09', $adani->published_on->format('Y-m-d'));
        $this->assertNull(TechnicalAnalysis::where('title', 'No date analysis')->first()->published_on);

        // Cards: the admin's date, newest date first (the one without a date counts as added today)
        $home = $this->get(route('home'))->assertOk()->assertSee('09 Oct 2026')->assertSee('12 Oct 2026')
            ->assertSeeInOrder(['No date analysis', 'JSW Steel: key chart levels', 'Adani Ports: key chart levels']);
        $this->assertSame(2, substr_count($home->getContent(), 'class="ta-date"')); // none on the analysis without a date

        $this->get($adani->url)->assertOk()->assertSee('09 October 2026')->assertSee('bi-person-fill', false)->assertSee('id="imgZoom"', false)->assertDontSee('📅')->assertDontSee('✍');
        $this->get(route('admin.technical-analysis.edit', $adani))->assertOk()->assertSee('name="published_on" value="2026-10-09"', false);
        $this->put(route('admin.technical-analysis.update', $adani), ['title' => 'Adani Ports: key chart levels', 'status' => 'active', 'cmp' => '1755', 'published_on' => '2026-10-13'])->assertSessionHasNoErrors();
        $this->get(route('analysis.index'))->assertOk()->assertSeeInOrder(['Adani Ports: key chart levels', 'JSW Steel: key chart levels']);
    }

    public function test_technical_analysis_and_latest_news_can_be_saved_without_a_summary(): void
    {
        $this->post(route('admin.technical-analysis.store'), ['title' => 'Reliance Industries: key chart levels', 'status' => 'active', 'support' => '2840', 'resistance' => '2940'])
            ->assertSessionHasNoErrors();
        $this->assertSame('', TechnicalAnalysis::first()->description);

        $this->post(route('admin.latest-news.store'), ['title' => 'RBI keeps the repo rate unchanged at its October meeting', 'status' => 'active', 'content' => '<p>The central bank held rates steady.</p>'])
            ->assertSessionHasNoErrors();
        $this->assertSame('The central bank held rates steady.', LatestNews::first()->description);

        $this->get(route('home'))->assertOk();
        $this->get(route('analysis.index'))->assertOk();
    }
}
