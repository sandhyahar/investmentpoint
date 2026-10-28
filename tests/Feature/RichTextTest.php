<?php

namespace Tests\Feature;

use App\Models\News;
use App\Support\Html;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RichTextTest extends TestCase
{
    use RefreshDatabase;

    public function test_editor_formatting_is_shown_on_the_article_page(): void
    {
        $news = News::create([
            'title' => 'Formatted story',
            'description' => '<p>Short <strong>summary</strong></p>',
            'content' => '<h2>Big move</h2><p>Nifty <strong>rallied</strong> &amp; <em>closed</em> higher.</p><ul><li>Point one</li></ul>',
            'status' => 'active',
        ]);

        $this->get($news->url)->assertOk()
            ->assertSee('<h2>Big move</h2>', false)
            ->assertSee('<strong>rallied</strong>', false)
            ->assertSee('<li>Point one</li>', false);
    }

    public function test_scripts_and_event_handlers_are_removed(): void
    {
        $clean = Html::clean('<p onclick="steal()">Hi<script>alert(1)</script></p><a href="javascript:alert(1)">x</a><img src="x.jpg" onerror="alert(1)">');

        $this->assertStringNotContainsString('<script', $clean);
        $this->assertStringNotContainsString('onclick', $clean);
        $this->assertStringNotContainsString('onerror', $clean);
        $this->assertStringNotContainsString('javascript:', $clean);
        $this->assertStringContainsString('Hi', $clean);
    }

    public function test_previews_show_clean_text_without_tags_or_entities(): void
    {
        $this->assertSame('Nifty rallied & closed higher. Next', Html::plain('<p>Nifty <b>rallied</b> &amp;&nbsp;closed higher.</p><p>Next</p>'));
    }

    public function test_old_plain_text_articles_still_render_as_paragraphs(): void
    {
        $news = News::create(['title' => 'Plain story', 'description' => 'x', 'content' => "First paragraph.\n\nSecond one says 5 < 6 & more.", 'status' => 'active']);

        $this->get($news->url)->assertOk()
            ->assertSee('<p>First paragraph.</p>', false)
            ->assertSee('<p>Second one says 5 &lt; 6 &amp; more.</p>', false);
    }
}
