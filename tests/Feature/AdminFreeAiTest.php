<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use App\Services\NewsWriter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Admin -> Settings -> Gemini AI (the main AI) and Free AI backup (Groq). */
class AdminFreeAiTest extends TestCase
{
    use RefreshDatabase;

    private const KEY = 'gsk_abcdefghijklmnopqrstuvwxyz0123456789ABCD';

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        config(['services.gemini_ai.key' => null, 'services.huggingface_ai.token' => null,
            'services.groq_ai.key' => null, 'services.groq_ai.models' => ['llama-3.3-70b-versatile', 'openai/gpt-oss-120b']]);
        $this->actingAs(User::forceCreate(['name' => 'Administrator', 'username' => 'admin-ai', 'email' => 'admin-ai@example.com', 'role' => 'admin', 'password' => bcrypt('secret')]));
    }

    private static function groqSays(string $text)
    {
        return Http::response(['choices' => [['message' => ['role' => 'assistant', 'content' => $text]]]]);
    }

    public function test_a_working_key_is_tested_saved_encrypted_and_never_shown_in_full(): void
    {
        Http::fake(['api.groq.com/*' => self::groqSays('OK')]);

        $this->get(route('admin.settings.edit'))->assertOk()->assertSee('Free AI backup for articles (Groq)')->assertDontSee('Connected. Key ending');
        $this->post(route('admin.settings.ai'), ['groq_api_key' => self::KEY])->assertSessionHasNoErrors()
            ->assertSessionHas('success', fn ($message) => str_contains($message, 'Groq is connected (model llama-3.3-70b-versatile)'));

        $this->assertSame(self::KEY, Setting::groqKey());
        $this->assertStringNotContainsString(self::KEY, (string) Setting::get('groq_api_key')); // encrypted in the database
        $this->get(route('admin.settings.edit'))->assertOk()->assertSee('…ABCD')->assertDontSee(self::KEY);

        Http::assertSent(fn ($r) => $r->url() === 'https://api.groq.com/openai/v1/chat/completions'
            && $r->hasHeader('Authorization', 'Bearer ' . self::KEY) && $r['model'] === 'llama-3.3-70b-versatile');
    }

    public function test_a_model_the_account_cannot_use_is_skipped_and_a_wrong_key_is_not_saved(): void
    {
        Http::fake(['api.groq.com/*' => function ($request) {
            return $request['model'] === 'llama-3.3-70b-versatile'
                ? Http::response(['error' => ['message' => 'The model `llama-3.3-70b-versatile` has been decommissioned']], 400)
                : self::groqSays('OK');
        }]);
        $this->post(route('admin.settings.ai'), ['groq_api_key' => self::KEY])->assertSessionHasNoErrors();
        $this->assertSame('openai/gpt-oss-120b', Setting::groqModel());

        Setting::set('groq_api_key', '');
        Http::swap(new \Illuminate\Http\Client\Factory);
        Http::fake(['api.groq.com/*' => Http::response(['error' => ['message' => 'Invalid API Key']], 401)]);
        $this->post(route('admin.settings.ai'), ['groq_api_key' => self::KEY])
            ->assertSessionHasErrors(['groq_api_key' => 'Not saved: Groq did not accept the API key. Copy it again from console.groq.com (it starts with gsk_).']);
        $this->assertNull(Setting::groqKey());

        $this->post(route('admin.settings.ai'), ['groq_api_key' => 'hello world'])->assertSessionHasErrors('groq_api_key');
    }

    public function test_text_is_written_by_gemini_first_and_by_groq_when_gemini_is_over_its_limit(): void
    {
        self::geminiOn();
        Setting::set('groq_api_key', \Illuminate\Support\Facades\Crypt::encryptString(self::KEY));
        Setting::set('groq_model', 'llama-3.3-70b-versatile');

        Http::fake(['generativelanguage.googleapis.com/*' => self::geminiSays("Adani Ports Share Price: Support, Resistance and Key Levels\nAdani Ports Technical Analysis: Trend and Chart Levels"),
            'api.groq.com/*' => self::groqSays("From Groq title one is long enough to be kept\nFrom Groq title two is long enough to be kept")]);
        $titles = app(NewsWriter::class)->titles('Adani Ports', 'analysis');
        $this->assertSame('Adani Ports Share Price: Support, Resistance and Key Levels', $titles['titles'][0]);
        $this->assertSame('gemini', $titles['source']);
        Http::assertNotSent(fn ($r) => str_contains($r->url(), 'api.groq.com'));

        // Gemini over its limit (or out of balance): the free Groq writes instead
        Http::swap(new \Illuminate\Http\Client\Factory);
        Http::fake(['generativelanguage.googleapis.com/*' => self::geminiLimit(),
            'api.groq.com/*' => self::groqSays("Adani Ports Share Price Outlook: Levels Traders Watch\nAdani Ports Chart Analysis: What the Price Trend Shows")]);
        $titles = app(NewsWriter::class)->titles('Adani Ports', 'analysis');
        $this->assertSame('Adani Ports Share Price Outlook: Levels Traders Watch', $titles['titles'][0]);
        $this->assertSame('groq', $titles['source']);

        $this->post(route('admin.settings.ai'), ['remove' => '1'])->assertSessionHasNoErrors();
        $this->assertNull(Setting::groqKey());
    }

    public function test_a_gemini_key_is_tested_saved_encrypted_with_the_daily_picture_limit(): void
    {
        $key = 'AIzaSyAbcdefghijklmnopqrstuvwxyz0123456';
        Http::fake(['generativelanguage.googleapis.com/*' => self::geminiSays('OK')]);

        $this->get(route('admin.settings.edit'))->assertOk()->assertSee('Gemini AI (articles, titles and translations)')->assertDontSee('…3456');
        $this->post(route('admin.settings.gemini'), ['gemini_api_key' => $key, 'ai_image_daily_limit' => 40])->assertSessionHasNoErrors()
            ->assertSessionHas('success', fn ($m) => str_contains($m, 'Gemini is connected (model gemini-3.1-flash-lite)') && str_contains($m, 'limit: 40'));

        $this->assertSame($key, Setting::geminiKey());
        $this->assertStringNotContainsString($key, (string) Setting::get('gemini_api_key'));
        $this->assertSame(40, Setting::aiImageDailyLimit());
        $this->get(route('admin.settings.edit'))->assertOk()->assertSee('…3456')->assertDontSee($key);
        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/models/gemini-3.1-flash-lite:generateContent') && $r->hasHeader('x-goog-api-key', $key));

        // Only the limit changed: the key is kept
        $this->post(route('admin.settings.gemini'), ['gemini_api_key' => '', 'ai_image_daily_limit' => 10])->assertSessionHasNoErrors();
        $this->assertSame($key, Setting::geminiKey());
        $this->assertSame(10, Setting::aiImageDailyLimit());

        // A wrong key is not saved
        Setting::set('gemini_api_key', '');
        Http::swap(new \Illuminate\Http\Client\Factory);
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response(['error' => ['code' => 400, 'message' => 'API key not valid. Please pass a valid API key.', 'status' => 'INVALID_ARGUMENT']], 400)]);
        $this->post(route('admin.settings.gemini'), ['gemini_api_key' => $key, 'ai_image_daily_limit' => 10])
            ->assertSessionHasErrors('gemini_api_key');
        $this->assertStringStartsWith('Key not saved: Gemini did not accept the API key', session('errors')->first('gemini_api_key'));
        $this->assertNull(Setting::geminiKey());
    }

    public function test_a_news_article_that_is_too_long_or_too_short_is_rewritten_to_200_to_250_words(): void
    {
        Setting::set('groq_api_key', \Illuminate\Support\Facades\Crypt::encryptString(self::KEY));
        $reply = fn (int $words) => self::groqSays("TITLE: RBI Keeps the Repo Rate Unchanged at 5.5% in Its October Policy\nSUMMARY: The RBI kept the repo rate at 5.5%.\nKEYWORDS: RBI repo rate, RBI policy\nARTICLE:\n"
            . trim(str_repeat('word ', $words)));

        Http::fake(['api.groq.com/*' => Http::sequence()->pushResponse($reply(320))->pushResponse($reply(150))->pushResponse($reply(232))]);
        $draft = app(NewsWriter::class)->draft('RBI holds repo rate at 5.5%', 'The RBI kept the repo rate at 5.5% in October.', null);

        $this->assertSame(232, str_word_count(strip_tags($draft['content'])));
        Http::assertSent(fn ($r) => str_contains(json_encode($r['messages']), 'The ARTICLE has 320 words') && str_contains(json_encode($r['messages']), 'Make each paragraph shorter'));
        Http::assertSentCount(3);
    }
}
