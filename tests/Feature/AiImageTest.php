<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiImageTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::forceCreate([
            'name' => 'Administrator', 'username' => 'admin-ai', 'email' => 'admin-ai@example.com',
            'role' => 'admin', 'password' => bcrypt('secret'),
        ]);
    }

    private const NEURONS = ['errors' => [['message' => 'AiError: you have used up your daily free allocation of 10,000 neurons']], 'success' => false];

    public function test_generates_a_free_cloudflare_picture_and_never_uses_gemini(): void
    {
        self::geminiOn(); // Gemini writes text only, even when its key is saved
        self::cloudflareOn();
        Http::fake(['api.cloudflare.com/*' => self::cloudflarePicture('fake-jpeg')]);

        $this->actingAs($this->admin())
            ->postJson(route('admin.ai-image'), ['prompt' => 'Bull market in Mumbai', 'style' => true])
            ->assertOk()
            ->assertJson(['image' => 'data:image/jpeg;base64,' . base64_encode('fake-jpeg'), 'provider' => 'cloudflare']);

        // The admin's own description, shot as a realistic news photo (never "illustration")
        Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/accounts/acc123/ai/run/@cf/black-forest-labs/flux-1-schnell')
            && $request->hasHeader('Authorization', 'Bearer cf-token')
            && str_starts_with($request['prompt'], 'Award-winning editorial news photograph: Bull market in Mumbai.')
            && str_contains($request['prompt'], 'A real photograph, photorealistic')
            && !str_contains(strtolower($request['prompt']), 'illustration'));
        Http::assertNotSent(fn (Request $request) => str_contains($request->url(), 'generativelanguage.googleapis.com'));
    }

    /** Market Trend's "Generate with AI": the owner's painted style, a bull (or a bear for a falling headline) with the NSE building */
    public function test_market_trend_pictures_are_the_bull_and_bear_style(): void
    {
        self::cloudflareOn();
        Http::fake(['api.cloudflare.com/*' => self::cloudflarePicture('fake-jpeg')]);
        $this->actingAs($this->admin());

        // Made by the premium model (FLUX.2 [klein], a real 16:9 picture), sent as a form
        $field = fn (Request $r, string $name) => (string) (collect($r->data())->firstWhere('name', $name)['contents'] ?? '');
        $premium = fn (Request $r) => str_contains($r->url(), 'flux-2-klein-9b') && $field($r, 'width') === '1280' && $field($r, 'height') === '720';
        $this->postJson(route('admin.ai-image'), ['prompt' => 'Sensex falls 400 points as banks slide', 'style' => true, 'ratio' => '16:9', 'look' => 'bull-bear', 'from_headline' => true])->assertOk();
        Http::assertSent(fn (Request $r) => $premium($r) && str_contains($field($r, 'prompt'), 'Hyper-realistic cinematic financial news image')
            && str_contains($field($r, 'prompt'), 'showing only the letters NSE') && str_contains($field($r, 'prompt'), 'true bear anatomy')
            && str_contains($field($r, 'prompt'), 'arrowhead pointing at the ground'));

        $this->postJson(route('admin.ai-image'), ['prompt' => 'Nifty rises for a third day', 'style' => true, 'ratio' => '16:9', 'look' => 'bull-bear'])->assertOk();
        Http::assertSent(fn (Request $r) => $premium($r) && str_contains($field($r, 'prompt'), 'true bull anatomy') && str_contains($field($r, 'prompt'), 'arrowhead pointing at the sky'));
        // Other pictures keep the usual FLUX.1 model
        Http::assertNotSent(fn (Request $r) => str_contains($r->url(), 'flux-1-schnell'));

        // Only this one look; other forms keep the news photo style
        $this->postJson(route('admin.ai-image'), ['prompt' => 'x y z', 'look' => 'something'])->assertStatus(422);
        $this->assertSame(-1, \App\Http\Controllers\Admin\AiImageController::moodOf('Nifty slips below 22500'));
        $this->assertSame(1, \App\Http\Controllers\Admin\AiImageController::moodOf('Market Trend Analysis: banks lead'));
    }

    /** A square picture saved in Market Trend fills the 16:9 picture box: no blurred bands at the sides */
    public function test_market_trend_pictures_fill_the_box_without_blurred_sides(): void
    {
        \Illuminate\Support\Facades\Storage::fake('public');
        $this->actingAs($this->admin())->post(route('admin.news.store'), ['title' => 'Nifty ends higher', 'status' => 'active',
            'image' => \Illuminate\Http\UploadedFile::fake()->image('square.jpg', 800, 800)])->assertSessionHasNoErrors();

        $path = \App\Models\News::firstOrFail()->image;
        [$w, $h] = getimagesize(\Illuminate\Support\Facades\Storage::disk('public')->path($path));
        $this->assertEqualsWithDelta(16 / 9, $w / $h, 0.02);
    }

    public function test_cloudflare_used_up_falls_back_to_pollinations_without_a_key(): void
    {
        self::cloudflareOn();
        config(['services.pollinations.enabled' => true]);
        Http::fake([
            'api.cloudflare.com/*' => Http::response(self::NEURONS, 429),
            'image.pollinations.ai/*' => Http::response('pol-bytes', 200, ['Content-Type' => 'image/jpeg']),
        ]);

        $this->actingAs($this->admin())
            ->postJson(route('admin.ai-image'), ['prompt' => 'Bull market', 'ratio' => '16:10'])
            ->assertOk()
            ->assertJson(['image' => 'data:image/jpeg;base64,' . base64_encode('pol-bytes'), 'provider' => 'pollinations']);

        Http::assertSent(fn (Request $r) => str_starts_with($r->url(), 'https://image.pollinations.ai/prompt/Award-winning%20editorial')
            && str_contains($r->url(), 'width=1024') && str_contains($r->url(), 'height=640') && !$r->hasHeader('Authorization'));
    }

    public function test_a_pollinations_key_uses_the_clean_picture_api(): void
    {
        config(['services.pollinations.enabled' => true, 'services.pollinations.key' => 'pk_test']);
        Http::fake(['gen.pollinations.ai/*' => Http::response('png-bytes', 200, ['Content-Type' => 'image/png'])]);

        $this->actingAs($this->admin())
            ->postJson(route('admin.ai-image'), ['prompt' => 'Bull market'])
            ->assertOk()
            ->assertJson(['image' => 'data:image/png;base64,' . base64_encode('png-bytes'), 'provider' => 'pollinations']);

        Http::assertSent(fn (Request $r) => str_starts_with($r->url(), 'https://gen.pollinations.ai/image/')
            && str_contains($r->url(), 'model=flux') && $r->hasHeader('Authorization', 'Bearer pk_test'));
    }

    public function test_a_headline_becomes_a_name_free_real_photo_scene(): void
    {
        self::cloudflareOn();
        Http::fake(['api.cloudflare.com/*' => self::cloudflarePicture()]);

        $this->actingAs($this->admin())
            ->postJson(route('admin.ai-image'), ['prompt' => 'PC Jeweller stock rises 4 percent', 'style' => true, 'ratio' => '16:9', 'from_headline' => true])
            ->assertOk();

        // Company name left out (it would become a fake logo); one concrete scene + mood instead
        Http::assertSent(fn (Request $request) => !str_contains($request['prompt'], 'PC Jeweller')
            && str_contains($request['prompt'], 'gold ingots')
            && str_contains($request['prompt'], 'optimistic mood'));
    }

    public function test_without_keys_uses_the_free_hugging_face_space_at_the_right_size(): void
    {
        config(['services.huggingface_ai.token' => null]);
        $space = config('services.huggingface_ai.space');
        Http::fake([
            $space . '/gradio_api/call/infer' => Http::response(['event_id' => 'ev1']),
            $space . '/gradio_api/call/infer/ev1' => Http::response("event: complete\ndata: [{\"url\": \"{$space}/gradio_api/file=/tmp/x/image.webp\"}, 123]\n\n"),
            $space . '/gradio_api/file=*' => Http::response('webp-bytes', 200, ['Content-Type' => 'image/webp']),
        ]);

        $this->actingAs($this->admin())
            ->postJson(route('admin.ai-image'), ['prompt' => 'Analyst portrait', 'ratio' => '1:1'])
            ->assertOk()
            ->assertJson(['image' => 'data:image/webp;base64,' . base64_encode('webp-bytes'), 'provider' => 'huggingface']);

        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/gradio_api/call/infer')
            && $r['data'][3] === 768 && $r['data'][4] === 768 && !$r->hasHeader('Authorization'));
    }

    public function test_free_limit_reached_message(): void
    {
        config(['services.huggingface_ai.token' => null]);
        $space = config('services.huggingface_ai.space');
        Http::fake([
            $space . '/gradio_api/call/infer' => Http::response(['event_id' => 'ev2']),
            $space . '/gradio_api/call/infer/ev2' => Http::response("event: error\ndata: null\n\n"),
        ]);

        $this->actingAs($this->admin())
            ->postJson(route('admin.ai-image'), ['prompt' => 'Bull market'])
            ->assertStatus(502)
            ->assertJsonFragment(['message' => 'The free no-account Hugging Face limit (about 3 pictures a day) is used up. Try again tomorrow.']);
    }

    public function test_explains_when_every_free_service_is_used_up(): void
    {
        self::cloudflareOn();
        config(['services.pollinations.enabled' => true, 'services.huggingface_ai.token' => null]);
        $space = config('services.huggingface_ai.space');
        Http::fake([
            'api.cloudflare.com/*' => Http::response(self::NEURONS, 429),
            'image.pollinations.ai/*' => Http::response(['error' => 'Too many requests'], 429),
            $space . '/gradio_api/call/infer' => Http::response(['event_id' => 'ev3']),
            $space . '/gradio_api/call/infer/ev3' => Http::response("event: error\ndata: null\n\n"),
        ]);

        $this->actingAs($this->admin())
            ->postJson(route('admin.ai-image'), ['prompt' => 'Bull market'])
            ->assertStatus(502)
            ->assertJsonPath('message', fn ($m) => str_starts_with($m, "Cloudflare's free AI pictures for today are used up") && str_contains($m, 'The other free AI services are also busy'));
    }

    public function test_auto_published_news_never_gets_a_marked_no_key_picture(): void
    {
        config(['services.pollinations.enabled' => true, 'services.huggingface_ai.token' => null]);
        $space = config('services.huggingface_ai.space');
        Http::fake([
            $space . '/gradio_api/call/infer' => Http::response(['event_id' => 'ev5']),
            $space . '/gradio_api/call/infer/ev5' => Http::response("event: error\ndata: null\n\n"),
        ]);

        $this->assertNull(app(\App\Services\NewsWriter::class)->image('Sensex rises 500 points'));
        Http::assertNotSent(fn (Request $r) => str_contains($r->url(), 'pollinations.ai'));
    }

    public function test_the_admin_daily_picture_limit_stops_generating(): void
    {
        self::cloudflareOn();
        \App\Models\Setting::set('ai_image_daily_limit', '1');
        Http::fake(['api.cloudflare.com/*' => self::cloudflarePicture()]);
        $admin = $this->admin();

        $this->actingAs($admin)->postJson(route('admin.ai-image'), ['prompt' => 'Bull market'])->assertOk();
        $this->actingAs($admin)->postJson(route('admin.ai-image'), ['prompt' => 'Bull market'])
            ->assertStatus(429)->assertJsonFragment(['message' => 'Daily AI image limit reached (1 images/day). Please try again tomorrow.']);
    }

    public function test_only_admins_can_generate(): void
    {
        $this->postJson(route('admin.ai-image'), ['prompt' => 'x'])->assertStatus(401);
    }
}
