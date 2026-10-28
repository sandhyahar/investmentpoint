<?php

namespace Tests\Feature;

use App\Models\LatestNews;
use App\Models\Setting;
use App\Models\User;
use App\Services\SeoAudit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/** Admin -> Settings -> Google (SEO): Search Console code and Analytics ID pasted in the admin panel. */
class AdminGoogleSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        config(['seo.google_verification' => null, 'seo.google_analytics_id' => null]);
        $this->actingAs(User::forceCreate(['name' => 'Administrator', 'username' => 'admin-google', 'email' => 'admin-google@example.com', 'role' => 'admin', 'password' => bcrypt('secret')]));
    }

    private function check(string $label): array
    {
        return collect(app(SeoAudit::class)->run()['checks'])->firstWhere('label', $label);
    }

    public function test_pasting_the_codes_connects_google_on_every_page(): void
    {
        $this->get(route('admin.settings.edit'))->assertOk()->assertSee('Google (SEO)')->assertSee('name="google_site_verification"', false);
        $this->get(route('home'))->assertOk()->assertDontSee('google-site-verification', false)->assertDontSee('googletagmanager.com', false);
        $this->assertSame('warn', $this->check('Google Search Console connected')['status']);

        // The whole tag from Search Console is pasted; the Analytics ID in small letters
        $this->post(route('admin.settings.google'), [
            'google_site_verification' => '<meta name="google-site-verification" content="AbCdEf123456_ghIJKlmnop-QRstuVWxyz0123456789" />',
            'google_analytics_id' => ' g-ab12cd34ef ',
        ])->assertSessionHasNoErrors()->assertSessionHas('success');

        $this->assertSame('AbCdEf123456_ghIJKlmnop-QRstuVWxyz0123456789', Setting::get('google_site_verification'));
        $this->assertSame('G-AB12CD34EF', Setting::get('google_analytics_id'));

        $this->get(route('home'))->assertOk()
            ->assertSee('<meta name="google-site-verification" content="AbCdEf123456_ghIJKlmnop-QRstuVWxyz0123456789">', false)
            ->assertSee('https://www.googletagmanager.com/gtag/js?id=G-AB12CD34EF', false);
        $this->assertSame('pass', $this->check('Google Search Console connected')['status']);
        $this->assertSame('pass', $this->check('Google Analytics connected')['status']);
    }

    public function test_wrong_codes_are_refused_and_empty_boxes_disconnect(): void
    {
        $this->post(route('admin.settings.google'), ['google_site_verification' => 'hello', 'google_analytics_id' => 'UA-12345-1'])
            ->assertSessionHasErrors(['google_site_verification', 'google_analytics_id']);
        $this->assertNull(Setting::get('google_analytics_id'));

        Setting::set('google_analytics_id', 'G-AB12CD34EF');
        $this->post(route('admin.settings.google'), ['google_site_verification' => '', 'google_analytics_id' => ''])->assertSessionHasNoErrors();
        $this->get(route('home'))->assertOk()->assertDontSee('googletagmanager.com', false);
    }

    public function test_the_news_checks_count_latest_news_articles_too(): void
    {
        $this->assertSame('No news articles yet', $this->check('News headlines of good length (20-110 characters)')['detail']);

        LatestNews::create(['title' => 'RBI keeps the repo rate unchanged at its October policy meeting', 'status' => 'active',
            'description' => 'The Reserve Bank of India held the repo rate steady, saying inflation is easing while growth stays firm.']);

        $this->assertSame('pass', $this->check('News headlines of good length (20-110 characters)')['status']);
        $this->assertSame('pass', $this->check('News with a proper Google description (70+ characters)')['status']);
        $this->assertSame('pass', $this->check('Fresh content (new article in the last 7 days)')['status']);
    }

    public function test_a_short_news_summary_is_continued_with_the_article_for_google(): void
    {
        $item = LatestNews::create(['title' => 'PC Jeweller shares rise for the fourth session in a row', 'status' => 'active',
            'description' => 'PC Jeweller shares rise 5% and 20% in 4 sessions.',
            'content' => '<p>Shares of PC Jeweller extended their rally on Monday as the company said it had become debt free after repaying its lenders in full.</p><p>Second paragraph.</p>']);

        $expected = 'PC Jeweller shares rise 5% and 20% in 4 sessions. Shares of PC Jeweller extended their rally on Monday as the company said it had become debt free after…';
        $this->assertSame($expected, \App\Support\Html::metaDescription($item->description, $item->body));
        $this->assertLessThanOrEqual(160, mb_strlen($expected));
        $this->get($item->url)->assertOk()->assertSee('<meta name="description" content="' . e($expected) . '">', false);

        // A summary that is already long enough is used as it is; an article opening with the summary isn't repeated
        $long = 'The Reserve Bank of India kept the repo rate unchanged at its October meeting, saying inflation is easing while growth remains firm.';
        $this->assertSame($long, \App\Support\Html::metaDescription($long, '<p>Other text.</p>'));
        $this->assertSame('Bond yields increase due to RBI measures announced on Friday.', \App\Support\Html::metaDescription('Bond yields increase due to RBI measures.', '<p>Bond yields increase due to RBI measures announced on Friday.</p>'));
    }
}
