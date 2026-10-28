<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserBlog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class UserBlogWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_visitor_can_submit_a_pending_blog_without_an_account(): void
    {
        $this->get('/blog/create')->assertOk()->assertSee("Expert's Name", false);
        $this->get('/author/register')->assertRedirect('/blog/create');
        $this->get('/author/login')->assertRedirect('/blog/create');

        $this->post('/blog', [
            'author_name' => 'Asha Writer',
            'author_email' => 'Asha@Example.com ',
            'title' => 'My First Market Story',
            'content' => 'This is a long enough market blog written by an author for review.',
            'declaration' => '1',
        ])->assertRedirect('/blog/create');

        $blog = UserBlog::firstOrFail();
        $this->assertGuest();
        $this->assertNull($blog->user_id);
        $this->assertSame('Asha Writer', $blog->writer_name);
        $this->assertSame('asha@example.com', $blog->author_email); // kept for the team, never shown on the site
        $this->assertSame('my-first-market-story', $blog->slug);
        $this->assertSame('pending', $blog->status);
        $this->assertFalse($blog->has_writer_photo);
        $this->get('/')->assertOk()->assertDontSee('My First Market Story');
        $this->get('/blog/my-first-market-story')->assertNotFound();
    }

    public function test_writer_photo_is_stored_and_shown_otherwise_logo_is_used(): void
    {
        Storage::fake('public');

        $this->post('/blog', [
            'author_name' => 'Photo Writer',
            'author_email' => 'photo@example.com',
            'title' => 'Blog With Photo',
            'content' => 'A blog that includes a profile picture of the writer for readers.',
            'author_photo' => UploadedFile::fake()->create('me.jpg', 100, 'image/jpeg'),
            'declaration' => '1',
        ])->assertRedirect('/blog/create');

        $blog = UserBlog::firstOrFail();
        $this->assertTrue($blog->has_writer_photo);
        Storage::disk('public')->assertExists($blog->author_photo);

        $blog->update(['status' => 'approved', 'approved_at' => now()]);
        $this->get('/blog/blog-with-photo')->assertOk()->assertSee('Photo Writer')->assertSee('storage/' . $blog->author_photo, false);
    }

    public function test_whatsapp_preview_is_the_banner_with_the_writers_photo(): void
    {
        Storage::fake('public');
        $photo = UploadedFile::fake()->image('me.jpg', 400, 630)->store('blog-authors', 'public');
        $blog = UserBlog::create(['author_name' => 'Paresh Dave', 'author_photo' => $photo, 'title' => 'Gulf War Effects',
            'slug' => 'gulf-war-effects', 'content' => 'Oil prices and the Indian market.', 'status' => 'approved', 'approved_at' => now()]);

        $share = 'blog-authors/share/' . pathinfo($photo, PATHINFO_FILENAME) . '-card.jpg';
        $this->get('/blog/gulf-war-effects')->assertOk()
            ->assertSee('<meta property="og:image" content="' . asset('storage/' . $share) . '">', false);
        [$width, $height] = getimagesizefromstring(Storage::disk('public')->get($share));
        $this->assertSame([1200, 630], [$width, $height]); // the shape WhatsApp shows whole

        // Removing the writer photo removes its share picture too
        $blog->deleteWriterPhoto();
        Storage::disk('public')->assertMissing([$photo, $share]);
    }

    public function test_blog_text_is_formatted_and_pictures_are_placed_where_marked(): void
    {
        Storage::fake('public');

        $this->post('/blog', [
            'author_name' => 'Chart Writer',
            'author_email' => 'chart@example.com',
            'title' => 'Crude Oil and India',
            'content' => "Why oil matters\nA war in the Gulf pushes **crude oil** up.\n\n[Image 2]\n\nWhat to watch\n- Crude above \$90\n- Rupee\n\nMore at www.example.com",
            'images' => [UploadedFile::fake()->image('unused.jpg', 300, 200), UploadedFile::fake()->image('chart.png', 800, 450)],
            'image_numbers' => [1, 2],
            'declaration' => '1',
        ])->assertRedirect('/blog/create')->assertSessionHasNoErrors();

        $blog = UserBlog::firstOrFail();
        $this->assertStringContainsString('<h3>Why oil matters</h3>', $blog->content);
        $this->assertStringContainsString('<strong>crude oil</strong>', $blog->content);
        $this->assertStringContainsString('<ul><li>Crude above $90</li><li>Rupee</li></ul>', $blog->content);
        $this->assertStringContainsString('<a href="https://www.example.com" target="_blank">', $blog->content);

        // Only the marked picture is kept, in its place
        $files = Storage::disk('public')->files('blog-images');
        $this->assertCount(1, $files);
        $this->assertStringContainsString('<img src="' . asset('storage/' . $files[0]) . '"', $blog->content);

        $blog->update(['status' => 'approved', 'approved_at' => now()]);
        $this->get('/blog/crude-oil-and-india')->assertOk()->assertSee('storage/' . $files[0], false)->assertSee('<strong>crude oil</strong>', false);

        // Deleting the blog in the admin removes its pictures
        $admin = \App\Models\User::forceCreate(['name' => 'Admin', 'username' => 'admin-blog-img', 'email' => 'admin-blog-img@example.com', 'role' => 'admin', 'password' => bcrypt('secret')]);
        $this->actingAs($admin)->delete(route('admin.blogs.destroy', $blog))->assertRedirect();
        Storage::disk('public')->assertMissing($files[0]);
    }

    public function test_writer_website_link_and_ca_number_are_shown(): void
    {
        $post = fn (string $website) => $this->post('/blog', [
            'author_name' => 'Web Writer', 'author_email' => 'web@example.com', 'author_reg_no' => 'ca 123456',
            'author_website' => $website, 'title' => 'Website Blog ' . $website,
            'content' => 'A blog with the writer website link for readers to visit.', 'declaration' => '1',
        ]);

        $post('javascript:alert(1)')->assertSessionHasErrors('author_website');
        $post('not a link')->assertSessionHasErrors('author_website');
        $post('www.webwriter.in/about')->assertSessionHasNoErrors();

        $blog = UserBlog::sole();
        $this->assertSame('https://www.webwriter.in/about', $blog->author_website);
        $blog->update(['status' => 'approved', 'approved_at' => now()]);
        $this->get('/blog/' . $blog->slug)->assertOk()
            ->assertSee('href="https://www.webwriter.in/about"', false)
            ->assertSee('webwriter.in/about')
            ->assertSee('RA / IA / CA No. CA 123456');
        $this->get('/blog/create')->assertSee('RA / IA / CA No.')->assertSee('name="author_website"', false);
    }

    public function test_readers_can_like_or_dislike_a_blog_once(): void
    {
        UserBlog::create(['author_name' => 'Paresh Dave', 'title' => 'Gulf War Effects', 'slug' => 'gulf-war-effects',
            'content' => 'Oil prices and the Indian market.', 'status' => 'approved', 'approved_at' => now()]);
        $reader = ['ip_vid' => str_repeat('a', 32)];
        $other = ['ip_vid' => str_repeat('b', 32)];

        $this->get('/blog/gulf-war-effects')->assertOk()->assertSee('data-reaction="like"', false)->assertSee('data-reaction="dislike"', false);

        $this->withCredentials()->withCookies($reader)->postJson('/blog/gulf-war-effects/react', ['reaction' => 'like'])
            ->assertOk()->assertExactJson(['likes' => 1, 'dislikes' => 0, 'mine' => 'like']);
        // Same reader again: still one vote; switching changes it; the same button again takes it back
        $this->withCredentials()->withCookies($reader)->postJson('/blog/gulf-war-effects/react', ['reaction' => 'dislike'])
            ->assertExactJson(['likes' => 0, 'dislikes' => 1, 'mine' => 'dislike']);
        $this->withCredentials()->withCookies($other)->postJson('/blog/gulf-war-effects/react', ['reaction' => 'like'])
            ->assertExactJson(['likes' => 1, 'dislikes' => 1, 'mine' => 'like']);
        $this->withCredentials()->withCookies($reader)->postJson('/blog/gulf-war-effects/react', ['reaction' => 'dislike'])
            ->assertExactJson(['likes' => 1, 'dislikes' => 0, 'mine' => null]);

        // The page shows the counts and this reader's own vote
        $this->withCredentials()->withCookies($other)->get('/blog/gulf-war-effects')->assertOk()->assertSee('bi-hand-thumbs-up-fill', false);

        $this->postJson('/blog/gulf-war-effects/react', ['reaction' => 'love'])->assertStatus(422);
        $this->postJson('/blog/no-such-blog/react', ['reaction' => 'like'])->assertNotFound();
    }

    public function test_blog_submission_requires_writer_name_and_ignores_bots(): void
    {
        $this->post('/blog', [
            'title' => 'No Writer',
            'content' => 'This blog has no writer name so it must be rejected by validation.',
        ])->assertSessionHasErrors(['author_name', 'author_email']);

        // The "my personal views" declaration must be ticked
        $this->get('/blog/create')->assertSee('Neither Investment Point nor Nikhil Bhatt has any involvement');
        $this->post('/blog', [
            'author_name' => 'Asha Writer',
            'title' => 'Not Declared',
            'content' => 'This blog was sent without ticking the declaration checkbox.',
        ])->assertSessionHasErrors(['declaration' => 'Please tick the declaration to submit your blog.']);

        // Honeypot filled in: silently ignored
        $this->post('/blog', [
            'website' => 'http://spam.example',
            'author_name' => 'Bot',
            'title' => 'Spam',
            'content' => 'Spam content that is long enough to pass the minimum length rule.',
        ])->assertRedirect('/blog/create');

        $this->assertSame(0, UserBlog::count());
    }

    public function test_only_approved_blogs_are_public_and_admin_can_approve(): void
    {
        $admin = User::factory()->create([
            'username' => 'reviewadmin',
            'role' => 'admin',
            'password' => Hash::make('password'),
        ]);
        $blog = UserBlog::create([
            'author_name' => 'Market Writer',
            'title' => 'Approved Market Story',
            'slug' => 'approved-market-story',
            'content' => 'Approved content.',
            'status' => 'pending',
        ]);

        $this->actingAs($admin)->put('/admin/blogs/' . $blog->id, [
            'author_name' => 'Market Writer',
            'title' => $blog->title,
            'content' => $blog->content,
            'status' => 'approved',
        ])->assertRedirect('/admin/blogs');

        // Approved blogs are listed at the top of Expert's View; the old /blogs page redirects there
        $this->get('/blogs')->assertRedirect(route('experts-view.index') . '#latest-blogs');
        $this->get(route('experts-view.index'))->assertOk()->assertSee('Approved Market Story')->assertSee('Market Writer')->assertSee('/blog/approved-market-story', false);
        $this->get('/blog/approved-market-story')->assertOk()->assertSee('Approved Market Story');
        $this->get('/approved-market-story')->assertOk()->assertSee('Approved Market Story');
    }

    public function test_regular_users_cannot_open_admin_blogs(): void
    {
        $user = User::factory()->create(['role' => 'user']);

        $this->actingAs($user)->get('/admin/blogs')->assertForbidden();
    }
}
