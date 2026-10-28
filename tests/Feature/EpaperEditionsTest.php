<?php

namespace Tests\Feature;

use App\Models\DigitalNewspaper;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** E-Paper -> Gujarati / Hindi / English: daily editions stored with the Weekly News Paper, managed in Admin -> E-Paper. */
class EpaperEditionsTest extends TestCase
{
    use RefreshDatabase, \Tests\FakesBloggerEmail;

    private const IMG = 'https://blogger.googleusercontent.com/img/b/abc/s1746/1.jpg';

    private function admin(): User
    {
        return User::forceCreate([
            'name' => 'Administrator', 'username' => 'admin-ep', 'email' => 'admin-ep@example.com',
            'role' => 'admin', 'password' => bcrypt('secret'),
        ]);
    }

    private function edition(string $edition, string $date, array $attrs = []): DigitalNewspaper
    {
        return DigitalNewspaper::create($attrs + [
            'edition' => $edition, 'title' => strtoupper($edition) . ' - INVESTMENT POINT DAILY - ' . $date,
            'published_on' => \Carbon\Carbon::createFromFormat('d.m.Y', $date)->toDateString(), 'pages' => [self::IMG], 'status' => 'active',
        ]);
    }

    public function test_each_language_page_lists_only_its_own_editions(): void
    {
        $guj = $this->edition('gujarati', '01.10.2026', ['blogger_id' => '4127048657253702504']);
        $older = $this->edition('gujarati', '30.09.2026');
        $this->edition('hindi', '01.10.2026');
        DigitalNewspaper::create(['title' => 'INVESTMENT POINT WEEKLY - 19.09.2026 TO 25.09.2026', 'published_on' => '2026-09-19', 'pages' => [self::IMG], 'status' => 'active']);

        $this->get(route('epaper.index', 'gujarati'))->assertOk()
            ->assertSee('GUJARATI - INVESTMENT POINT DAILY - 01.10.2026')->assertSee('2 editions')
            ->assertDontSee('HINDI - INVESTMENT POINT DAILY')->assertDontSee('INVESTMENT POINT WEEKLY');
        $this->get(route('epaper.index', ['language' => 'gujarati', 'q' => '30.09.2026']))->assertOk()->assertSee('1 editions');
        $this->get(route('epaper.index', 'english'))->assertOk()->assertSee('English E-Paper coming soon');
        // The weekly page still shows only the weekly paper
        $this->get(route('digital-newspaper.index'))->assertOk()->assertSee('INVESTMENT POINT WEEKLY')->assertDontSee('GUJARATI - INVESTMENT POINT DAILY');

        $this->get($guj->url)->assertOk()->assertSee('ગુજરાતી ઈ-પેપર')->assertSee('Older edition')->assertSee($older->url, false);
        // Old links with the blogspot post id open the edition; another language's id is not found
        $this->get('/e-paper/gujarati/4127048657253702504')->assertRedirect($guj->url);
        $this->get('/e-paper/hindi/' . $guj->id)->assertNotFound();
    }

    public function test_admin_adds_a_daily_edition_from_pdf_pages(): void
    {
        Storage::fake('public');
        $this->bloggerConnected();
        $admin = $this->admin();
        $jpeg = fn ($n) => UploadedFile::fake()->createWithContent($n, base64_decode('/9j/4AAQSkZJRgABAQEASABIAAD/2wBDAP//////////////////////////////////////////////////////////////////////////////////////wgALCAABAAEBAREA/8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQABPxA='));

        $this->actingAs($admin)->get(route('admin.digital-newspaper.index', ['edition' => 'gujarati']))->assertOk()
            ->assertSee('Gujarati E-Paper')->assertSee('Add Gujarati Edition') // both ways: upload here (by email) or bring in from Blogger
            ->assertSee('Get all editions from Blogger')->assertSee('https://www.blogger.com/blog/posts/blog-gujarati');
        $this->get(route('admin.digital-newspaper.create', ['edition' => 'gujarati']))->assertOk()
            ->assertSee('placeholder="INVESTMENT POINT - 01.10.2026"', false)->assertDontSee('name="week_from"', false)
            // The title is made from the file name's date ("INVESTMENT POINT - dd.mm.yyyy"), in the single form and the bulk list
            ->assertSee('const DAILY_TITLE = true', false)->assertSee("const dailyTitle = date => 'INVESTMENT POINT - '", false);
        $this->get(route('admin.digital-newspaper.create', ['edition' => 'weekly']))->assertSee('const DAILY_TITLE = false', false);
        $this->get(route('admin.digital-newspaper.create', ['edition' => 'gujarati']))->assertOk();

        // A daily edition needs its date
        $this->post(route('admin.digital-newspaper.store'), ['edition' => 'gujarati', 'title' => 'No date', 'status' => 'active', 'page_files' => [$jpeg('page-01.jpg')]])
            ->assertSessionHasErrors('published_on');

        $this->post(route('admin.digital-newspaper.store'), [
            'edition' => 'gujarati', 'title' => 'GUJ - INVESTMENT POINT DAILY - 01.10.2026', 'published_on' => '2026-10-01',
            'week_from' => '2026-09-01', 'status' => 'active', 'page_files' => [$jpeg('page-01.jpg'), $jpeg('page-02.jpg')],
        ])->assertRedirect(route('admin.digital-newspaper.index', ['edition' => 'gujarati']))->assertSessionHas('success', fn ($m) => str_contains($m, 'Added to the Gujarati blog'));

        // Emailed to the Gujarati blog's secret address, the pages attached; nothing on the server or in the database yet
        $email = $this->sentEmails()->sole();
        $this->assertSame('investmentpoint.gujaratisecret@blogger.com', $email->getTo()[0]->getAddress());
        $this->assertSame('GUJ - INVESTMENT POINT DAILY - 01.10.2026', $email->getSubject());
        $this->assertSame(['page-01.jpg', 'page-02.jpg'], array_map(fn ($a) => $a->getFilename(), $email->getAttachments()));
        $this->assertSame(0, DigitalNewspaper::count());
        $this->assertSame([], Storage::disk('public')->allFiles());
        $this->get(route('admin.digital-newspaper.index', ['edition' => 'gujarati']))->assertSee('waiting for Blogger');

        // Blogger makes the post; the every-minute check gives it the edition date and layout, and lists it on the website
        $this->bloggerMadePost('blog-gujarati', '5551234', self::codeOf($email), 2);
        $this->assertSame(['finished' => 1, 'failed' => 0, 'sent' => 0], app(\App\Services\EpaperByEmail::class)->run());
        \Illuminate\Support\Facades\Http::assertSent(fn ($r) => $r->method() === 'PATCH' && str_ends_with($r->url(), 'blogs/blog-gujarati/posts/5551234')
            && $r['published'] === '2026-10-01T06:00:00+05:30' && !str_contains($r['content'], 'IPUPLOAD')
            && str_contains($r['content'], 'src="https://blogger.googleusercontent.com/img/b/R29vZ2xl/AVvX55512342/s0/page-02.jpg"'));

        $paper = DigitalNewspaper::firstOrFail();
        $this->assertSame('gujarati', $paper->edition);
        $this->assertSame('2026-10-01', $paper->published_on->toDateString());
        $this->assertNull($paper->week_from); // daily papers have no week
        $this->assertSame('5551234', $paper->blogger_id);
        // Blogger's own pictures, like a post made by hand
        $this->assertSame(['https://blogger.googleusercontent.com/img/b/R29vZ2xl/AVvX55512341/s0/page-01.jpg', 'https://blogger.googleusercontent.com/img/b/R29vZ2xl/AVvX55512342/s0/page-02.jpg'], $paper->pages);
        $this->get(route('admin.digital-newspaper.index', ['edition' => 'gujarati']))->assertDontSee('waiting for Blogger');
        $this->get(route('epaper.show', ['language' => 'gujarati', 'id' => $paper->id]))->assertOk()->assertSee('2 pages');

        // Edit keeps it a Gujarati edition and goes back to the Gujarati list
        $this->get(route('admin.digital-newspaper.edit', $paper))->assertOk()->assertSee('Edit Gujarati Edition');
        $this->put(route('admin.digital-newspaper.update', $paper), ['title' => 'Renamed', 'published_on' => '2026-10-02', 'status' => 'active'])
            ->assertRedirect(route('admin.digital-newspaper.index', ['edition' => 'gujarati']));
        $this->assertSame('gujarati', $paper->fresh()->edition);
        \Illuminate\Support\Facades\Http::assertSent(fn ($r) => $r->method() === 'PATCH' && str_ends_with($r->url(), 'blogs/blog-gujarati/posts/5551234') && $r['title'] === 'Renamed');
        // Its pages are on Blogger: not replaced from here
        $this->put(route('admin.digital-newspaper.update', $paper), ['title' => 'Renamed', 'published_on' => '2026-10-02', 'status' => 'active', 'page_files' => [$jpeg('page-01.jpg')]])
            ->assertSessionHasErrors('page_files');

        // Bulk delete only touches the paper being managed
        $weekly = DigitalNewspaper::create(['title' => 'Weekly', 'published_on' => '2026-09-19', 'pages' => [self::IMG], 'status' => 'active']);
        $this->post(route('admin.digital-newspaper.bulk-delete'), ['edition' => 'gujarati', 'ids' => [$paper->id, $weekly->id]])
            ->assertRedirect(route('admin.digital-newspaper.index', ['edition' => 'gujarati']));
        $this->assertSame(['weekly'], DigitalNewspaper::pluck('edition')->all());
    }

    public function test_sidebar_groups_e_paper_and_settings(): void
    {
        $this->actingAs($this->admin())->get(route('admin.digital-newspaper.index', ['edition' => 'hindi']))->assertOk()
            ->assertSeeInOrder(['Dashboard', 'Market Trend', 'E-Paper', 'Gujarati', 'Hindi', 'English', 'Weekly News Paper'])
            ->assertSeeInOrder(['Settings', 'Site Settings', 'My Account', 'Legal Pages', 'Disclaimer Questions']);
    }

    public function test_daily_blog_posts_are_parsed_by_their_date(): void
    {
        $post = DigitalNewspaper::parsePost('GUJ - INVESTMENT POINT DAILY - 01.10.2026', '<img src="https://blogger.googleusercontent.com/img/b/x/w400-h640/1.jpg">', '2026-10-01T08:00:00+05:30', false);

        $this->assertSame('2026-10-01', $post['published_on']);
        $this->assertNull($post['week_from']);
        $this->assertSame(['https://blogger.googleusercontent.com/img/b/x/s0/1.jpg'], $post['pages']);
    }
}
