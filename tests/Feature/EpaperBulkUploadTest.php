<?php

namespace Tests\Feature;

use App\Models\DigitalNewspaper;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Add Edition -> "Bulk upload": many PDFs at once for every E-Paper, like Media Articles */
class EpaperBulkUploadTest extends TestCase
{
    use RefreshDatabase, \Tests\FakesBloggerEmail;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->actingAs(User::forceCreate(['name' => 'Admin', 'username' => 'admin-epbulk', 'role' => 'admin', 'password' => Hash::make('x')]));
    }

    private static function pages(int $count = 2): array
    {
        return array_map(fn ($n) => UploadedFile::fake()->image("page-0{$n}.jpg", 300, 420), range(1, $count));
    }

    public function test_every_e_paper_has_the_bulk_upload_tab(): void
    {
        foreach (array_keys(DigitalNewspaper::EDITIONS) as $edition) {
            $this->get(route('admin.digital-newspaper.create', ['edition' => $edition]))->assertOk()
                ->assertSee('Bulk upload (many PDFs / folder)')->assertSee('id="bulkFolder"', false)
                ->assertSee('digital-newspaper\/bulk-store', false); // the upload address in the page's script
        }
        $this->get(route('admin.digital-newspaper.create', ['edition' => 'weekly']))->assertSee('Week From *');
        $this->get(route('admin.digital-newspaper.create', ['edition' => 'hindi']))->assertSee('Edition Date *');
    }

    public function test_each_pdf_is_emailed_to_its_blog_and_duplicates_are_skipped(): void
    {
        $this->bloggerConnected();
        $this->postJson(route('admin.digital-newspaper.bulk-store'), ['edition' => 'hindi', 'title' => 'INVESTMENT POINT - 23.10.2026',
            'published_on' => '2026-10-23', 'status' => 'active', 'page_files' => self::pages(3)])
            ->assertCreated()->assertJson(['title' => 'INVESTMENT POINT - 23.10.2026', 'pages' => 3, 'url' => 'https://hindi.blogspot.com/']);
        $this->assertCount(3, $this->sentEmails()->sole()->getAttachments());
        $this->assertSame(0, DigitalNewspaper::count()); // on its way to Blogger
        $this->assertSame([], Storage::disk('public')->allFiles());

        // While Blogger makes the post, the same edition is not sent twice
        $this->postJson(route('admin.digital-newspaper.bulk-check'), ['edition' => 'hindi', 'items' => [['key' => 'f1', 'title' => 'X', 'published_on' => '2026-10-23']]])
            ->assertJsonPath('duplicates.f1.title', 'INVESTMENT POINT - 23.10.2026 (on its way to Blogger)');
        $this->postJson(route('admin.digital-newspaper.bulk-store'), ['edition' => 'hindi', 'title' => 'INVESTMENT POINT - 23.10.2026',
            'published_on' => '2026-10-23', 'status' => 'active', 'page_files' => self::pages(1)])->assertStatus(409);

        // Once Blogger has made the post, the sent email goes from Gmail's Sent to the Trash
        $gmail = $this->fakeGmail([
            'A1 OK shreeinvestmentpoint@gmail.com authenticated (Success)',
            '* LIST (\HasNoChildren) "/" "INBOX"', '* LIST (\All \HasNoChildren) "/" "[Gmail]/All Mail"', '* LIST (\HasNoChildren \Sent) "/" "[Gmail]/Sent Mail"', '* LIST (\HasNoChildren \Trash) "/" "[Gmail]/Trash"', 'A2 OK Success',
            '* 3 EXISTS', 'A3 OK [READ-WRITE] [Gmail]/Sent Mail selected. (Success)',
            '* SEARCH 41 42', 'A4 OK SEARCH completed (Success)',
            'A5 OK [COPYUID 1 41:42 9:10] (Success)',
            '* BYE LOGOUT Requested', 'A6 OK 73 good day (Success)',
        ]);
        $this->bloggerMadePost('blog-hindi', '777', self::codeOf($this->sentEmails()->sole()), 3);
        app(\App\Services\EpaperByEmail::class)->run();
        $this->assertSame("A1 LOGIN \"shreeinvestmentpoint@gmail.com\" \"abcdefghijklmnop\"\r\nA2 LIST \"\" \"*\"\r\nA3 SELECT \"[Gmail]/Sent Mail\"\r\n"
            . "A4 UID SEARCH X-GM-RAW \"to:blogger.com IPUPLOAD\"\r\nA5 UID MOVE 41,42 \"[Gmail]/Trash\"\r\nA6 LOGOUT\r\n", self::gmailCommands($gmail));
        $paper = DigitalNewspaper::firstOrFail();
        $this->assertSame(['hindi', '2026-10-23', 3], [$paper->edition, $paper->published_on->toDateString(), count($paper->pages)]);

        // Before uploading, the list is checked: the same date (or title) of the same paper is already there
        $this->postJson(route('admin.digital-newspaper.bulk-check'), ['edition' => 'hindi', 'items' => [
            ['key' => 'f1', 'title' => 'Other title', 'published_on' => '2026-10-23'],
            ['key' => 'f2', 'title' => 'INVESTMENT POINT - 24.10.2026', 'published_on' => '2026-10-24'],
        ]])->assertOk()->assertJsonPath('duplicates.f1.title', 'INVESTMENT POINT - 23.10.2026')->assertJsonMissingPath('duplicates.f2');
        // Another paper's edition of that day is not a duplicate
        $this->postJson(route('admin.digital-newspaper.bulk-check'), ['edition' => 'gujarati', 'items' => [['key' => 'f1', 'title' => 'X', 'published_on' => '2026-10-23']]])
            ->assertJsonMissingPath('duplicates.f1');

        // Uploading it again is refused before anything is sent, unless "upload anyway"
        $this->postJson(route('admin.digital-newspaper.bulk-store'), ['edition' => 'hindi', 'title' => 'INVESTMENT POINT - 23.10.2026',
            'published_on' => '2026-10-23', 'status' => 'active', 'page_files' => self::pages(1)])->assertStatus(409);
        $this->assertCount(1, $this->sentEmails());
        $this->postJson(route('admin.digital-newspaper.bulk-store'), ['edition' => 'hindi', 'title' => 'INVESTMENT POINT - 23.10.2026',
            'published_on' => '2026-10-23', 'status' => 'active', 'force' => 1, 'page_files' => self::pages(1)])->assertCreated();
        // Blogger blocks emails that come too quickly: the next one goes a few minutes after the last
        $this->assertCount(1, $this->sentEmails());
        $this->travel(\App\Services\EpaperByEmail::GAP_MINUTES)->minutes();
        $this->assertSame(1, app(\App\Services\EpaperByEmail::class)->run()['sent']);
        $this->assertCount(2, $this->sentEmails());
    }

    public function test_a_daily_upload_needs_the_blogs_email_address_and_a_missing_post_is_reported(): void
    {
        $this->bloggerConnected();
        \App\Models\Setting::set('blogger_email_english', '');

        $this->get(route('admin.digital-newspaper.create', ['edition' => 'english']))->assertSee('address is not saved yet');
        $this->postJson(route('admin.digital-newspaper.bulk-store'), ['edition' => 'english', 'title' => 'ENG - INVESTMENT POINT DAILY - 24.10.2026',
            'published_on' => '2026-10-24', 'status' => 'active', 'page_files' => self::pages()])
            ->assertStatus(422)->assertJsonPath('errors.page_files.0', fn ($m) => str_contains($m, 'Site Settings → Blogger') && str_contains($m, 'Nothing was uploaded'));
        $this->assertCount(0, $this->sentEmails());

        // The address is saved in Site Settings (the secret word is not shown again)
        $this->post(route('admin.settings.blogger.emails'), ['blogger_email_english' => 'not-an-address@gmail.com'])->assertSessionHasErrors('blogger_email_english');
        $this->post(route('admin.settings.blogger.emails'), ['blogger_email_english' => 'investmentpoint.Eng123@blogger.com'])->assertSessionHasNoErrors();
        $this->assertSame('investmentpoint.eng123@blogger.com', \App\Services\EpaperByEmail::address('english'));
        $this->get(route('admin.settings.edit'))->assertSee('investmentpoint.••••@blogger.com')->assertDontSee('eng123');

        $this->get(route('admin.digital-newspaper.create', ['edition' => 'english']))->assertSee('straight to the English Blogger blog');
        $this->postJson(route('admin.digital-newspaper.bulk-store'), ['edition' => 'english', 'title' => 'ENG - INVESTMENT POINT DAILY - 24.10.2026',
            'published_on' => '2026-10-24', 'status' => 'active', 'page_files' => self::pages()])->assertCreated();
        $this->assertSame('investmentpoint.eng123@blogger.com', $this->sentEmails()->sole()->getTo()[0]->getAddress());

        // Blogger never makes the post: 5 minutes later it is cleared from the list, its pages deleted
        $this->travel(\App\Services\EpaperByEmail::WAIT_MINUTES + 1)->minutes();
        $this->assertSame(['finished' => 0, 'failed' => 1, 'sent' => 0], app(\App\Services\EpaperByEmail::class)->run());
        $this->get(route('admin.digital-newspaper.index', ['edition' => 'english']))->assertDontSee('Going to Blogger');
        $this->assertSame([], glob(config('blogger.outbox') . '/IPUPLOAD-*'));
        $this->assertSame(0, DigitalNewspaper::count());
    }

    public function test_a_bulk_upload_goes_out_one_by_one_and_one_not_posted_in_5_minutes_is_cleared(): void
    {
        $this->bloggerConnected();
        $outbox = config('blogger.outbox');
        foreach (['24', '25', '26'] as $day) {
            $this->postJson(route('admin.digital-newspaper.bulk-store'), ['edition' => 'english', 'title' => "ENG - INVESTMENT POINT DAILY - {$day}.02.2026",
                'published_on' => "2026-02-{$day}", 'status' => 'active', 'page_files' => self::pages()])->assertCreated();
        }
        // Only the first is emailed; the others wait in the outbox (their pages kept until Blogger has them)
        $this->assertSame(['ENG - INVESTMENT POINT DAILY - 24.02.2026'], $this->sentEmails()->map->getSubject()->all());
        $this->assertCount(3, glob($outbox . '/IPUPLOAD-*'));
        $this->get(route('admin.digital-newspaper.index', ['edition' => 'english']))->assertSee('Going to Blogger (3 left)')
            ->assertSee("waiting for Blogger's post", false)->assertSee('in the queue');

        // Blogger makes the first post: its pages leave the outbox; a few minutes later the next email goes
        $this->bloggerMadePost('blog-english', '9001', self::codeOf($this->sentEmails()->last()), 2);
        $this->assertSame(['finished' => 1, 'failed' => 0, 'sent' => 0], app(\App\Services\EpaperByEmail::class)->run());
        $this->assertCount(2, glob($outbox . '/IPUPLOAD-*'));
        $this->travel(\App\Services\EpaperByEmail::GAP_MINUTES)->minutes();
        app(\App\Services\EpaperByEmail::class)->run();
        $second = $this->sentEmails()->last();
        $this->assertSame('ENG - INVESTMENT POINT DAILY - 25.02.2026', $second->getSubject());

        // Blogger blocks it ("Message blocked"): no post within 5 minutes, so it is cleared and the third one goes
        $this->travel(\App\Services\EpaperByEmail::WAIT_MINUTES + 1)->minutes();
        $this->assertSame(['finished' => 0, 'failed' => 1, 'sent' => 1], app(\App\Services\EpaperByEmail::class)->run());
        $this->assertSame('ENG - INVESTMENT POINT DAILY - 26.02.2026', $this->sentEmails()->last()->getSubject());
        $this->bloggerMadePost('blog-english', '9003', self::codeOf($this->sentEmails()->last()), 2);
        app(\App\Services\EpaperByEmail::class)->run();

        // 24.02 and 26.02 are on the website; 25.02 is gone from the list (upload it again)
        $this->assertSame(['2026-02-26', '2026-02-24'], DigitalNewspaper::edition('english')->latestFirst()->get()->map(fn ($p) => $p->published_on->toDateString())->all());
        $this->assertSame([], glob($outbox . '/IPUPLOAD-*')); // nothing left on the server
        $this->get(route('admin.digital-newspaper.index', ['edition' => 'english']))->assertDontSee('Going to Blogger');
    }

    /**
     * Blogger's mail server blocks the emails after about five a day (owner, 27 Oct 2026): at most DAILY_LIMIT emails a day
     * (India time, all papers together); the rest wait in the queue and go on the next day by themselves.
     */
    public function test_at_most_five_emails_go_a_day_and_the_rest_the_next_day(): void
    {
        $this->bloggerConnected();
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-10-27 10:00', 'Asia/Kolkata'));
        foreach (range(1, 6) as $day) {
            $this->postJson(route('admin.digital-newspaper.bulk-store'), ['edition' => 'english', 'title' => sprintf('INVESTMENT POINT - %02d.03.2026', $day),
                'published_on' => sprintf('2026-03-%02d', $day), 'status' => 'active', 'page_files' => self::pages(1)])->assertCreated();
        }

        // Each one is posted by Blogger, then the next goes a minute later: five today
        foreach (range(1, 5) as $n) {
            $this->assertCount($n, $this->sentEmails());
            $this->bloggerMadePost('blog-english', (string) (9500 + $n), self::codeOf($this->sentEmails()->last()), 1);
            $this->travel(\App\Services\EpaperByEmail::GAP_MINUTES)->minutes();
            app(\App\Services\EpaperByEmail::class)->run();
        }
        $this->assertCount(5, $this->sentEmails());
        $this->assertSame(5, \App\Services\EpaperByEmail::sentToday());
        $this->travel(2)->hours();
        $this->assertSame(0, app(\App\Services\EpaperByEmail::class)->run()['sent']); // still today: the sixth waits
        $this->get(route('admin.digital-newspaper.index', ['edition' => 'english']))
            ->assertSee('5 of 5 sent today')->assertSee("today's 5 emails are sent, it goes tomorrow by itself", false);

        // Tomorrow (India time) it goes by itself
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-10-28 00:01', 'Asia/Kolkata'));
        $this->assertSame(1, app(\App\Services\EpaperByEmail::class)->run()['sent']);
        $this->assertSame('INVESTMENT POINT - 06.03.2026', $this->sentEmails()->last()->getSubject());
        $this->assertSame(1, \App\Services\EpaperByEmail::sentToday());
    }

    /**
     * Gmail's "Message blocked" reply comes within a minute: the next edition goes at once instead of after WAIT_MINUTES.
     * The blocked one stays on the list (Blogger has made a post after such a reply) and is finished if its post comes.
     */
    public function test_a_blocked_email_lets_the_next_edition_go_at_once(): void
    {
        $this->bloggerConnected();
        foreach (['24', '25'] as $day) {
            $this->postJson(route('admin.digital-newspaper.bulk-store'), ['edition' => 'english', 'title' => "ENG - INVESTMENT POINT DAILY - {$day}.02.2026",
                'published_on' => "2026-02-{$day}", 'status' => 'active', 'page_files' => self::pages()])->assertCreated();
        }
        $first = $this->sentEmails()->sole();
        $messageId = trim($first->getHeaders()->get('Message-ID')->getBodyAsString(), '<>');

        // A minute later Gmail has its "blocked" reply
        $this->travel(\App\Services\EpaperByEmail::GAP_MINUTES)->minutes();
        $gmail = $this->fakeGmail([
            'A1 OK authenticated (Success)',
            '* LIST (\HasNoChildren) "/" "INBOX"', '* LIST (\All \HasNoChildren) "/" "[Gmail]/All Mail"', '* LIST (\HasNoChildren \Sent) "/" "[Gmail]/Sent Mail"', '* LIST (\HasNoChildren \Trash) "/" "[Gmail]/Trash"', 'A2 OK Success',
            'A3 OK [READ-ONLY] [Gmail]/All Mail selected. (Success)',
            '* SEARCH 263693', 'A4 OK SEARCH completed (Success)',
            '* BYE LOGOUT Requested', 'A5 OK 73 good day (Success)',
        ]);
        $this->assertSame(['finished' => 0, 'failed' => 0, 'sent' => 1], app(\App\Services\EpaperByEmail::class)->run());
        $this->assertStringContainsString('A4 UID SEARCH HEADER In-Reply-To "' . $messageId . '"', self::gmailCommands($gmail));
        $this->assertSame(['ENG - INVESTMENT POINT DAILY - 24.02.2026', 'ENG - INVESTMENT POINT DAILY - 25.02.2026'], $this->sentEmails()->map->getSubject()->all());
        $this->get(route('admin.digital-newspaper.index', ['edition' => 'english']))->assertSee('Google blocked the email; the next one goes now');

        // Blogger makes the "blocked" post after all: it is finished like any other
        $this->bloggerMadePost('blog-english', '9101', self::codeOf($first), 2);
        $this->assertSame(1, app(\App\Services\EpaperByEmail::class)->run()['finished']);
        $this->assertSame(['ENG - INVESTMENT POINT DAILY - 24.02.2026'], DigitalNewspaper::pluck('title')->all());
    }

    /**
     * The blog check copied the emailed post to the website a few seconds before the email queue finished it (on live:
     * "Duplicate entry ... digital_newspapers_blogger_id_unique"): that row becomes the edition, no error, no second copy
     */
    public function test_an_emailed_edition_already_copied_from_the_blog_is_finished_without_a_duplicate(): void
    {
        $this->bloggerConnected();
        $this->postJson(route('admin.digital-newspaper.bulk-store'), ['edition' => 'english', 'title' => 'ENG - INVESTMENT POINT DAILY - 24.02.2026',
            'published_on' => '2026-02-24', 'status' => 'active', 'page_files' => self::pages()])->assertCreated();
        $this->bloggerMadePost('blog-english', '931075558986410715', self::codeOf($this->sentEmails()->sole()), 2);
        $copied = DigitalNewspaper::forceCreate(['edition' => 'english', 'blogger_id' => '931075558986410715', 'title' => 'ENG - INVESTMENT POINT DAILY - 24.02.2026',
            'published_on' => '2026-02-24', 'pages' => ['https://blogger.googleusercontent.com/img/b/x/s0/old.jpg'], 'status' => 'active']);

        $this->assertSame(['finished' => 1, 'failed' => 0, 'sent' => 0], app(\App\Services\EpaperByEmail::class)->run());

        $paper = DigitalNewspaper::sole();
        $this->assertSame($copied->id, $paper->id);
        $this->assertCount(2, $paper->pages); // the pages of the Blogger post, in page order
        $this->assertNotNull($paper->blogger_url);
        $this->assertSame([], \App\Services\EpaperByEmail::waiting());
    }

    /**
     * "Get all editions from Blogger" reads the whole blog (more than one feed page) and adds each post the website does
     * not have, once: not a post already here, not the same edition posted twice, not one already uploaded here.
     */
    public function test_get_all_editions_from_blogger_copies_every_post_once(): void
    {
        $this->bloggerConnected();
        $img = fn ($id) => '<img src="https://blogger.googleusercontent.com/img/b/x/s320/' . $id . '.jpg">';
        $entry = fn ($id, $title, $html = null) => ['id' => ['$t' => 'tag:blogger.com,1999:blog-1.post-' . $id], 'title' => ['$t' => $title],
            'published' => ['$t' => '2025-01-01T09:00:00+05:30'], 'content' => ['$t' => $html ?? $img($id)]];

        // 155 posts, newest first: 150 numbered editions on the first feed page, then 5 more
        $posts = [];
        foreach (range(1, 150) as $n) {
            $posts[] = $entry((string) (1000 + $n), 'INVESTMENT POINT - ' . Carbon::parse('2026-03-01')->subDays($n)->format('d.m.Y'));
        }
        $posts[] = $entry('2001', 'INVESTMENT POINT - 09.04.2025');
        $posts[] = $entry('2002', 'INVESTMENT POINT  - 09.04.2025'); // the same edition posted twice (extra space)
        $posts[] = $entry('2003', 'INVESTMENT POINT - 08.04.2025');  // already on the website, uploaded here
        $posts[] = $entry('2004', 'TRADING PERFORMANCE - 08.04.2025'); // another post of that date: not a copy
        $posts[] = $entry('2005', 'GOOD MORNING', '<p>No pictures</p>');
        DigitalNewspaper::create(['edition' => 'hindi', 'title' => 'Investment Point - 08.04.2025', 'published_on' => '2025-04-08', 'pages' => ['epaper/hindi/1.jpg'], 'status' => 'active']);
        // Imported twice before: one copy goes
        foreach (['3001', '3002'] as $id) {
            DigitalNewspaper::forceCreate(['edition' => 'hindi', 'blogger_id' => $id, 'title' => 'INVESTMENT POINT - 01.01.2020', 'published_on' => '2020-01-01',
                'pages' => ['https://blogger.googleusercontent.com/img/b/x/s0/' . $id . '.jpg'], 'status' => 'active']);
        }

        Http::fake(['markettrendhindi-ipoint.blogspot.com/*' => function (\Illuminate\Http\Client\Request $r) use ($posts) {
            parse_str((string) parse_url($r->url(), PHP_URL_QUERY), $q);

            return Http::response(['feed' => ['openSearch$totalResults' => ['$t' => (string) count($posts)],
                'entry' => array_slice($posts, (int) $q['start-index'] - 1, (int) $q['max-results'])]]);
        }]);

        $this->post(route('admin.digital-newspaper.from-blogger'), ['edition' => 'hindi'])
            ->assertRedirect(route('admin.digital-newspaper.index', ['edition' => 'hindi']))
            ->assertSessionHas('success', '152 edition(s) brought in from the Hindi blog. 2 post(s) left out: the same edition (date and title) is already here. 1 extra copy(ies) of an edition removed from the website list.');

        $this->assertSame(1 + 1 + 152, DigitalNewspaper::edition('hindi')->count());
        $this->assertSame(1, DigitalNewspaper::whereDate('published_on', '2025-04-09')->count());
        $this->assertSame(['Investment Point - 08.04.2025', 'TRADING PERFORMANCE - 08.04.2025'], DigitalNewspaper::whereDate('published_on', '2025-04-08')->orderBy('id')->pluck('title')->all());
        $this->assertSame(['3001'], DigitalNewspaper::whereDate('published_on', '2020-01-01')->pluck('blogger_id')->all());
        $this->assertNull(DigitalNewspaper::where('title', 'GOOD MORNING')->first());
        // Newest date first on the list
        $this->get(route('admin.digital-newspaper.index', ['edition' => 'hindi']))->assertSeeInOrder(['INVESTMENT POINT - 28.02.2026', 'INVESTMENT POINT - 27.02.2026']);
        // Copying old posts never changes them on Blogger
        Http::assertNotSent(fn ($r) => $r->method() === 'PATCH');

        // Clicked again: nothing new
        $this->post(route('admin.digital-newspaper.from-blogger'), ['edition' => 'hindi'])
            ->assertSessionHas('success', 'The website already has every edition of the Hindi blog. 2 post(s) left out: the same edition (date and title) is already here.');
        $this->assertSame(154, DigitalNewspaper::edition('hindi')->count());
    }

    /**
     * The admin posts the pages in Blogger today and clicks "Get all editions from Blogger" at
     * once. That post gets its edition date on the blog (newest edition first); old posts are never changed.
     */
    public function test_a_post_just_made_in_blogger_gets_its_edition_date_when_synced(): void
    {
        $this->bloggerConnected();
        \Illuminate\Support\Facades\Cache::put('blogger.access_token', 'ya29.token', 3600);
        $entry = fn ($id, $title, $posted) => ['id' => ['$t' => 'tag:blogger.com,1999:blog-1.post-' . $id], 'title' => ['$t' => $title],
            'published' => ['$t' => $posted], 'content' => ['$t' => '<img src="https://blogger.googleusercontent.com/img/b/x/s1600/' . $id . '-page-01.jpg">']];
        Http::fake(['markettrendhindi-ipoint.blogspot.com/*' => Http::response(['feed' => ['openSearch$totalResults' => ['$t' => '2'], 'entry' => [
            $entry('7001', 'HINDI - INVESTMENT POINT DAILY - 23.10.2026', now()->subHour()->toIso8601String()), // posted just now for 23.10
            $entry('7002', 'INVESTMENT POINT - 02.03.2026', '2026-03-01T18:00:00+05:30'), // an old post, made the evening before
        ]]])]);

        $this->post(route('admin.digital-newspaper.from-blogger'), ['edition' => 'hindi'])->assertSessionHas('success', '2 edition(s) brought in from the Hindi blog.');

        Http::assertSent(fn ($r) => $r->method() === 'PATCH' && str_ends_with($r->url(), 'blogs/blog-hindi/posts/7001') && $r['published'] === '2026-10-23T06:00:00+05:30');
        Http::assertNotSent(fn ($r) => $r->method() === 'PATCH' && str_ends_with($r->url(), '/posts/7002'));
        $this->assertSame(['2026-10-23', '2026-03-02'], DigitalNewspaper::edition('hindi')->latestFirst()->get()->map(fn ($p) => $p->published_on->toDateString())->all());
    }

    /** The "For Blogger (no email)" tab is gone (owner, 27 Oct 2026): the Add Edition page has the two email tabs only */
    public function test_the_daily_add_edition_page_has_no_for_blogger_tab(): void
    {
        $this->bloggerConnected();
        $this->get(route('admin.digital-newspaper.create', ['edition' => 'gujarati']))->assertOk()
            ->assertSeeInOrder(['Single PDF (by email)', 'Bulk upload (many PDFs / folder) (by email)'])
            ->assertDontSee('For Blogger (no email)')->assertDontSee('id="fb_pdfs"', false)->assertDontSee('Save pictures');
        // Editions made in Blogger still come in from the list
        $this->get(route('admin.digital-newspaper.index', ['edition' => 'gujarati']))->assertSee('Get all editions from Blogger')->assertSee('Open Blogger');
    }

    public function test_a_daily_post_made_in_blogger_later_gets_its_edition_date_on_the_blog(): void
    {
        \App\Models\Setting::set('blogger_client_id', '1234567890-abcdef123.apps.googleusercontent.com');
        \App\Models\Setting::set('blogger_client_secret', \Illuminate\Support\Facades\Crypt::encryptString('GOCSPX-test-secret-0123456789'));
        \App\Models\Setting::set('blogger_refresh_token', \Illuminate\Support\Facades\Crypt::encryptString('1//refresh-token'));
        \App\Models\Setting::set('blogger_blogs', json_encode(['english' => ['id' => 'blog-english', 'name' => 'English', 'url' => 'https://markettrendeng-ipoint.blogspot.com/']]));
        \Illuminate\Support\Facades\Cache::put('blogger.access_token', 'ya29.token', 3600);
        $entry = fn ($id, $title, $posted) => ['id' => ['$t' => 'tag:blogger.com,1999:blog-1.post-' . $id], 'title' => ['$t' => $title],
            'published' => ['$t' => $posted], 'content' => ['$t' => '<img src="https://blogger.googleusercontent.com/img/b/x/s320/' . $id . '.jpg">']];
        \Illuminate\Support\Facades\Http::swap(new \Illuminate\Http\Client\Factory());
        \Illuminate\Support\Facades\Http::fake([
            'markettrendeng-ipoint.blogspot.com/*' => \Illuminate\Support\Facades\Http::response(['feed' => ['openSearch$totalResults' => ['$t' => '2'], 'entry' => [
                $entry('901', 'ENG - INVESTMENT POINT DAILY - 24.02.2026', '2026-10-24T13:05:00+05:30'), // emailed today for February
                $entry('902', 'ENG - INVESTMENT POINT DAILY - 24.10.2026', '2026-10-24T07:00:00+05:30'), // posted on its own day
            ]]]),
            'www.googleapis.com/*' => \Illuminate\Support\Facades\Http::response(['id' => '901']),
        ]);

        $this->artisan('newspaper:import', ['--edition' => 'english'])->expectsOutputToContain('Blogger date set to 24.02.2026')->assertSuccessful();
        \Illuminate\Support\Facades\Http::assertSent(fn ($r) => $r->method() === 'PATCH' && str_ends_with($r->url(), 'blogs/blog-english/posts/901') && $r['published'] === '2026-02-24T06:00:00+05:30');
        \Illuminate\Support\Facades\Http::assertNotSent(fn ($r) => $r->method() === 'PATCH' && str_ends_with($r->url(), '/posts/902'));
        $this->assertSame(2, DigitalNewspaper::count());
    }

    public function test_weekly_editions_take_the_week_from_the_file_name_fields(): void
    {
        $this->postJson(route('admin.digital-newspaper.bulk-store'), ['edition' => 'weekly', 'title' => 'INVESTMENT POINT WEEKLY - 17.10.2026 TO 23.10.2026',
            'week_from' => '2026-10-17', 'week_to' => '2026-10-23', 'status' => 'active', 'page_files' => self::pages()])->assertCreated();
        $paper = DigitalNewspaper::firstOrFail();
        $this->assertSame(['weekly', '2026-10-17', '2026-10-23', '2026-10-17'], [$paper->edition, $paper->week_from->toDateString(), $paper->week_to->toDateString(), $paper->published_on->toDateString()]);

        $this->postJson(route('admin.digital-newspaper.bulk-check'), ['edition' => 'weekly', 'items' => [['key' => 'f1', 'title' => 'Y', 'published_on' => '2026-10-17']]])
            ->assertJsonPath('duplicates.f1.title', 'INVESTMENT POINT WEEKLY - 17.10.2026 TO 23.10.2026');
        // Missing PDF pages or a bad week are refused like the single form
        $this->postJson(route('admin.digital-newspaper.bulk-store'), ['edition' => 'weekly', 'title' => 'Z', 'week_from' => '2026-10-24', 'status' => 'active'])
            ->assertStatus(422)->assertJsonValidationErrors('page_files');
    }
}
