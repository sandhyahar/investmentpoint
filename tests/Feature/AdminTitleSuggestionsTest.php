<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** "Suggest SEO titles" under the Title box of the article forms. */
class AdminTitleSuggestionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        config(['services.gemini_ai.key' => 'AIzaTestKey0123456789012345678901234567', 'services.huggingface_ai.token' => null]);
        $this->actingAs(User::forceCreate(['name' => 'Administrator', 'username' => 'admin-titles', 'email' => 'admin-titles@example.com', 'role' => 'admin', 'password' => bcrypt('secret')]));
    }

    public function test_the_ai_returns_five_clean_titles(): void
    {
        Http::fake(['generativelanguage.googleapis.com/*' => self::geminiSays(
            "1. Godrej Properties Share Price: Support, Resistance and Key Levels\n"
            . "2) \"Godrej Properties Technical Analysis: Trend and Chart Levels\"\n"
            . "- Godrej Properties Stock: Buy Now Before the Breakout\n"       // advice: dropped
            . "* Godrej Properties Share Price Outlook: Levels Traders Watch\n"
            . "Godrej Properties Technical View: Support Zone and Resistance Area\n"
            . "Short one\n"                                                     // too short: dropped
            . "Godrej Properties Chart Analysis: What the Price Trend Shows"
        )]);

        $this->postJson(route('admin.title-suggestions'), ['title' => 'GODREJ PROPERTY', 'type' => 'analysis', 'support' => '1533', 'resistance' => '1574 - 1580'])
            ->assertOk()
            ->assertJsonPath('source', 'gemini')
            ->assertJsonPath('titles', [
                'Godrej Properties Share Price: Support, Resistance and Key Levels',
                'Godrej Properties Technical Analysis: Trend and Chart Levels',
                'Godrej Properties Share Price Outlook: Levels Traders Watch',
                'Godrej Properties Technical View: Support Zone and Resistance Area',
                'Godrej Properties Chart Analysis: What the Price Trend Shows',
            ]);

        Http::assertSent(fn ($r) => str_contains($r['contents'][0]['parts'][0]['text'], 'GODREJ PROPERTY')
            && str_contains($r['contents'][0]['parts'][0]['text'], 'Support: 1533, Resistance: 1574 - 1580')
            && str_contains($r['contents'][0]['parts'][0]['text'], 'technical analysis article'));
    }

    public function test_technical_analysis_gets_ready_made_titles_when_the_free_allowance_is_used_up(): void
    {
        Http::fake(['generativelanguage.googleapis.com/*' => self::geminiLimit()]);

        $this->postJson(route('admin.title-suggestions'), ['title' => 'GODREJ PROPERTY', 'type' => 'analysis', 'support' => '1533', 'resistance' => '1580'])
            ->assertOk()
            ->assertJsonPath('source', 'patterns')
            ->assertJsonPath('titles.0', 'Godrej Property Share Price: Support, Resistance & Key Levels')
            ->assertJsonPath('titles.1', 'Godrej Property Share Price: Support at ₹1,533, Resistance at ₹1,580')
            ->assertJson(fn ($json) => $json->where('note', fn ($note) => str_contains($note, 'Gemini limit reached') && str_contains($note, 'ready-made'))->etc());

        // News titles can't be built from a pattern: the admin is told why there are none
        $this->postJson(route('admin.title-suggestions'), ['title' => 'RBI keeps repo rate unchanged', 'type' => 'news'])
            ->assertStatus(502)->assertJsonPath('message', 'Gemini limit reached (requests per minute, the daily free limit or the billing account cap). Please try again shortly, or check billing in aistudio.google.com.');
    }

    public function test_the_button_is_on_the_article_forms(): void
    {
        foreach (['admin.technical-analysis.create' => 'analysis', 'admin.news.create' => 'news', 'admin.latest-news.create' => 'news', 'admin.editor-articles.create' => 'article'] as $route => $type) {
            $this->get(route($route))->assertOk()->assertSee('Suggest SEO titles (free AI)')->assertSee('data-type="' . $type . '"', false);
        }

        $this->postJson(route('admin.title-suggestions'), ['title' => '', 'type' => 'analysis'])->assertStatus(422);
    }
}
