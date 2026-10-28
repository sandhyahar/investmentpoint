<?php

namespace Tests\Feature;

use App\Models\LatestNews;
use App\Models\News;
use App\Models\PopularNews;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NewsPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_view_all_shows_admin_latest_news_not_live_headlines(): void
    {
        LatestNews::create(['title' => 'Admin latest story', 'description' => 'x', 'status' => 'active']);
        PopularNews::create(['title' => 'Admin popular story', 'description' => 'x', 'status' => 'active']);

        $this->get(route('news.index'))->assertOk()
            ->assertSee('Latest Business News')
            ->assertSee('Admin latest story')
            ->assertSee('Admin popular story') // sidebar "Most Popular"
            ->assertDontSee('Economic Times test headline');
    }

    /** Business News (home) and Most Popular show the date and the time it was published, in India time */
    public function test_business_news_and_most_popular_show_the_time_too(): void
    {
        LatestNews::create(['title' => 'Sensex drops 400 points', 'description' => 'x', 'status' => 'active', 'published_at' => '2026-10-23 07:46:00']); // UTC
        PopularNews::create(['title' => 'Gold prices near record', 'description' => 'x', 'status' => 'active', 'published_at' => '2026-10-23 03:15:00']);

        $this->get(route('home'))->assertOk()->assertSee('23 Oct 2026, 1:16 PM');
        $this->get(route('news.index'))->assertOk()->assertSee('23 Oct 2026, 8:45 AM'); // Most Popular sidebar

        // A Market Trend article (e.g. the Nifty one, updated at 2:02 PM) shows its time under the title too
        $nifty = News::create(['title' => 'Nifty Derivative Market Trend Analysis : Banks Lead', 'description' => 'x', 'status' => 'active']);
        $nifty->forceFill(['created_at' => '2026-10-23 08:32:00'])->save(); // 2:02 PM India time
        $this->get($nifty->url)->assertOk()->assertSee('23 October 2026, 2:02 PM');
    }

    public function test_search_filters_latest_news(): void
    {
        LatestNews::create(['title' => 'Bank credit grows', 'description' => 'x', 'status' => 'active']);
        LatestNews::create(['title' => 'Auto sales rise', 'description' => 'x', 'status' => 'active']);

        $this->get(route('news.index', ['q' => 'bank']))->assertOk()
            ->assertSee('Bank credit grows')
            ->assertDontSee('Auto sales rise');
    }

    public function test_admin_articles_still_open_on_their_own_page(): void
    {
        $news = News::create(['title' => 'Database only story', 'description' => 'x', 'status' => 'active']);

        $this->get($news->url)->assertOk()->assertSee('Database only story');
    }
}
