<?php

namespace Tests\Feature;

use App\Models\AdminRole;
use App\Models\MediaArticle;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Add Media Article -> Bulk upload: many PDFs (or a folder), one Media Article each */
class MediaArticleBulkUploadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->actingAs(User::forceCreate(['name' => 'Admin', 'username' => 'admin-bulk', 'role' => 'admin', 'password' => Hash::make('x')]));
    }

    private function article(string $publication, string $date): MediaArticle
    {
        return MediaArticle::create(['title' => "INVESTMENT POINT / {$publication} - x", 'publication' => $publication, 'published_on' => $date,
            'image' => 'https://example.com/clip.jpg', 'status' => 'active']);
    }

    private function upload(array $extra = [])
    {
        return $this->postJson(route('admin.media-articles.bulk-store'), $extra + [
            'title' => 'PAGDANDI - 22.10.2026', 'column_name' => '', 'publication' => 'PAGDANDI', 'published_on' => '2026-10-22', 'status' => 'active',
            'page_files' => [UploadedFile::fake()->image('page-01.jpg', 900, 1300), UploadedFile::fake()->image('page-02.jpg', 900, 1300)],
        ]);
    }

    public function test_the_page_has_the_single_form_unchanged_and_a_bulk_upload_tab(): void
    {
        $this->article('PAGDANDI', '2026-10-01');

        $this->get(route('admin.media-articles.create'))->assertOk()
            ->assertSee('Single PDF')->assertSee('Bulk upload (many PDFs / folder)')
            ->assertSee('id="np_pdf"', false) // the single form's PDF box is still there
            ->assertSee('id="bulkFiles"', false)->assertSee('webkitdirectory', false)
            ->assertSee('<option value="PAGDANDI">', false); // known newspapers, to recognise file names
    }

    public function test_each_pdf_is_saved_as_its_own_article_with_its_pages(): void
    {
        $this->upload()->assertCreated()->assertJsonPath('pages', 2);
        $this->upload(['title' => 'NOBAT - 23.10.2026', 'publication' => 'NOBAT', 'published_on' => '2026-10-23'])->assertCreated();

        $this->assertSame(2, MediaArticle::count());
        $article = MediaArticle::where('publication', 'PAGDANDI')->firstOrFail();
        $this->assertSame('2026-10-22', $article->published_on->toDateString());
        $this->assertCount(2, $article->pages);
        $this->assertSame($article->pages[0], $article->image);
        Storage::disk('public')->assertExists($article->pages[1]);
    }

    public function test_duplicates_are_found_before_uploading_and_refused_unless_forced(): void
    {
        $existing = $this->article('PAGDANDI /', '2026-10-22'); // same paper, other spelling

        $this->postJson(route('admin.media-articles.bulk-check'), ['items' => [
            ['key' => 'f1', 'title' => 'x', 'publication' => 'PAGDANDI', 'published_on' => '2026-10-22'],
            ['key' => 'f2', 'title' => 'x', 'publication' => 'PAGDANDI', 'published_on' => '2026-10-23'],
        ]])->assertOk()->assertJsonPath('duplicates.f1.id', $existing->id)->assertJsonMissingPath('duplicates.f2');

        // Refused, and its uploaded pages are not left behind
        $this->upload()->assertStatus(409)->assertJsonPath('duplicate.id', $existing->id);
        $this->assertSame(1, MediaArticle::count());
        $this->assertSame([], Storage::disk('public')->allFiles('media-articles'));

        // "Upload anyway" ticked
        $this->upload(['force' => '1'])->assertCreated();
        $this->assertSame(2, MediaArticle::count());
    }

    public function test_invalid_files_are_reported(): void
    {
        $this->postJson(route('admin.media-articles.bulk-store'), ['title' => 'x', 'published_on' => 'not a date', 'status' => 'active'])
            ->assertStatus(422)->assertJsonValidationErrors(['published_on']);
        $this->postJson(route('admin.media-articles.bulk-store'), ['title' => 'x', 'published_on' => '2026-10-22', 'status' => 'active'])
            ->assertStatus(422)->assertJsonValidationErrors(['page_files']);
        $this->assertSame(0, MediaArticle::count());
    }

    public function test_bulk_upload_needs_the_create_permission(): void
    {
        $this->flushSession();
        $role = AdminRole::create(['name' => 'Viewer', 'is_active' => true]);
        $role->syncPermissions(['media-articles.view']);
        $viewer = User::forceCreate(['name' => 'V', 'username' => 'viewer', 'role' => 'staff', 'admin_role_id' => $role->id, 'password' => Hash::make('x')]);
        $this->actingAs($viewer);

        $this->upload()->assertForbidden();
        $this->postJson(route('admin.media-articles.bulk-check'), ['items' => [['key' => 'a']]])->assertForbidden();

        $role->syncPermissions(['media-articles.create']);
        $this->actingAs($viewer->fresh()); // each real request loads the user (and role) fresh
        $this->upload()->assertCreated();
    }
}
