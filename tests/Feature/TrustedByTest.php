<?php

namespace Tests\Feature;

use App\Models\MediaArticle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Home page "Trusted by": the newspapers that publish Investment Point columns */
class TrustedByTest extends TestCase
{
    use RefreshDatabase;

    private function clippings(string $publication, int $count, string $date = '2026-02-20'): MediaArticle
    {
        $last = null;
        for ($i = 0; $i < $count; $i++) {
            $last = MediaArticle::create(['title' => "INVESTMENT POINT / {$publication} - {$i}", 'publication' => $publication, 'published_on' => $date,
                'image' => "https://blogger.googleusercontent.com/img/a/X{$i}/s1600/" . rawurlencode($publication) . "-{$i}.jpg", 'status' => 'active']);
        }

        return $last;
    }

    public function test_spellings_of_one_newspaper_are_merged(): void
    {
        $this->assertSame('PAGDANDI', MediaArticle::normalizePublication('PAGDANDI /'));
        $this->assertSame('PAGDANDI', MediaArticle::normalizePublication(' pagdandi '));
        $this->assertSame('SUPRABHAT SAURASHTRA', MediaArticle::normalizePublication('SUPRABHAT SAURASTRA /'));
        $this->assertSame('SAURASHTRA ALARM', MediaArticle::normalizePublication('SAURASHTRA ALRAM'));
    }

    public function test_the_home_page_shows_each_newspaper_once_with_its_logo_and_opens_all_its_articles(): void
    {
        $this->clippings('PAGDANDI', 8);
        $newest = $this->clippings('PAGDANDI /', 4); // same paper, posted with a slash: newest clipping
        $this->clippings('NOBAT', 11);
        $this->clippings('TINY PAPER', 2); // fewer than 10 articles: not shown

        $trusted = MediaArticle::trustedBy();
        $this->assertSame(['PAGDANDI', 'NOBAT'], array_column($trusted, 'name')); // most articles first
        $this->assertSame(12, $trusted[0]['total']);
        $list = route('media-articles.index', ['publication' => 'PAGDANDI']);
        $this->assertSame($list, $trusted[0]['url']); // a click opens all of that paper's articles
        $this->assertSame($newest->imageUrl('w400'), $trusted[0]['logo']); // masthead of the newest clipping

        $this->get(route('home'))->assertOk()
            ->assertSee('Trusted by')->assertDontSee('Featured in')
            ->assertSee('href="' . e($list) . '"', false)->assertSee('alt="PAGDANDI newspaper"', false)
            ->assertDontSee('columns published')->assertDontSee('View all media articles')->assertDontSee('TINY PAPER');
    }

    public function test_the_newest_article_is_by_published_date_not_by_upload_order(): void
    {
        $recent = $this->clippings('KHABAR GUJARAT', 10, '2026-10-20');
        $this->clippings('KHABAR GUJARAT', 1, '2017-11-14'); // an old clipping imported last

        $this->assertSame($recent->imageUrl('w400'), MediaArticle::trustedBy()[0]['logo']); // the logo comes from it
    }

    public function test_the_logo_skips_a_clipping_of_another_paper(): void
    {
        $this->clippings('ABTAK DAILY', 10, '2024-08-21'); // pictures named ABTAK%20DAILY-...
        // the newest post, but its picture is an Azad Sandesh page
        MediaArticle::create(['title' => 'INVESTMENT POINT / ABTAK DAILY - 12.12.2025', 'publication' => 'ABTAK DAILY', 'published_on' => '2025-12-12',
            'image' => 'https://blogger.googleusercontent.com/img/a/Z/s1600/AZAD%20SANDESH%20-%2012.12.2025.jpg', 'status' => 'active']);

        $this->assertStringContainsString('ABTAK%20DAILY', MediaArticle::trustedBy()[0]['logo']);
    }

    public function test_the_newspaper_filter_finds_every_spelling(): void
    {
        $plain = $this->clippings('PAGDANDI', 1);
        $slash = $this->clippings('PAGDANDI /', 1);

        $this->get(route('media-articles.index', ['publication' => 'PAGDANDI']))->assertOk()
            ->assertSee($plain->url, false)->assertSee($slash->url, false);
    }

    public function test_the_media_articles_page_lists_every_newspaper_once_in_a_scrollable_row(): void
    {
        $this->clippings('PAGDANDI', 2);
        $this->clippings('PAGDANDI /', 1);
        for ($i = 1; $i <= 45; $i++) {
            $this->clippings('PAPER ' . $i, 1); // more than the old limit of 40
        }

        $html = $this->get(route('media-articles.index', ['publication' => 'PAGDANDI /']))->assertOk()
            ->assertSee('id="maFilter"', false)->assertSee('aria-label="More newspapers"', false)
            ->assertSee('PAPER 45')->getContent();
        $this->assertSame(1, substr_count($html, '>PAGDANDI <small>3</small>')); // once, with both spellings counted
        $this->assertMatchesRegularExpression('/class="active"[^>]*>PAGDANDI <small>|href="[^"]*PAGDANDI" class="active"/', $html);
    }

    public function test_a_new_article_shows_up_at_once(): void
    {
        \Illuminate\Support\Facades\Storage::fake('public');
        $this->clippings('NOBAT', 10);
        $this->assertCount(1, MediaArticle::trustedBy());

        $this->actingAs(\App\Models\User::forceCreate(['name' => 'A', 'username' => 'a', 'role' => 'admin', 'password' => 'x']));
        $this->clippings('GUJARAT MAIL', 9);
        $this->post(route('admin.media-articles.store'), ['title' => 'INVESTMENT POINT / GUJARAT MAIL - 21.02.2026', 'publication' => 'GUJARAT MAIL',
            'published_on' => '2026-02-21', 'status' => 'active', 'page_files' => [\Illuminate\Http\UploadedFile::fake()->image('p1.jpg', 800, 1100)]])->assertRedirect();

        $this->assertSame(['GUJARAT MAIL', 'NOBAT'], array_column(MediaArticle::trustedBy(), 'name'));
    }
}
