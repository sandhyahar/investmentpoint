<?php

namespace Tests\Feature;

use App\Models\DigitalNewspaper;
use App\Models\MediaArticle;
use App\Models\Setting;
use App\Models\User;
use App\Services\BloggerPublisher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BloggerPublisherTest extends TestCase
{
    use RefreshDatabase;

    private const CLIENT_ID = '1234567890-abcdef123.apps.googleusercontent.com';

    private function admin(): User
    {
        return User::forceCreate([
            'name' => 'Administrator', 'username' => 'admin-blogger', 'email' => 'admin-blogger@example.com',
            'role' => 'admin', 'password' => bcrypt('secret'),
        ]);
    }

    /** Google + Blogger answering; each blogspot's id is its name ("markettrendhindi-ipoint" -> blog-markettrendhindi-ipoint) */
    private function fakeGoogle(array $post = null): void
    {
        Http::swap(new Factory());
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'ya29.token', 'expires_in' => 3599, 'refresh_token' => '1//refresh-token']),
            'www.googleapis.com/blogger/v3/blogs/byurl*' => fn (Request $r) => Http::response(['id' => 'blog-' . explode('.', parse_url($r['url'], PHP_URL_HOST))[0],
                'name' => 'Blog ' . $r['url'], 'url' => $r['url']]),
            'www.googleapis.com/blogger/v3/blogs/*/posts/' => function () use ($post) {
                static $n = 0;
                $n++;

                return Http::response($post ?? ['id' => $n === 1 ? '777000111' : '77700011' . $n, 'url' => $n === 1
                    ? 'https://markettrendhindi-ipoint.blogspot.com/2026/10/investment-point-24102026.html' : "https://example.blogspot.com/post-{$n}.html"]);
            },
        ]);
    }

    private function connected(): void
    {
        Setting::set('blogger_client_id', self::CLIENT_ID);
        Setting::set('blogger_client_secret', Crypt::encryptString('GOCSPX-test-secret-0123456789'));
        Setting::set('blogger_refresh_token', Crypt::encryptString('1//refresh-token'));
        Setting::set('blogger_connected_at', now()->subHour()->toIso8601String());
        Setting::set('blogger_blogs', json_encode(['hindi' => ['id' => 'blog-hindi', 'name' => 'Market Trend Hindi', 'url' => 'https://markettrendhindi-ipoint.blogspot.com/'],
            'weekly' => ['id' => 'blog-weekly', 'name' => 'Weekly', 'url' => 'https://weeklynewspaper-ipoint.blogspot.com/'],
            'media' => ['id' => 'blog-media', 'name' => 'Media Article', 'url' => 'https://mediaarticle-ipoint.blogspot.com/']]));
    }

    public function test_the_admin_connects_the_google_account_and_the_blogs_are_found(): void
    {
        $this->fakeGoogle();
        $this->actingAs($this->admin());

        $this->get(route('admin.settings.edit'))->assertOk()->assertSee('Blogger (auto-post E-Paper')
            ->assertSee(route('admin.settings.blogger.callback'))->assertDontSee('Connect Blogger</a>', false);

        $this->post(route('admin.settings.blogger'), ['blogger_client_id' => self::CLIENT_ID, 'blogger_client_secret' => 'GOCSPX-test-secret-0123456789'])
            ->assertRedirect(route('admin.settings.edit') . '#blogger');
        $this->assertSame('GOCSPX-test-secret-0123456789', BloggerPublisher::clientSecret()); // stored encrypted
        $this->assertNotSame('GOCSPX-test-secret-0123456789', Setting::get('blogger_client_secret'));

        // Off to Google's sign-in, as the blogs' owner
        $google = $this->get(route('admin.settings.blogger.connect'))->assertRedirect()->headers->get('Location');
        $this->assertStringStartsWith('https://accounts.google.com/o/oauth2/v2/auth?', $google);
        parse_str(parse_url($google, PHP_URL_QUERY), $q);
        $this->assertSame([self::CLIENT_ID, BloggerPublisher::SCOPE, 'offline', 'shreeinvestmentpoint@gmail.com', route('admin.settings.blogger.callback')],
            [$q['client_id'], $q['scope'], $q['access_type'], $q['login_hint'], $q['redirect_uri']]);

        // A forged callback (wrong state) is refused
        $this->get(route('admin.settings.blogger.callback', ['code' => 'x', 'state' => 'forged']))->assertSessionHasErrors('blogger_client_id');
        $this->assertFalse(app(BloggerPublisher::class)->isConnected());

        $this->get(route('admin.settings.blogger.connect'));
        $this->get(route('admin.settings.blogger.callback', ['code' => '4/code', 'state' => session('blogger_oauth_state')]))
            ->assertSessionHas('success', fn ($m) => str_contains($m, 'Blogger is connected (5 blogs found)'));

        $this->assertTrue(app(BloggerPublisher::class)->isConnected());
        $this->assertSame('blog-markettrendhindi-ipoint', BloggerPublisher::blogs()['hindi']['id']);
        $this->assertSame('blog-mediaarticle-ipoint', BloggerPublisher::blogs()['media']['id']);
        Http::assertSent(fn (Request $r) => str_contains($r->url(), 'oauth2.googleapis.com/token') && $r['grant_type'] === 'authorization_code' && $r['code'] === '4/code');
        $this->get(route('admin.settings.edit'))->assertSee('Connected since')->assertSee('Connect again');

        // Saving the same client again keeps the connection; another secret means connecting again
        $this->post(route('admin.settings.blogger'), ['blogger_client_id' => self::CLIENT_ID, 'blogger_client_secret' => 'GOCSPX-test-secret-0123456789']);
        $this->assertTrue(app(BloggerPublisher::class)->isConnected());
        $this->post(route('admin.settings.blogger'), ['blogger_client_id' => self::CLIENT_ID, 'blogger_client_secret' => 'GOCSPX-another-secret-0123456']);
        $this->assertFalse(app(BloggerPublisher::class)->isConnected());
    }

    public function test_new_uploads_are_posted_once_to_their_blog_and_older_or_imported_ones_are_not(): void
    {
        config(['app.public_url' => 'https://investmentpoint.in']);
        $this->connected();
        $this->fakeGoogle();

        $old = DigitalNewspaper::forceCreate(['edition' => 'hindi', 'title' => 'INVESTMENT POINT - 22.10.2026', 'published_on' => '2026-10-22', 'pages' => ['epaper/hindi/a.jpg'], 'status' => 'active', 'created_at' => now()->subDays(2)]);
        $imported = DigitalNewspaper::create(['edition' => 'hindi', 'blogger_id' => '5550001', 'title' => 'INVESTMENT POINT - 23.10.2026', 'published_on' => '2026-10-23', 'pages' => ['https://blogger.googleusercontent.com/img/b/x/s0/1.jpg'], 'status' => 'active']);
        $new = DigitalNewspaper::create(['edition' => 'hindi', 'title' => 'INVESTMENT POINT - 24.10.2026', 'published_on' => '2026-10-24', 'pages' => ['epaper/hindi/1.jpg', 'epaper/hindi/2.jpg'], 'status' => 'active']);
        $scheduled = DigitalNewspaper::create(['edition' => 'weekly', 'title' => 'INVESTMENT POINT WEEKLY - 24.10.2026 TO 30.10.2026', 'published_on' => '2026-10-24', 'pages' => ['digital-newspaper/1.jpg'], 'status' => 'active', 'publish_at' => now()->addDay()]);
        $inactive = DigitalNewspaper::create(['edition' => 'hindi', 'title' => 'Draft', 'published_on' => '2026-10-24', 'pages' => ['epaper/hindi/3.jpg'], 'status' => 'inactive']);
        $noBlog = DigitalNewspaper::create(['edition' => 'gujarati', 'title' => 'INVESTMENT POINT - 24.10.2026', 'published_on' => '2026-10-24', 'pages' => ['epaper/gujarati/1.jpg'], 'status' => 'active']);
        $clipping = MediaArticle::create(['title' => 'INVESTMENT POINT / PAGDANDI - 24.10.2026', 'image' => 'media-articles/p1.jpg', 'pages' => ['media-articles/p1.jpg'], 'description' => 'Market column', 'status' => 'active']);

        $r = app(BloggerPublisher::class)->publishPending();

        $this->assertSame(2, $r['posted'], json_encode($r));
        $this->assertSame('777000111', $new->fresh()->blogger_id); // the hourly import now skips this post
        $this->assertSame('https://markettrendhindi-ipoint.blogspot.com/2026/10/investment-point-24102026.html', $new->fresh()->blogger_url);
        $this->assertNotNull($clipping->fresh()->blogger_url);
        foreach ([$old, $scheduled, $inactive, $noBlog] as $paper) {
            $this->assertNull($paper->fresh()->blogger_url);
        }
        $this->assertSame('5550001', $imported->fresh()->blogger_id);

        Http::assertSent(fn (Request $r) => $r->url() === 'https://www.googleapis.com/blogger/v3/blogs/blog-hindi/posts/'
            && $r->hasHeader('Authorization', 'Bearer ya29.token') && $r['title'] === 'INVESTMENT POINT - 24.10.2026'
            && substr_count($r['content'], '<img ') === 2 && str_contains($r['content'], 'src="https://investmentpoint.in/storage/epaper/hindi/1.jpg"')
            && str_contains($r['content'], 'Read on Investment Point'));
        Http::assertSent(fn (Request $r) => $r->url() === 'https://www.googleapis.com/blogger/v3/blogs/blog-media/posts/'
            && str_contains($r['content'], '<p>Market column</p>') && str_contains($r['content'], 'https://investmentpoint.in/storage/media-articles/p1.jpg'));

        // Nothing is posted twice; the scheduled Weekly edition goes once its time has come
        $this->assertSame(0, app(BloggerPublisher::class)->publishPending()['posted']);
        $scheduled->update(['publish_at' => now()->subMinute()]);
        $this->assertSame(1, app(BloggerPublisher::class)->publishPending()['posted']);
        Http::assertSent(fn (Request $r) => $r->url() === 'https://www.googleapis.com/blogger/v3/blogs/blog-weekly/posts/');

        $this->actingAs($this->admin())->get(route('admin.digital-newspaper.index', ['edition' => 'hindi']))->assertOk()->assertSee('On Blogger');
    }

    public function test_a_failed_post_is_retried_then_shown_and_an_expired_access_stops_the_run(): void
    {
        config(['blogger.max_tries' => 2]);
        $this->connected();
        Http::swap(new Factory());
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'ya29.token', 'expires_in' => 3599]),
            'www.googleapis.com/blogger/v3/*' => Http::response(['error' => ['code' => 403, 'message' => 'We are sorry, but you do not have permission to access this resource.']], 403),
        ]);
        $paper = DigitalNewspaper::create(['edition' => 'hindi', 'title' => 'INVESTMENT POINT - 24.10.2026', 'published_on' => '2026-10-24', 'pages' => ['epaper/hindi/1.jpg'], 'status' => 'active']);

        $this->assertSame(1, app(BloggerPublisher::class)->publishPending()['failed']);
        $this->assertSame(1, $paper->fresh()->blogger_tries);
        $this->assertStringContainsString('do not have permission', $paper->fresh()->blogger_error);
        app(BloggerPublisher::class)->publishPending();
        $this->assertSame(2, $paper->fresh()->blogger_tries);
        $this->assertSame(0, app(BloggerPublisher::class)->publishPending()['failed']); // given up after max_tries
        $this->actingAs($this->admin())->get(route('admin.digital-newspaper.index', ['edition' => 'hindi']))->assertSee('Blogger: not posted');

        // Access removed on Google's side: the admin is told to connect again
        \Illuminate\Support\Facades\Cache::forget('blogger.access_token');
        Http::swap(new Factory());
        Http::fake(['oauth2.googleapis.com/token' => Http::response(['error' => 'invalid_grant', 'error_description' => 'Token has been expired or revoked.'], 400)]);
        DigitalNewspaper::create(['edition' => 'hindi', 'title' => 'INVESTMENT POINT - 25.10.2026', 'published_on' => '2026-10-25', 'pages' => ['epaper/hindi/9.jpg'], 'status' => 'active']);
        $r = app(BloggerPublisher::class)->publishPending();
        $this->assertStringContainsString('Connect Blogger', $r['message']);
        $this->get(route('admin.settings.edit'))->assertSee('Google access has expired or was removed');
    }

    public function test_nothing_is_posted_until_blogger_is_connected(): void
    {
        Http::swap(new Factory());
        Http::fake();
        DigitalNewspaper::create(['edition' => 'hindi', 'title' => 'INVESTMENT POINT - 24.10.2026', 'published_on' => '2026-10-24', 'pages' => ['epaper/hindi/1.jpg'], 'status' => 'active']);

        $this->artisan('blogger:publish')->expectsOutputToContain('Blogger is not connected.')->assertSuccessful();
        Http::assertNothingSent();
    }

    public function test_a_weekly_upload_goes_to_blogger_at_once_and_its_pictures_are_never_deleted_while_posted(): void
    {
        \Illuminate\Support\Facades\Storage::fake('public');
        $this->connected();
        $this->fakeGoogle();
        Http::fake(['www.googleapis.com/blogger/v3/blogs/*/posts/777*' => Http::response(['id' => '777000111'])]); // changing the post
        $this->actingAs($this->admin());
        $page = fn () => \Illuminate\Http\UploadedFile::fake()->image('page-01.jpg', 300, 420);


        $this->postJson(route('admin.digital-newspaper.bulk-store'), ['edition' => 'weekly', 'title' => 'INVESTMENT POINT WEEKLY - 21.02.2026 TO 27.02.2026',
            'week_from' => '2026-02-21', 'week_to' => '2026-02-27', 'status' => 'active', 'page_files' => [$page(), $page()]])->assertCreated();

        // Posted right after saving, dated by the edition, showing the pictures from the website
        $paper = DigitalNewspaper::sole();
        $this->assertSame('777000111', $paper->blogger_id);
        $first = $paper->pages;
        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), 'blogs/blog-weekly/posts/') && $r['published'] === '2026-02-21T06:00:00+05:30'
            && str_contains($r['content'], \Illuminate\Support\Facades\Storage::disk('public')->url($first[0])));

        // A new PDF: the Blogger post gets the new pages, and the old ones stay (the post showed them)
        $this->put(route('admin.digital-newspaper.update', $paper), ['title' => $paper->title, 'week_from' => '2026-02-21', 'week_to' => '2026-02-27', 'status' => 'active', 'page_files' => [$page()]])
            ->assertSessionHas('success', fn ($m) => str_contains($m, 'The Blogger post was updated too.'));
        Http::assertSent(fn (Request $r) => $r->method() === 'PATCH' && str_ends_with($r->url(), 'blogs/blog-weekly/posts/777000111'));
        \Illuminate\Support\Facades\Storage::disk('public')->assertExists($first);

        // Deleting the edition on the website keeps every picture a Blogger post may show
        $now = $paper->fresh()->pages;
        $this->delete(route('admin.digital-newspaper.destroy', $paper))->assertRedirect();
        \Illuminate\Support\Facades\Storage::disk('public')->assertExists(array_merge($first, $now));

        // An edition that never reached Blogger is deleted with its pictures
        $draft = DigitalNewspaper::create(['edition' => 'weekly', 'title' => 'Draft', 'published_on' => '2026-02-28', 'pages' => ['digital-newspaper/draft.jpg'], 'status' => 'inactive']);
        \Illuminate\Support\Facades\Storage::disk('public')->put('digital-newspaper/draft.jpg', 'x');
        $draft->delete();
        \Illuminate\Support\Facades\Storage::disk('public')->assertMissing('digital-newspaper/draft.jpg');
    }

    public function test_a_bulk_upload_is_dated_by_edition_so_the_blog_shows_the_newest_date_first(): void
    {
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-10-24 10:25', 'Asia/Kolkata'));
        $this->connected();
        $this->fakeGoogle();
        Http::fake(['www.googleapis.com/blogger/v3/blogs/blog-hindi/posts/*' => Http::response(['id' => 'x'])]);

        // Uploaded oldest first, as the bulk upload does
        foreach (['2026-10-01', '2026-10-23', '2026-10-24'] as $i => $date) {
            DigitalNewspaper::create(['edition' => 'hindi', 'title' => 'HINDI - INVESTMENT POINT DAILY - ' . $date, 'published_on' => $date, 'pages' => ["epaper/hindi/{$i}.jpg"], 'status' => 'active']);
        }
        $this->assertSame(3, app(BloggerPublisher::class)->publishPending()['posted']);

        $published = collect(Http::recorded())->map(fn ($pair) => $pair[0])->filter(fn (Request $r) => $r->method() === 'POST' && str_ends_with($r->url(), '/posts/'))
            ->map(fn (Request $r) => [$r['title'], $r['published']])->values()->all();
        $this->assertSame([
            ['HINDI - INVESTMENT POINT DAILY - 2026-10-01', '2026-10-01T06:00:00+05:30'],
            ['HINDI - INVESTMENT POINT DAILY - 2026-10-23', '2026-10-23T06:00:00+05:30'],
            ['HINDI - INVESTMENT POINT DAILY - 2026-10-24', now()->toRfc3339String()], // today's: now
        ], $published);

        // Posts made before this change are moved to their edition date; imported posts (no blogger_url) are left alone
        DigitalNewspaper::create(['edition' => 'hindi', 'blogger_id' => '5550001', 'title' => 'Imported', 'published_on' => '2026-10-02', 'pages' => ['https://blogger.googleusercontent.com/img/b/x/s0/1.jpg'], 'status' => 'active']);
        $this->artisan('blogger:publish', ['--fix-dates' => true])->expectsOutputToContain('Moved 3 post(s) to their edition date.')->assertSuccessful();
        Http::assertSent(fn (Request $r) => $r->method() === 'PATCH' && str_ends_with($r->url(), '/posts/777000111') && $r['published'] === '2026-10-01T06:00:00+05:30');
        Http::assertNotSent(fn (Request $r) => $r->method() === 'PATCH' && str_ends_with($r->url(), '/posts/5550001'));
    }
}
