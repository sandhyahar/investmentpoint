<?php

namespace Tests\Feature;

use App\Models\News;
use App\Models\TechnicalAnalysis;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Market Trend and Technical Analysis: a Share button (WhatsApp, Facebook, ...) on the article and in the admin list. */
class ArticleShareButtonTest extends TestCase
{
    use RefreshDatabase;

    public function test_articles_and_admin_lists_have_a_share_button_with_the_article_link(): void
    {
        $news = News::create(['title' => 'Nifty Derivative Market Trend Analysis', 'description' => 'Trend', 'body' => 'Body text.', 'status' => 'active']);
        $analysis = TechnicalAnalysis::create(['title' => 'HDFC Bank Technical Analysis', 'description' => 'Levels', 'status' => 'active']);
        $hidden = TechnicalAnalysis::create(['title' => 'Draft analysis', 'description' => 'Draft', 'status' => 'inactive']);

        foreach ([[$news, $news->share_url], [$analysis, $analysis->share_url]] as [$article, $url]) {
            $this->get($article->url)->assertOk()
                ->assertSee('data-share data-url="' . e($url) . '"', false)
                ->assertSee('web.whatsapp.com/send?text=', false)->assertSee('facebook.com/sharer', false)
                ->assertDontSee('data-signed', false); // readers share without the owner's signature
        }

        // Admin shares get the date + signature (Nikhil Bhatt, Business Editor, Investment Point, nikhilbhatt.in)
        $signed = fn ($response) => $response->assertSee('data-signed', false)->assertSee('"Business Editor"', false)->assertSee('nikhilbhatt.in', false);
        $this->actingAs(User::forceCreate(['name' => 'Administrator', 'username' => 'admin-share', 'email' => 'admin-share@example.com', 'role' => 'admin', 'password' => bcrypt('secret')]));
        $signed($this->get(route('admin.news.index'))->assertOk()->assertSee('data-share data-url="' . e($news->share_url) . '"', false));
        $signed($this->get(route('admin.technical-analysis.index'))->assertOk())
            ->assertSee('data-share data-url="' . e($analysis->share_url) . '"', false)
            ->assertDontSee('data-url="' . e($hidden->url) . '"', false); // not shown to readers yet: nothing to share
    }
}
