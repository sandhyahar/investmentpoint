<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** "Auto-translate Gujarati / Hindi to English" on the article forms: Google Translate, the free AI as backup. */
class AdminTranslationTest extends TestCase
{
    use RefreshDatabase;

    private const GUJARATI = 'શેરબજારમાં આજે તેજી જોવા મળી.';

    private const HINDI = 'शेयर बाजार में आज तेजी देखने को मिली।';

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        config(['services.gemini_ai.key' => 'AIzaTestKey0123456789012345678901234567', 'services.huggingface_ai.token' => null]);
        $this->actingAs(User::forceCreate(['name' => 'Administrator', 'username' => 'admin-tr', 'email' => 'admin-tr@example.com', 'role' => 'admin', 'password' => bcrypt('secret')]));
    }

    /** The pieces ("q") of one request to Google */
    private static function pieces($request): array
    {
        preg_match_all('/(?:^|&)q=([^&]*)/', $request->body(), $m);

        return array_map('rawurldecode', $m[1]);
    }

    /** Fake Google: every piece comes back as "English N" (N counts up over the whole test), line breaks kept */
    private function fakeGoogle(): void
    {
        $n = 0;
        Http::fake(['clients5.google.com/*' => function ($request) use (&$n) {
            return Http::response(array_map(function ($piece) use (&$n) {
                return [implode("\n", array_map(function () use (&$n) { return 'English ' . ++$n . '.'; }, explode("\n", $piece))), 'gu'];
            }, self::pieces($request)));
        }]);
    }

    public function test_a_gujarati_or_hindi_title_becomes_english_and_english_is_left_alone(): void
    {
        $this->fakeGoogle();

        $this->postJson(route('admin.translate'), ['text' => 'Sensex closes higher on banking stocks'])
            ->assertOk()->assertExactJson(['text' => 'Sensex closes higher on banking stocks', 'translated' => false]);
        Http::assertNothingSent();

        $this->postJson(route('admin.translate'), ['text' => self::GUJARATI])->assertOk()->assertExactJson(['text' => 'English 1.', 'translated' => true]);
        $this->postJson(route('admin.translate'), ['text' => self::HINDI])->assertOk()->assertJsonPath('text', 'English 2.');

        // Google Translate, not the AI
        Http::assertSent(fn ($r) => str_starts_with($r->url(), 'https://clients5.google.com/translate_a/t') && self::pieces($r) === [self::GUJARATI]);
        Http::assertNotSent(fn ($r) => str_contains($r->url(), 'generativelanguage.googleapis.com'));
    }

    public function test_an_article_from_the_editor_keeps_its_structure_and_its_english_parts(): void
    {
        $this->fakeGoogle();
        $html = '<h2><strong>' . self::GUJARATI . '</strong></h2>'
            . '<p>' . self::GUJARATI . '<br>' . self::HINDI . '</p>'
            . '<p>Already English: Nifty at 22,707.</p>'
            . '<ul><li>' . self::HINDI . '</li><li>TCS</li></ul>'
            . '<table><tbody><tr><td>' . self::GUJARATI . '</td><td>1,533</td></tr></tbody></table>';

        $this->postJson(route('admin.translate'), ['text' => $html, 'html' => true])
            ->assertOk()
            ->assertJsonPath('text', '<h2><strong>English 1.</strong></h2>'
                . '<p>English 2.<br>English 3.</p>'
                . '<p>Already English: Nifty at 22,707.</p>'
                . '<ul><li>English 4.</li><li>TCS</li></ul>'
                . '<table><tbody><tr><td>English 5.</td><td>1,533</td></tr></tbody></table>');

        // All four pieces in one request; the English paragraph, "TCS" and the number were not sent
        Http::assertSentCount(1);
        Http::assertSent(fn ($r) => self::pieces($r) === [self::GUJARATI, self::GUJARATI . "\n" . self::HINDI, self::HINDI, self::GUJARATI]);
    }

    public function test_hidden_parts_copied_from_a_web_page_do_not_end_up_in_the_article(): void
    {
        $this->fakeGoogle();
        // As copied from Google's search results: a hidden tracking span in the middle of the sentence
        $html = '<p><span>વર્તમાન સમાચાર આધારિત લેખ:</span><span style="display: none;">TgQPHd|||[]</span><span> ચાલતી ઘટનાઓ કે રાજકારણ પર વિશ્લેષણ.</span><!-- note --></p>';

        $this->postJson(route('admin.translate'), ['text' => $html, 'html' => true])->assertOk()->assertJsonPath('text', '<p>English 1.</p>');
        Http::assertSent(fn ($r) => self::pieces($r) === ['વર્તમાન સમાચાર આધારિત લેખ: ચાલતી ઘટનાઓ કે રાજકારણ પર વિશ્લેષણ.']);

        // The same marker arriving as ordinary text is dropped too
        $this->postJson(route('admin.translate'), ['text' => 'વર્તમાન સમાચાર આધારિત લેખ:TgQPHd|||[] ચાલતી ઘટનાઓ'])->assertOk();
        Http::assertSent(fn ($r) => self::pieces($r) === ['વર્તમાન સમાચાર આધારિત લેખ: ચાલતી ઘટનાઓ']);
    }

    public function test_a_long_article_is_sent_in_several_requests(): void
    {
        $this->fakeGoogle();
        $paragraph = '<p>' . trim(str_repeat(self::GUJARATI . ' ', 30)) . '</p>'; // about 900 characters

        $this->postJson(route('admin.translate'), ['text' => str_repeat($paragraph, 5), 'html' => true])
            ->assertOk()->assertJsonPath('text', '<p>English 1.</p><p>English 2.</p><p>English 3.</p><p>English 4.</p><p>English 5.</p>');

        Http::assertSentCount(3); // 2 + 2 + 1 paragraphs
    }

    public function test_the_free_ai_is_the_backup_when_google_does_not_answer(): void
    {
        Http::fake([
            'clients5.google.com/*' => Http::response('<html><title>Sorry...</title></html>', 429),
            'generativelanguage.googleapis.com/*' => self::geminiSays('The stock market rose today.'),
        ]);

        $this->postJson(route('admin.translate'), ['text' => self::GUJARATI])->assertOk()->assertJsonPath('text', 'The stock market rose today.');
    }

    public function test_the_admin_is_told_when_neither_service_works(): void
    {
        Http::fake([
            'clients5.google.com/*' => Http::response('<html><title>Sorry...</title></html>', 429),
            'generativelanguage.googleapis.com/*' => self::geminiLimit(),
        ]);

        $this->postJson(route('admin.translate'), ['text' => self::GUJARATI])
            ->assertStatus(502)
            ->assertJson(fn ($json) => $json->where('message', fn ($m) => str_starts_with($m, 'Google Translate did not answer') && str_contains($m, 'try again in a minute')));
    }

    public function test_the_switch_is_on_the_article_forms(): void
    {
        foreach (['admin.technical-analysis.create', 'admin.news.create', 'admin.latest-news.create', 'admin.editor-articles.create'] as $route) {
            $this->get(route($route))->assertOk()->assertSee('Auto-translate Gujarati / Hindi to English')->assertSee('Google Translate')
                ->assertSee('data-url="' . route('admin.translate') . '"', false);
        }
    }
}
