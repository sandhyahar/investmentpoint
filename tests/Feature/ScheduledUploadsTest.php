<?php

namespace Tests\Feature;

use App\Models\DigitalNewspaper;
use App\Models\MediaArticle;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Daily E-Papers and Media Articles have the schedule too: the website and Blogger get them at that date and time */
class ScheduledUploadsTest extends TestCase
{
    use RefreshDatabase, \Tests\FakesBloggerEmail;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        Carbon::setTestNow(Carbon::parse('2026-10-23 13:30', 'Asia/Kolkata'));
        $this->actingAs(User::forceCreate(['name' => 'Admin', 'username' => 'admin-sched', 'role' => 'admin', 'password' => Hash::make('x')]));

        // Blogger connected an hour ago
        Setting::set('blogger_client_id', '1234567890-abcdef123.apps.googleusercontent.com');
        Setting::set('blogger_client_secret', Crypt::encryptString('GOCSPX-test-secret-0123456789'));
        Setting::set('blogger_refresh_token', Crypt::encryptString('1//refresh-token'));
        Setting::set('blogger_connected_at', now()->subHour()->toIso8601String());
        Setting::set('blogger_blogs', json_encode(['hindi' => ['id' => 'blog-hindi', 'name' => 'Hindi', 'url' => 'https://h.blogspot.com/'],
            'media' => ['id' => 'blog-media', 'name' => 'Media', 'url' => 'https://m.blogspot.com/']]));
        Http::swap(new Factory());
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'ya29.token', 'expires_in' => 3599]),
            'www.googleapis.com/blogger/v3/blogs/*/posts/' => fn (Request $r) => Http::response(['id' => (string) crc32($r['title']), 'url' => 'https://x.blogspot.com/' . crc32($r['title']) . '.html']),
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private static function page(): UploadedFile
    {
        return UploadedFile::fake()->image('page-01.jpg', 300, 420);
    }

    public function test_a_daily_e_paper_becomes_a_scheduled_blogger_post_and_nothing_is_kept_on_the_website(): void
    {
        $this->bloggerConnected();
        $this->post(route('admin.digital-newspaper.store', ['edition' => 'hindi']), [
            'title' => 'INVESTMENT POINT - 24.10.2026', 'published_on' => '2026-10-24', 'status' => 'active',
            'publish_date' => '2026-10-24', 'publish_time' => '07:00', 'page_files' => [self::page()],
        ])->assertRedirect()->assertSessionHasNoErrors();

        // Blogger published the emailed post at once: it goes back to a draft and is scheduled for 7:00 AM; the
        // website gets it from the blog's hourly import once it is live
        $this->bloggerMadePost('blog-hindi', '8881', self::codeOf($this->sentEmails()->sole()), 1);
        app(\App\Services\EpaperByEmail::class)->run();
        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), 'blogs/blog-hindi/posts/8881/revert'));
        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), 'blogs/blog-hindi/posts/8881/publish?publishDate=' . urlencode('2026-10-24T01:30:00+00:00')));
        $this->assertSame(0, DigitalNewspaper::count());
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    /** The hourly import reads the blogspots: the posts the website made there must not come back as second copies */
    public function test_the_hourly_import_never_copies_back_a_post_the_website_made(): void
    {
        $paper = DigitalNewspaper::create(['edition' => 'hindi', 'title' => 'INVESTMENT POINT - 24.10.2026', 'published_on' => '2026-10-24', 'pages' => ['epaper/hindi/1.jpg'], 'status' => 'active']);
        $article = MediaArticle::create(['title' => 'INVESTMENT POINT / PAGDANDI - 24.10.2026', 'publication' => 'PAGDANDI', 'published_on' => '2026-10-24',
            'image' => 'media-articles/p1.jpg', 'pages' => ['media-articles/p1.jpg'], 'status' => 'active']);
        app(\App\Services\BloggerPublisher::class)->publishPending();

        // The blogspots' feeds now list those posts (feed ids end in "post-<the id Blogger gave>")
        $feed = fn ($model, $html) => ['feed' => ['openSearch$totalResults' => ['$t' => '1'], 'entry' => [[
            'id' => ['$t' => 'tag:blogger.com,1999:blog-1.post-' . $model->fresh()->blogger_id],
            'title' => ['$t' => $model->title], 'published' => ['$t' => '2026-10-24T07:00:00+05:30'],
            'content' => ['$t' => $html],
        ]]]];
        Http::fake([
            'markettrendhindi-ipoint.blogspot.com/*' => Http::response($feed($paper, \App\Services\BloggerPublisher::postHtml($paper))),
            'mediaarticle-ipoint.blogspot.com/*' => Http::response($feed($article, \App\Services\BloggerPublisher::postHtml($article))),
        ]);

        $this->artisan('newspaper:import', ['--edition' => 'hindi', '--full' => true])->assertSuccessful();
        $this->artisan('media:import', ['--full' => true])->assertSuccessful();

        $this->assertSame(1, DigitalNewspaper::count());
        $this->assertSame(1, MediaArticle::count());

        // Control: it is the saved post id that stops it (the same post with an unknown id would be imported), except
        // for an E-Paper edition with the same date and title, which is never copied twice
        $paper->forceFill(['blogger_id' => 'other-1'])->save();
        $article->forceFill(['blogger_id' => 'other-2'])->save();
        $this->artisan('newspaper:import', ['--edition' => 'hindi', '--full' => true]);
        $this->artisan('media:import', ['--full' => true]);
        $this->assertSame(1, DigitalNewspaper::count());
        $this->assertSame(2, MediaArticle::count());
    }

    public function test_a_media_article_can_be_scheduled_and_is_hidden_from_readers_until_then(): void
    {
        $this->get(route('admin.media-articles.create'))->assertOk()->assertSee('Schedule Date')->assertSee('name="publish_time"', false);

        $this->post(route('admin.media-articles.store'), [
            'title' => 'INVESTMENT POINT / PAGDANDI - 24.10.2026', 'column_name' => 'INVESTMENT POINT', 'publication' => 'PAGDANDI',
            'published_on' => '2026-10-24', 'status' => 'active', 'publish_date' => '2026-10-24', 'publish_time' => '08:15', 'page_files' => [self::page()],
        ])->assertRedirect(route('admin.media-articles.index'))->assertSessionHasNoErrors();
        $article = MediaArticle::latest('id')->firstOrFail();

        $this->assertTrue($article->isScheduled());
        $this->get($article->url)->assertNotFound();
        $this->get(route('media-articles.index'))->assertDontSee('PAGDANDI - 24.10.2026');
        $this->get(route('admin.media-articles.index'))->assertSee('Scheduled')->assertSee('24 Oct 2026, 08:15 AM');
        $this->artisan('blogger:publish');
        $this->assertNull($article->fresh()->blogger_url);

        Carbon::setTestNow(Carbon::parse('2026-10-24 08:20', 'Asia/Kolkata'));
        $this->get($article->url)->assertOk();
        $this->get(route('media-articles.index'))->assertSee('PAGDANDI - 24.10.2026');
        $this->artisan('blogger:publish'); // the 5-minute run catches it too
        $this->assertNotNull($article->fresh()->blogger_url);

        // A time without a date is refused; clearing the schedule publishes at once
        $this->put(route('admin.media-articles.update', $article), ['title' => $article->title, 'published_on' => '2026-10-24', 'status' => 'active', 'publish_time' => '09:00'])
            ->assertSessionHasErrors('publish_date');
        $this->put(route('admin.media-articles.update', $article), ['title' => $article->title, 'published_on' => '2026-10-24', 'status' => 'active', 'publish_date' => '', 'publish_time' => ''])
            ->assertSessionHasNoErrors();
        $this->assertNull($article->fresh()->publish_at);
    }
}
