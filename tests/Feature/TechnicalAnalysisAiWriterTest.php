<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\NewsWriter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TechnicalAnalysisAiWriterTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::forceCreate(['name' => 'Administrator', 'username' => 'admin-ta', 'email' => 'admin-ta@example.com', 'role' => 'admin', 'password' => bcrypt('secret')]);
    }

    /** A report reply as the AI gives it */
    private function reply(string $trendFirst = 'Adani Energy Solutions trades near ₹1,526 while power demand in India keeps rising steadily across states.'): string
    {
        $fill = fn (int $words) => implode(' ', array_fill(0, $words, 'grid'));

        return "COMPANY: Adani Energy Solutions Limited\nNSE: adanIensol\nBSE: 539254\nSECTOR: Power Transmission\n"
            . "SUMMARY: A neutral public appearance report on Adani Energy Solutions covering its business, market trend and key risk factors.\n"
            . 'OVERVIEW: Adani Energy Solutions builds and runs power transmission lines and city electricity distribution in India. ' . $fill(35) . "\n"
            . "TREND: {$trendFirst} " . $fill(30) . "\n\nThe support at ₹1,480 and resistance at ₹1,560 may be watched by traders. " . $fill(25) . "\n"
            . 'RISK: Regulatory changes, high debt and project delays may affect the company. ' . $fill(28);
    }

    public function test_write_with_ai_returns_the_public_appearance_report_in_the_fixed_format(): void
    {
        config(['services.gemini_ai.key' => 'AIzaTestKey0123456789012345678901234567']);
        Http::fake(['generativelanguage.googleapis.com/*' => self::geminiSays($this->reply())]);

        $response = $this->actingAs($this->admin())
            ->postJson(route('admin.technical-analysis.write'), [
                'title' => 'ADANIENSOL - Adani Energy Solutions', 'cmp' => '1526', 'support' => '1480', 'resistance' => '1560', 'published_on' => '2026-10-13',
            ])
            ->assertOk()
            ->assertJsonPath('provider', 'gemini')
            ->assertJsonPath('warnings', ['Check the NSE symbol, BSE code and sector the AI filled in: ADANIENSOL / 539254 / Power Transmission']);

        $html = $response->json('content');
        // The owner's format, in order, with the fixed lines word for word
        $this->assertStringStartsWith('<p style="text-align: justify;"><strong>Report Date :</strong> 13 October 2026</p>', $html); // AI paragraphs justified
        $order = ['<strong>NSE Symbol :</strong> ADANIENSOL | <strong>BSE Code :</strong> 539254', '<strong>Sector :</strong> Power Transmission',
            'Recommendation : Neutral Editorial Assessment – No Buy / Sell Recommendation', '<strong>Company Overview :</strong> Adani Energy Solutions builds',
            '<strong>Market Trend :</strong>', '<strong>Risk Factor :</strong> Regulatory changes',
            '<p style="color: #dc2626;"><strong>Overall Assessment: Neutral and Observation-Based. No target price or Buy, Sell or Hold recommendation is assigned.</strong></p>',
            'This report is intended exclusively for educational and informational purposes.'];
        $at = 0;
        foreach ($order as $part) {
            $found = strpos($html, $part, $at);
            $this->assertNotFalse($found, "Missing or out of order: {$part}");
            $at = $found;
        }
        // Market trend in 2 paragraphs, and no commas inside amounts
        $this->assertStringContainsString('<p style="text-align: justify;">The support at ₹1480 and resistance at ₹1560', $html);
        $this->assertStringContainsString('₹1526', $html);
        $this->assertDoesNotMatchRegularExpression('/\d,\d/', $html);
        // The red line survives the website's HTML cleaning
        $this->assertStringContainsString('color:#dc2626', str_replace(' ', '', \App\Support\Html::clean($html)));

        // The prompt carries the company title, CMP and levels as the only allowed figures
        Http::assertSent(fn ($r) => str_contains($r['contents'][0]['parts'][0]['text'], 'Title: ADANIENSOL - Adani Energy Solutions')
            && str_contains($r['contents'][0]['parts'][0]['text'], 'CMP: ₹1526') && str_contains($r['systemInstruction']['parts'][0]['text'], 'never 1,526'));
    }

    public function test_invented_figures_and_promotional_words_are_flagged(): void
    {
        config(['services.gemini_ai.key' => 'AIzaTestKey0123456789012345678901234567']);
        Http::fake(['generativelanguage.googleapis.com/*' => self::geminiSays($this->reply('It is a multibagger that could reach ₹2,400 with guaranteed returns.'))]);
        $admin = $this->admin();

        $warnings = $this->actingAs($admin)
            ->postJson(route('admin.technical-analysis.write'), ['title' => 'Adani Energy Solutions', 'cmp' => '1526', 'support' => '1480', 'resistance' => '1560'])
            ->assertOk()->json('warnings');
        $this->assertContains('Check these figures - they are not in your title, CMP or levels: 2400', $warnings);
        $this->assertContains('Not allowed under SEBI guidelines - please reword: multibagger, guaranteed returns', $warnings);

        $this->actingAs($admin)->get(route('admin.technical-analysis.create'))->assertOk()->assertSee('Write Full Analysis with AI');
        $this->actingAs($admin)->postJson(route('admin.technical-analysis.write'), [])->assertStatus(422);
    }

    public function test_an_incomplete_reply_is_retried_then_reported(): void
    {
        config(['services.gemini_ai.key' => 'AIzaTestKey0123456789012345678901234567']);
        Http::fake(['generativelanguage.googleapis.com/*' => self::geminiSays("COMPANY: Adani Energy Solutions\nOVERVIEW: A power company.")]);

        $this->actingAs($this->admin())
            ->postJson(route('admin.technical-analysis.write'), ['title' => 'Adani Energy Solutions'])
            ->assertStatus(502)->assertJsonPath('message', 'The AI reply was incomplete. Please click "Write Full Analysis with AI" again.');
        Http::assertSentCount(2);
    }

    public function test_report_parts_are_read_from_labelled_markdown_replies(): void
    {
        $parts = NewsWriter::reportParts("**Company:** Tata Steel\n**NSE Symbol:** tatasteel\n**BSE Code:** 500470\n**Sector:** Metals\n"
            . "**Overview:** Steel maker with plants in India and Europe and revenue of 2,29,171 crore.\n**Market Trend:** Line one.\n\nLine two.\n**Risk Factors:** Prices of 1,200 tonnes.");

        $this->assertSame(['Tata Steel', 'TATASTEEL', '500470', 'Metals'], [$parts['company'], $parts['nse'], $parts['bse'], $parts['sector']]);
        $this->assertSame('Steel maker with plants in India and Europe and revenue of 229171 crore.', $parts['overview']);
        $this->assertSame("Line one.\n\nLine two.", $parts['trend']);
        $this->assertSame('Prices of 1200 tonnes.', $parts['risk']);
    }
}
