<?php

namespace Tests\Feature;

use App\Models\MediaArticle;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Media Articles are added as a PDF: every page becomes a page image (like the e-paper) */
class MediaArticlePdfTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->actingAs(User::forceCreate(['name' => 'Admin', 'username' => 'admin-ma', 'role' => 'admin', 'password' => Hash::make('x')]));
    }

    private static function page(string $name): UploadedFile
    {
        return UploadedFile::fake()->image($name, 1200, 1700);
    }

    private function fields(array $extra = []): array
    {
        return ['title' => 'INVESTMENT POINT / PAGDANDI - 20.02.2026', 'column_name' => 'INVESTMENT POINT', 'publication' => 'PAGDANDI',
            'published_on' => '2026-02-20', 'status' => 'active'] + $extra;
    }

    public function test_the_form_has_a_pdf_upload_and_no_image_link_or_description(): void
    {
        $this->get(route('admin.media-articles.create'))->assertOk()
            ->assertSee('Article PDF')->assertSee('id="np_pdf"', false)->assertSee('name="page_files[]"', false)
            ->assertDontSee('name="image_url"', false)->assertDontSee('name="image_file"', false)->assertDontSee('name="description"', false);
    }

    public function test_a_pdf_article_is_saved_with_every_page_in_order_and_shown_on_the_website(): void
    {
        $this->post(route('admin.media-articles.store'), $this->fields(['page_files' => [self::page('page-01.jpg'), self::page('page-02.jpg'), self::page('page-03.jpg')]]))
            ->assertRedirect(route('admin.media-articles.index'));

        $article = MediaArticle::firstOrFail();
        $this->assertCount(3, $article->pages);
        $this->assertSame($article->pages[0], $article->image); // page 1 is the cover in the lists
        foreach ($article->pages as $page) {
            Storage::disk('public')->assertExists($page);
        }

        $html = $this->get($article->url)->assertOk()->getContent();
        foreach ($article->pages as $page) {
            $this->assertStringContainsString('storage/' . $page, $html);
        }
        $this->assertTrue(strpos($html, $article->pages[0]) < strpos($html, $article->pages[2]));
    }

    public function test_a_new_article_needs_the_pdf(): void
    {
        $this->post(route('admin.media-articles.store'), $this->fields())->assertSessionHasErrors('page_files');
        $this->assertSame(0, MediaArticle::count());
    }

    public function test_editing_keeps_the_pages_or_replaces_them_all_with_a_new_pdf(): void
    {
        $this->post(route('admin.media-articles.store'), $this->fields(['page_files' => [self::page('a.jpg'), self::page('b.jpg')]]));
        $article = MediaArticle::firstOrFail();
        $old = $article->pages;
        $article->forceFill(['description' => 'Older note'])->save();

        // No new PDF: pages (and the older description) stay
        $this->put(route('admin.media-articles.update', $article), $this->fields(['title' => 'Renamed']))->assertRedirect();
        $this->assertSame($old, $article->fresh()->pages);
        $this->assertSame('Older note', $article->fresh()->description);
        $this->get(route('admin.media-articles.edit', $article))->assertOk()->assertSee('Current pages (2)');

        // A new PDF replaces every page; the old files are deleted
        $this->put(route('admin.media-articles.update', $article), $this->fields(['page_files' => [self::page('new.jpg')]]))->assertRedirect();
        $article->refresh();
        $this->assertCount(1, $article->pages);
        foreach ($old as $page) {
            Storage::disk('public')->assertMissing($page);
        }

        // Deleting the article deletes its page files
        $this->delete(route('admin.media-articles.destroy', $article));
        Storage::disk('public')->assertMissing($article->pages[0]);
    }

    public function test_older_one_picture_articles_still_show(): void
    {
        $article = MediaArticle::create($this->fields(['image' => 'https://blogger.googleusercontent.com/img/a/AVvX/s1600/clip.jpg']));

        $this->get($article->url)->assertOk()->assertSee('https://blogger.googleusercontent.com/img/a/AVvX/w1200/clip.jpg', false);
    }
}
