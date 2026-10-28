<?php

namespace Tests\Feature;

use App\Models\EditorArticle;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Editor's Desk (menu after Blog): Nikhil Bhatt's daily articles from Admin -> Editor's Desk. */
class EditorsDeskTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::forceCreate([
            'name' => 'Administrator', 'username' => 'admin-ed', 'email' => 'admin-ed@example.com',
            'role' => 'admin', 'password' => bcrypt('secret'),
        ]);
    }

    public function test_admin_writes_an_article_and_readers_see_it_with_writer_views_and_share(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->get(route('admin.editor-articles.index'))->assertOk()->assertSee('Write Article');
        $this->get(route('admin.editor-articles.create'))->assertOk()->assertSee('Publish Article');

        $this->post(route('admin.editor-articles.store'), ['title' => 'No body', 'published_on' => '2026-10-05', 'status' => 'active'])
            ->assertSessionHasErrors(['content' => 'Please write the article.']);

        $this->post(route('admin.editor-articles.store'), [
            'title' => 'Why Nifty Could Stay Range-Bound This Week',
            'summary' => 'Key levels to watch and what they mean.',
            'content' => '<p>Nifty has support near 24,800.</p><p>Resistance stands at 25,400.</p>',
            'published_on' => '2026-10-05', 'status' => 'active',
        ])->assertRedirect(route('admin.editor-articles.index'));

        $article = EditorArticle::firstOrFail();
        $this->get(route('admin.editor-articles.index'))->assertSee('Why Nifty Could Stay Range-Bound This Week');

        auth()->logout();
        // Menu link after Blog, list, then the article with writer, date, views and Share
        $this->get(route('home'))->assertOk()->assertSee(route('editors-desk.index'), false)->assertSee("Editor's Desk", false);
        $this->get(route('editors-desk.index'))->assertOk()
            ->assertSee('Why Nifty Could Stay Range-Bound This Week')->assertSee('Key levels to watch')->assertSee($article->url, false);
        $this->get(route('editors-desk.index', ['q' => 'Nifty']))->assertOk()->assertSee('Why Nifty Could Stay');
        $this->get(route('editors-desk.index', ['q' => 'gold']))->assertOk()->assertSee('No article matches your search.');

        $this->get($article->url)->assertOk()
            ->assertSee('Why Nifty Could Stay Range-Bound This Week')
            ->assertSee('Nikhil Bhatt')->assertSee('05 October 2026')->assertSee('view')
            ->assertSee('data-share', false)->assertSee('Resistance stands at 25,400.');
    }

    public function test_inactive_articles_are_hidden_and_admin_can_edit_and_delete(): void
    {
        $article = EditorArticle::create(['title' => 'Draft piece', 'content' => '<p>Draft</p>', 'published_on' => '2026-10-04', 'status' => 'inactive']);

        $this->get(route('editors-desk.index'))->assertOk()->assertDontSee('Draft piece');
        $this->get($article->url)->assertNotFound();

        $this->actingAs($this->admin())->put(route('admin.editor-articles.update', $article), [
            'title' => 'Published piece', 'content' => '<p>Now live</p>', 'published_on' => '2026-10-04', 'status' => 'active',
        ])->assertRedirect(route('admin.editor-articles.index'));
        $this->get($article->fresh()->url)->assertOk()->assertSee('Now live');

        $this->delete(route('admin.editor-articles.destroy', $article))->assertRedirect(route('admin.editor-articles.index'));
        $this->assertSame(0, EditorArticle::count());
    }

    public function test_article_page_links_newer_and_older_articles(): void
    {
        $old = EditorArticle::create(['title' => 'Monday view', 'content' => '<p>a</p>', 'published_on' => '2026-10-01', 'status' => 'active']);
        $mid = EditorArticle::create(['title' => 'Tuesday view', 'content' => '<p>b</p>', 'published_on' => '2026-10-02', 'status' => 'active']);
        $new = EditorArticle::create(['title' => 'Wednesday view', 'content' => '<p>c</p>', 'published_on' => '2026-10-03', 'status' => 'active']);

        $this->get($mid->url)->assertOk()
            ->assertSee('Newer article')->assertSee($new->url, false)
            ->assertSee('Older article')->assertSee($old->url, false)
            ->assertSee("More from Editor's Desk", false);
    }
}
