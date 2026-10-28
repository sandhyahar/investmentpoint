<?php

namespace Tests\Feature;

use App\Models\News;
use App\Models\User;
use App\Support\ContentVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LiveContentUpdateTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::forceCreate([
            'name' => 'Administrator', 'username' => 'admin-live', 'email' => 'admin-live@example.com',
            'role' => 'admin', 'password' => bcrypt('secret'),
        ]);
    }

    public function test_admin_save_changes_the_content_version(): void
    {
        $before = $this->getJson(route('content.version'))->assertOk()->json('v');
        $this->travel(1)->seconds();

        $this->actingAs($this->admin())
            ->post(route('admin.news.store'), ['title' => 'Fresh story', 'description' => '<p>Short</p>', 'status' => 'active'])
            ->assertRedirect();

        $this->assertNotSame($before, $this->getJson(route('content.version'))->json('v'));
    }

    public function test_failed_validation_does_not_change_the_version(): void
    {
        $before = ContentVersion::current();
        $this->travel(1)->seconds();

        $this->actingAs($this->admin())
            ->post(route('admin.news.store'), ['title' => ''])
            ->assertSessionHasErrors();

        $this->assertSame($before, ContentVersion::current());
    }

    public function test_pages_mark_the_parts_that_update_live(): void
    {
        News::create(['title' => 'Hero story', 'description' => 'x', 'status' => 'active', 'is_featured' => true]);

        $this->get('/')->assertOk()
            ->assertSee('data-live-region="hero"', false)
            ->assertSee('data-live-region="youtube"', false)
            ->assertSee('content-version', false);

        $this->get(route('blog.create'))->assertOk()->assertSee('data-live="off"', false);
    }
}
