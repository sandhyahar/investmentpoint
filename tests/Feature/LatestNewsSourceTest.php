<?php

namespace Tests\Feature;

use App\Models\LatestNews;
use App\Models\PopularNews;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Homepage "Latest News" box and "Most Popular" list come from their own admin sections, not the live feeds. */
class LatestNewsSourceTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::forceCreate([
            'name' => 'Administrator', 'username' => 'admin-news', 'email' => 'admin-news@example.com',
            'role' => 'admin', 'password' => bcrypt('secret'),
        ]);
    }

    public function test_homepage_box_shows_admin_latest_news_and_no_live_headlines(): void
    {
        LatestNews::create(['title' => 'Admin latest story', 'description' => 'From the admin panel', 'status' => 'active']);
        LatestNews::create(['title' => 'Hidden latest story', 'description' => 'x', 'status' => 'inactive']);

        $html = $this->get('/')->assertOk()->getContent();
        $box = substr($html, strpos($html, 'id="latestNewsList"'), 6000);

        $this->assertStringContainsString('Admin latest story', $box);
        $this->assertStringNotContainsString('Hidden latest story', $box);
        $this->assertStringNotContainsString('Economic Times test headline', $box);
    }

    public function test_refresh_endpoint_returns_just_the_news_list(): void
    {
        LatestNews::create(['title' => 'Admin latest story', 'description' => 'x', 'status' => 'active']);

        $this->get(route('news.latest'))->assertOk()
            ->assertSee('Admin latest story')
            ->assertDontSee('data-external="1"', false)
            ->assertDontSee('<html', false);
    }

    public function test_latest_and_popular_items_open_on_their_own_page(): void
    {
        $latest = LatestNews::create(['title' => 'Latest detail story', 'description' => 'Summary', 'content' => 'Full latest body', 'status' => 'active']);
        $popular = PopularNews::create(['title' => 'Popular detail story', 'description' => 'Summary', 'status' => 'active', 'sort_order' => 1]);

        // The text justified, older articles saved without it too
        $this->get($latest->url)->assertOk()->assertSee('Full latest body')->assertSee('Popular detail story')
            ->assertSee('<div class="np-content np-justify">', false)->assertSee('.np-justify p,.np-justify li{text-align:justify}', false);
        $this->get($popular->url)->assertOk()->assertSee('Popular detail story')->assertSee('np-justify', false);
        $this->assertSame(1, $latest->fresh()->views);
    }

    public function test_inactive_item_page_is_not_shown(): void
    {
        $item = PopularNews::create(['title' => 'Draft', 'description' => 'x', 'status' => 'inactive']);

        // Hidden (or deleted) articles are never shown: the link opens the news list instead
        $this->get($item->url)->assertRedirect(route('news.index'))->assertDontSee('Draft');
    }

    public function test_admin_can_create_update_and_delete_latest_news(): void
    {
        $this->actingAs($this->admin());

        $this->post(route('admin.latest-news.store'), [
            'title' => 'Created in admin', 'description' => 'Summary', 'status' => 'active',
            'image_url' => 'https://example.com/a.jpg',
        ])->assertRedirect(route('admin.latest-news.index'));

        $item = LatestNews::firstOrFail();
        $this->assertSame('https://example.com/a.jpg', $item->image);

        $this->put(route('admin.latest-news.update', $item->id), ['title' => 'Renamed', 'description' => 'Summary', 'status' => 'active'])
            ->assertRedirect(route('admin.latest-news.index'));
        $this->assertSame('Renamed', $item->fresh()->title);
        $this->assertSame('https://example.com/a.jpg', $item->fresh()->image); // empty image fields keep the current one

        $this->get(route('admin.latest-news.index'))->assertOk()->assertSee('Renamed');

        $this->delete(route('admin.latest-news.destroy', $item->id))->assertRedirect(route('admin.latest-news.index'));
        $this->assertSame(0, LatestNews::count());
    }

    public function test_popular_news_admin_is_separate_and_ranked(): void
    {
        $this->actingAs($this->admin());

        $this->post(route('admin.popular-news.store'), ['title' => 'Second', 'description' => 'x', 'status' => 'active', 'sort_order' => 2]);
        $this->post(route('admin.popular-news.store'), ['title' => 'First', 'description' => 'x', 'status' => 'active', 'sort_order' => 1]);

        $this->assertSame(0, LatestNews::count());
        $this->assertSame(['First', 'Second'], PopularNews::ordered()->pluck('title')->all());
        $this->get(route('admin.popular-news.create'))->assertOk()->assertSee('Position');
    }
}
