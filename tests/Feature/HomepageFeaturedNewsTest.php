<?php

namespace Tests\Feature;

use App\Models\News;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomepageFeaturedNewsTest extends TestCase
{
    use RefreshDatabase;

    public function test_homepage_uses_admin_featured_news_as_hero(): void
    {
        News::create([
            'title' => 'Older market update',
            'description' => 'Not the hero.',
            'status' => 'active',
            'is_featured' => false,
        ]);

        $featured = News::create([
            'title' => 'Featured market headline',
            'description' => 'This is the hero text.',
            'image' => 'featured/hero.jpg',
            'status' => 'active',
            'is_featured' => true,
        ]);
        // Updated at 11:11 AM India time (saved in UTC)
        $featured->forceFill(['created_at' => \Illuminate\Support\Carbon::parse('2026-10-26 11:11', 'Asia/Kolkata')->utc()])->save();

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('Featured market headline');
        $response->assertDontSee('This is the hero text.'); // headline only, no summary line under it
        $response->assertSee('storage/featured/hero.jpg');
        // Its update time, as on the article page
        $response->assertSee('26 October 2026, 11:11 AM');
    }
}
