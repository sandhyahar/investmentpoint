<?php

namespace Tests\Feature;

use App\Models\LatestNews;
use App\Models\User;
use App\Services\StockPhotos;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StockPhotosTest extends TestCase
{
    use RefreshDatabase;

    private function fakePexels(): void
    {
        Http::fake([
            'api.pexels.com/*' => Http::response(['photos' => [[
                'id' => 777, 'url' => 'https://www.pexels.com/photo/gold-bars-777/', 'photographer' => 'Asha Rao', 'alt' => 'Gold bars',
                'src' => ['landscape' => 'https://images.pexels.com/photos/777/gold.jpeg?w=1200&h=627'],
            ]]]),
            'images.pexels.com/*' => Http::response('jpeg-bytes', 200, ['Content-Type' => 'image/jpeg']),
        ]);
    }

    public function test_a_headline_becomes_a_topic_search(): void
    {
        $this->assertContains(StockPhotos::queryFor('PC Jeweller stock rises 4%'), ['gold bars', 'gold bullion', 'gold coins']);
        $this->assertContains(StockPhotos::queryFor('MRPL shares jump as crude oil rises'), ['oil refinery', 'oil industry', 'petrochemical plant']);
        $this->assertContains(StockPhotos::queryFor('Nifty ends flat in choppy trade'), ['stock market chart', 'stock market trading screen', 'business finance office']);
    }

    public function test_news_photo_is_saved_with_the_photographer_credit(): void
    {
        Storage::fake('public');
        config(['services.pexels.key' => 'pexels-key', 'services.pixabay.key' => null]);
        $this->fakePexels();

        $photo = app(StockPhotos::class)->forHeadline('Gold prices hit a new high');

        $this->assertSame('Asha Rao', $photo['credit']);
        $this->assertSame('https://www.pexels.com/photo/gold-bars-777/', $photo['credit_url']);
        Storage::disk('public')->assertExists($photo['path']);
        Http::assertSent(fn ($r) => str_contains($r->url(), 'api.pexels.com/v1/search')
            && $r->hasHeader('Authorization', 'pexels-key') && $r['orientation'] === 'landscape');

        // The article page shows the credit, the footer the Pexels link
        $article = LatestNews::create(['title' => 'Gold prices hit a new high', 'description' => 'x', 'content' => '<p>x</p>', 'status' => 'active',
            'image' => $photo['path'], 'image_credit' => $photo['credit'], 'image_credit_url' => $photo['credit_url']]);
        $this->get($article->url)->assertOk()->assertSee('Photo:', false)->assertSee('Asha Rao')->assertSee('Photos provided by Pexels');
    }

    public function test_pixabay_photos_are_downloaded_credited_and_searches_cached(): void
    {
        Storage::fake('public');
        config(['services.pixabay.key' => 'pixabay-key', 'services.pexels.key' => null]);
        Http::fake([
            'pixabay.com/api/*' => Http::response(['hits' => [[
                'id' => 42, 'pageURL' => 'https://pixabay.com/photos/refinery-42/', 'user' => 'Ravi_K', 'tags' => 'refinery, oil',
                'largeImageURL' => 'https://pixabay.com/get/refinery_1280.jpg', 'imageWidth' => 4000,
            ]]]),
            'pixabay.com/get/*' => Http::response('jpeg-bytes', 200, ['Content-Type' => 'image/jpeg']),
        ]);
        $photos = app(StockPhotos::class);

        $first = $photos->forHeadline('MRPL shares jump as crude oil rises');
        $this->assertSame('Ravi_K', $first['credit']);
        $this->assertSame('https://pixabay.com/photos/refinery-42/', $first['credit_url']);
        Storage::disk('public')->assertExists($first['path']); // saved on our server, not hotlinked
        Http::assertSent(fn ($r) => str_contains($r->url(), 'pixabay.com/api/') && $r['key'] === 'pixabay-key'
            && $r['image_type'] === 'photo' && $r['orientation'] === 'horizontal' && $r['safesearch'] === 'true');

        // The same search within 24 hours is answered from the cache (Pixabay's rule)
        $searches = fn () => count(array_filter(Http::recorded()->all(), fn ($pair) => str_contains($pair[0]->url(), 'pixabay.com/api/')));
        $before = $searches();
        $photos->find('oil refinery');
        $photos->find('oil refinery');
        $this->assertLessThanOrEqual($before + 1, $searches());

        // Credit on the article page names Pixabay; the footer links to Pixabay
        $article = LatestNews::create(['title' => 'MRPL shares jump', 'description' => 'x', 'content' => '<p>x</p>', 'status' => 'active',
            'image' => $first['path'], 'image_credit' => $first['credit'], 'image_credit_url' => $first['credit_url']]);
        $this->get($article->url)->assertOk()->assertSee('Ravi_K')->assertSee('/ Pixabay', false)->assertSee('Photos provided by Pixabay');
    }

    public function test_admin_find_real_photo_button(): void
    {
        $admin = User::forceCreate(['name' => 'Administrator', 'username' => 'admin-px', 'email' => 'admin-px@example.com', 'role' => 'admin', 'password' => bcrypt('secret')]);

        // Not set up yet: a clear message
        config(['services.pexels.key' => null, 'services.pixabay.key' => null]);
        $this->actingAs($admin)->postJson(route('admin.stock-photo'), ['prompt' => 'gold'])->assertStatus(422)
            ->assertJsonFragment(['message' => 'Real photos are not set up yet: add a free PIXABAY_API_KEY to .env (pixabay.com/api/docs, shown after you log in).']);

        config(['services.pexels.key' => 'pexels-key', 'services.pixabay.key' => null]);
        $this->fakePexels();
        $this->actingAs($admin)->postJson(route('admin.stock-photo'), ['prompt' => 'PC Jeweller stock rises', 'from_headline' => true])
            ->assertOk()
            ->assertJson(['image' => 'data:image/jpeg;base64,' . base64_encode('jpeg-bytes'), 'provider' => 'pexels', 'credit' => 'Asha Rao']);
        // The headline was turned into a topic search, not sent with the company name
        Http::assertSent(fn ($r) => str_contains($r->url(), 'api.pexels.com') && !str_contains(strtolower($r['query']), 'jeweller'));
    }
}
