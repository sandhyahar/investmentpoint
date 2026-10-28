<?php

namespace Tests\Feature;

use App\Models\DigitalNewspaper;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DigitalNewspaperTest extends TestCase
{
    use RefreshDatabase;

    private const IMG = 'https://blogger.googleusercontent.com/img/b/abc/s1746/11.jpg';

    private function admin(): User
    {
        return User::forceCreate([
            'name' => 'Administrator', 'username' => 'admin-dn', 'email' => 'admin-dn@example.com',
            'role' => 'admin', 'password' => bcrypt('secret'),
        ]);
    }

    private function paper(array $attrs = []): DigitalNewspaper
    {
        return DigitalNewspaper::create($attrs + [
            'title' => 'INVESTMENT POINT WEEKLY - 19.09.2026 TO 25.09.2026',
            'week_from' => '2026-09-19', 'week_to' => '2026-09-25', 'published_on' => '2026-09-19',
            'pages' => [self::IMG, 'https://blogger.googleusercontent.com/img/b/abc/s1774/22.jpg'], 'status' => 'active',
        ]);
    }

    public function test_blog_post_is_parsed_into_week_and_pages(): void
    {
        $html = '<a href="' . self::IMG . '"><img src="https://blogger.googleusercontent.com/img/b/abc/w474-h640/11.jpg"></a>';
        $post = DigitalNewspaper::parsePost('INVESTMENT POINT WEEKLY -  13.09.2025 TO 19.09.2025', $html, '2025-09-13T10:00:00+05:30');

        $this->assertSame('INVESTMENT POINT WEEKLY - 13.09.2025 TO 19.09.2025', $post['title']);
        $this->assertSame(['2025-09-13', '2025-09-19', '2025-09-13'], [$post['week_from'], $post['week_to'], $post['published_on']]);
        $this->assertSame(['https://blogger.googleusercontent.com/img/b/abc/s0/11.jpg'], $post['pages']);

        // Specials without a week use the post date
        $special = DigitalNewspaper::parsePost('DEEPAWALI - 2023', $html, '2023-11-13T09:00:00+05:30');
        $this->assertNull($special['week_from']);
        $this->assertSame('2023-11-13', $special['published_on']);
    }

    public function test_list_page_shows_editions_years_menu_and_disclaimer(): void
    {
        $this->paper();
        $this->paper(['title' => 'Hidden edition', 'status' => 'inactive']);

        $this->get(route('digital-newspaper.index'))->assertOk()
            ->assertSee('INVESTMENT POINT WEEKLY - 19.09.2026 TO 25.09.2026')
            ->assertDontSee('Hidden edition')
            ->assertSee('2 pages')
            ->assertSee('Registration granted by RNI / SEBI certification')
            ->assertSee(route('digital-newspaper.index'), false); // E-Paper menu + tab

        $this->get(route('digital-newspaper.index', ['q' => '21.09.2026']))->assertOk()->assertSee('19.09.2026 TO 25.09.2026');
        $this->get(route('digital-newspaper.index', ['year' => 2019]))->assertOk()->assertSee('No editions found');
    }

    public function test_blogger_total_pageviews_are_read_and_shown(): void
    {
        \Illuminate\Support\Facades\Http::swap(new \Illuminate\Http\Client\Factory());
        \Illuminate\Support\Facades\Http::fake([
            'weeklynewspaper-ipoint.blogspot.com/b/stats*' => \Illuminate\Support\Facades\Http::response(['total' => 67029, 'sparklineData' => [[0, 97], [1, 36], [2, 75]]]),
            'weeklynewspaper-ipoint.blogspot.com/*' => \Illuminate\Support\Facades\Http::response("<script>{'statsUrl': '//weeklynewspaper-ipoint.blogspot.com/b/stats?style\\x3dBLACK\\x26token\\x3dabc'}</script>"),
        ]);

        $stats = app(\App\Services\BloggerStats::class)->refresh(config('epaper.weekly_blog'));
        $this->assertSame(['total' => 67029, 'daily' => [97, 36, 75]], $stats);

        $this->paper();
        $this->get(route('digital-newspaper.index'))->assertOk()->assertSee('Total Pageviews')->assertSee('67,029');
    }

    public function test_edition_page_shows_every_page_and_neighbours(): void
    {
        $older = $this->paper(['title' => 'Older week', 'week_from' => '2026-09-12', 'published_on' => '2026-09-12']);
        $paper = $this->paper();

        $this->get($paper->url)->assertOk()
            ->assertSee('Page 2 of 2')
            ->assertSee('https://blogger.googleusercontent.com/img/b/abc/w1200/11.jpg', false)
            ->assertSee('Older week');
        $this->get($older->url)->assertOk()->assertSee('Newer edition');
        $this->get(DigitalNewspaper::create(['title' => 'x', 'published_on' => '2026-01-01', 'pages' => [self::IMG], 'status' => 'inactive'])->url)->assertNotFound();
    }

    public function test_admin_can_add_edit_and_delete_an_edition(): void
    {
        Storage::fake('public');
        $this->actingAs($this->admin());

        // The admin form turns the PDF into one JPEG per page in the browser and uploads those
        // (a real 1×1 JPEG: UploadedFile::fake()->image() needs the GD extension)
        $jpeg = fn ($name) => UploadedFile::fake()->createWithContent($name, base64_decode('/9j/4AAQSkZJRgABAQEASABIAAD/2wBDAP//////////////////////////////////////////////////////////////////////////////////////wgALCAABAAEBAREA/8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQABPxA='));
        $this->post(route('admin.digital-newspaper.store'), [
            'title' => 'New week', 'week_from' => '2026-09-26', 'week_to' => '2026-10-02', 'status' => 'active',
            'page_files' => [$jpeg('page-01.jpg'), $jpeg('page-02.jpg')],
        ])->assertRedirect(route('admin.digital-newspaper.index', ['edition' => 'weekly']));

        $paper = DigitalNewspaper::firstOrFail();
        $this->assertCount(2, $paper->pages);
        $this->assertSame('2026-09-26', $paper->published_on->toDateString());
        $uploaded = $paper->pages;
        Storage::disk('public')->assertExists($uploaded);

        $this->get(route('admin.digital-newspaper.index'))->assertOk()->assertSee('New week');
        $this->get(route('admin.digital-newspaper.edit', $paper))->assertOk()->assertSee(asset('storage/' . $uploaded[1]), false);

        // Saved without a new PDF -> the pages stay
        $this->put(route('admin.digital-newspaper.update', $paper), ['title' => 'Renamed', 'status' => 'active', 'week_from' => '2026-09-26'])
            ->assertRedirect(route('admin.digital-newspaper.index', ['edition' => 'weekly']));
        $this->assertSame($uploaded, $paper->fresh()->pages);

        // A new PDF -> its pages replace all the old ones, and the old files are deleted
        $this->put(route('admin.digital-newspaper.update', $paper), ['title' => 'Renamed', 'status' => 'active', 'week_from' => '2026-09-26', 'page_files' => [$jpeg('page-01.jpg')]])
            ->assertRedirect(route('admin.digital-newspaper.index', ['edition' => 'weekly']));
        $this->assertCount(1, $paper->fresh()->pages);
        Storage::disk('public')->assertMissing($uploaded);

        $this->post(route('admin.digital-newspaper.store'), ['title' => 'No pages', 'status' => 'active'])->assertSessionHasErrors('page_files');

        $this->delete(route('admin.digital-newspaper.destroy', $paper))->assertRedirect();
        $this->assertSame(0, DigitalNewspaper::count());
    }
}
