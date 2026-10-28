<?php

namespace Tests\Feature;

use App\Models\LatestNews;
use App\Models\NewsInboxItem;
use App\Models\PopularNews;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class NewsPruneTest extends TestCase
{
    use RefreshDatabase;

    private function article(string $model, string $title, $publishedAt, string $status = 'active', ?string $image = null)
    {
        return $model::create(['title' => $title, 'description' => 'x', 'content' => '<p>x</p>', 'image' => $image,
            'published_at' => $publishedAt, 'status' => $status, 'sort_order' => 0]);
    }

    public function test_old_news_is_deleted_but_the_newest_are_always_kept(): void
    {
        Storage::fake('public');
        config(['news.keep_days' => 7, 'news.limit' => 2, 'news.inbox_keep_days' => 3]);

        Storage::disk('public')->put('headline-news/old.jpg', 'jpg');
        $old = $this->article(LatestNews::class, 'Old story', now()->subDays(20), 'active', 'headline-news/old.jpg');
        $hiddenOld = $this->article(LatestNews::class, 'Old hidden story', now()->subDays(10), 'inactive');
        $fresh = $this->article(LatestNews::class, 'Fresh story', now()->subDay());
        // Most Popular: only old ones exist -> the newest 2 stay so the box is never empty
        $p1 = $this->article(PopularNews::class, 'Popular A', now()->subDays(30));
        $p2 = $this->article(PopularNews::class, 'Popular B', now()->subDays(20));
        $p3 = $this->article(PopularNews::class, 'Popular C', now()->subDays(40));

        $oldInbox = NewsInboxItem::create(['source' => 'Mint', 'url' => 'https://m.example/1', 'url_hash' => sha1('https://m.example/1'),
            'title' => 'Old headline', 'published_at' => now()->subDays(5), 'score' => 5, 'coverage' => 1, 'status' => 'used']);
        $oldInbox->forceFill(['created_at' => now()->subDays(5)])->save();
        $newInbox = NewsInboxItem::create(['source' => 'Mint', 'url' => 'https://m.example/2', 'url_hash' => sha1('https://m.example/2'),
            'title' => 'New headline', 'published_at' => now(), 'score' => 5, 'coverage' => 1]);

        // Unused picture: deleted once it is over an hour old
        Storage::disk('public')->put('headline-news/orphan.jpg', 'jpg');
        touch(Storage::disk('public')->path('headline-news/orphan.jpg'), now()->subHours(2)->getTimestamp());

        // Dry run changes nothing
        $this->artisan('news:prune --dry-run')->assertSuccessful();
        $this->assertSame(3, LatestNews::count());

        $this->artisan('news:prune')->assertSuccessful();

        // Business News: the newest 2 kept ("Old story" is one of them), the hidden old one deleted
        $this->assertNull($hiddenOld->fresh());
        $this->assertNotNull($fresh->fresh());
        $this->assertNotNull($old->fresh());
        // Most Popular: newest 2 kept, the oldest deleted
        $this->assertNotNull($p1->fresh());
        $this->assertNotNull($p2->fresh());
        $this->assertNull($p3->fresh());
        // Inbox
        $this->assertNull($oldInbox->fresh());
        $this->assertNotNull($newInbox->fresh());
        // Pictures
        Storage::disk('public')->assertMissing('headline-news/orphan.jpg');
        Storage::disk('public')->assertExists('headline-news/old.jpg');

        // With more fresh news, the old story is no longer among the newest -> deleted with its picture
        $this->article(LatestNews::class, 'Fresh story 2', now()->subHours(3));
        $this->artisan('news:prune')->assertSuccessful();
        $this->assertNull($old->fresh());
        Storage::disk('public')->assertMissing('headline-news/old.jpg');
    }

    public function test_a_deleted_article_link_opens_the_news_page(): void
    {
        $this->get(route('latest-news.show', ['id' => 999, 'slug' => 'gone']))->assertRedirect(route('news.index'))->assertStatus(301);
        $this->get(route('popular-news.show', ['id' => 999]))->assertRedirect(route('news.index'))->assertStatus(301);
    }
}
