<?php

namespace Tests\Feature;

use App\Models\News;
use App\Models\TechnicalAnalysis;
use App\Models\UserBlog;
use App\Support\ShortLink;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/** investmentpoint.in/nifty, /hdfc-bank: short share links that open the newest article with that name. */
class ShortLinkTest extends TestCase
{
    use RefreshDatabase;

    public function test_names_come_from_the_index_or_the_company_in_the_title(): void
    {
        $news = fn (string $t) => ShortLink::nameFor(new News(['title' => $t]));
        $ta = fn (string $t) => ShortLink::nameFor(new TechnicalAnalysis(['title' => $t]));

        $this->assertSame('nifty', $news('Nifty Derivative Market Trend Analysis : Understanding Market Momentum'));
        $this->assertSame('nifty', $news('Market Outlook : Nifty Support at 22202–22088; Resistance at 22474'));
        $this->assertSame('bank-nifty', $news('Bank Nifty weekly outlook'));
        $this->assertNull($news('Indian Stock Market Remains Volatile Amid Global Uncertainty'));
        $this->assertSame('hdfc-bank', $ta('HDFC Bank Technical Analysis : Trend Support & Resistance'));
        $this->assertSame('reliance-industries', $ta('Reliance Industries Technical Analysis : Trend, Support & Resistance'));
        $this->assertSame('adani-ensol', $ta('Adani Ensol Technical View: Trend, Momentum & Price Levels'));
        $this->assertNull($ta('Weekly levels to watch'));
    }

    public function test_the_short_link_opens_the_newest_article_and_older_ones_share_their_own_link(): void
    {
        Carbon::setTestNow('2026-10-18 09:00:00');
        $old = News::create(['title' => 'Nifty Derivative Market Trend Analysis: Expert Insights', 'description' => 'x', 'status' => 'active']);
        $hdfc = TechnicalAnalysis::create(['title' => 'HDFC Bank Technical Analysis : Trend', 'description' => 'x', 'status' => 'active']);
        Carbon::setTestNow('2026-10-19 09:00:00');
        $new = News::create(['title' => 'Nifty Derivative Market Trend Analysis : Key Levels', 'description' => 'x', 'status' => 'active']);
        News::create(['title' => 'Nifty draft', 'description' => 'x', 'status' => 'inactive']); // not shown: never the target

        $this->get('/nifty')->assertRedirect($new->url);
        $this->get('/NIFTY')->assertRedirect($new->url);
        $this->get('/hdfc-bank')->assertRedirect($hdfc->url);
        $this->get('/no-such-name')->assertNotFound();

        $this->assertSame(url('/nifty'), $new->share_url);
        $this->assertSame($old->url, $old->share_url); // an older Nifty article keeps its own address
        $this->assertSame(url('/hdfc-bank'), $hdfc->share_url);
        $this->get($new->url)->assertOk()->assertSee('data-url="' . url('/nifty') . '"', false);
    }

    public function test_a_blog_keeps_its_address(): void
    {
        UserBlog::create(['author_name' => 'Paresh Dave', 'title' => 'Nifty', 'slug' => 'nifty', 'content' => 'A blog.', 'status' => 'approved', 'approved_at' => now()]);
        $news = News::create(['title' => 'Nifty Derivative Market Trend Analysis', 'description' => 'x', 'status' => 'active']);

        $this->get('/nifty')->assertOk()->assertSee('A blog.');
        $this->assertSame($news->url, $news->share_url);
    }

    public function test_the_admin_share_url_is_used_and_empty_keeps_the_name_from_the_title(): void
    {
        $hdfc = TechnicalAnalysis::create(['title' => 'HDFC Bank Technical Analysis : Trend', 'description' => 'x', 'status' => 'active', 'share_name' => 'hdfc-bank-ta']);

        $this->assertSame(url('/hdfc-bank-ta'), $hdfc->share_url);
        $this->get('/hdfc-bank-ta')->assertRedirect($hdfc->url);
        $this->get('/hdfc-bank')->assertRedirect($hdfc->url); // the name from the title still works

        $hdfc->update(['share_name' => null]);
        \App\Support\ContentVersion::bump();
        $this->assertSame(url('/hdfc-bank'), $hdfc->fresh()->share_url);
        $this->get('/hdfc-bank-ta')->assertNotFound();

        $this->assertSame('hdfc-bank-21', ShortLink::normalize('https://investmentpoint.in/HDFC Bank 21'));
        $this->assertNull(ShortLink::normalize('  '));
    }

    public function test_the_share_box_saves_the_share_url(): void
    {
        $admin = \App\Models\User::forceCreate(['name' => 'Administrator', 'username' => 'admin-test', 'email' => 'admin-test@example.com', 'role' => 'admin', 'password' => bcrypt('secret')]);
        $hdfc = TechnicalAnalysis::create(['title' => 'HDFC Bank Technical Analysis : Trend', 'description' => 'x', 'status' => 'active']);
        $save = fn ($name) => $this->actingAs($admin)->postJson(route('admin.share-link.update', ['analysis', $hdfc->id]), ['share_name' => $name]);

        $this->actingAs($admin)->get(route('admin.technical-analysis.index'))->assertOk()
            ->assertSee('data-edit="' . route('admin.share-link.update', ['analysis', $hdfc->id]) . '"', false)->assertSee('data-auto-name="hdfc-bank"', false);
        $this->actingAs($admin)->get(route('admin.technical-analysis.edit', $hdfc))->assertOk()
            ->assertSee('name="share_name"', false)->assertSee('placeholder="hdfc-bank"', false);
        $this->actingAs($admin)->get(route('admin.news.create'))->assertOk()->assertSee('name="share_name"', false);

        $save('HDFC Bank Today')->assertOk()->assertJson(['url' => url('/hdfc-bank-today'), 'share_name' => 'hdfc-bank-today', 'auto' => 'hdfc-bank']);
        $this->get('/hdfc-bank-today')->assertRedirect($hdfc->url);
        $save('news')->assertStatus(422)->assertJsonValidationErrors('share_name'); // a site page
        $save('')->assertOk()->assertJson(['url' => url('/hdfc-bank'), 'share_name' => null]);
        $this->assertNull($hdfc->fresh()->share_name);
    }
}
