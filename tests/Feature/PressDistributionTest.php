<?php

namespace Tests\Feature;

use App\Mail\ArticleMail;
use App\Models\News;
use App\Models\PressContact;
use App\Models\PressDelivery;
use App\Models\TechnicalAnalysis;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PressDistributionTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::forceCreate(['name' => 'Administrator', 'username' => 'admin-press', 'email' => 'admin-press@example.com', 'role' => 'admin', 'password' => bcrypt('secret')]);
    }

    private function analysisForm(array $extra = []): array
    {
        return $extra + [
            'title' => 'SBI Cards: Support, Resistance & Key Levels', 'support' => '618', 'resistance' => '660',
            'description' => 'SBI Cards key levels.', 'content' => "First paragraph.\n\nSecond paragraph.", 'status' => 'active',
            'press_list' => '1',
        ];
    }

    public function test_rows_typed_on_the_form_are_remembered_for_both_forms(): void
    {
        Mail::fake();
        $admin = $this->admin();

        $this->actingAs($admin)->get(route('admin.technical-analysis.create'))->assertOk()
            ->assertSee('Send this article')->assertSee('Add email')->assertDontSee('Add channel')->assertDontSee('Channel name:')
            ->assertSee('name="send_press" value="1" checked', false); // ticked by default

        // Saved without ticking "Send": rows are remembered, nothing is sent
        $this->actingAs($admin)->post(route('admin.technical-analysis.store'), $this->analysisForm([
            'channels' => [['name' => 'Biz Channel', 'url' => 'https://biz.example/api/submit'], ['name' => '', 'url' => '']],
            'emails' => [['name' => 'Ramesh Patel', 'email' => 'Ramesh@Example.com'], ['name' => '', 'email' => 'sita@example.com']],
        ]))->assertSessionHas('success', 'Technical analysis created successfully.');

        Mail::assertNothingSent();
        $this->assertSame(['Biz Channel'], PressContact::channels()->pluck('name')->all());
        $this->assertSame(['ramesh@example.com', 'sita@example.com'], PressContact::customers()->orderBy('id')->pluck('email')->all());
        $this->assertSame('sita', PressContact::customers()->where('email', 'sita@example.com')->value('name'));

        // Same list on the Market Trend form
        $this->actingAs($admin)->get(route('admin.news.create'))->assertOk()
            ->assertDontSee('https://biz.example/api/submit')->assertSee('ramesh@example.com'); // email list only

        // Removing a row removes it from the list; a bad link is refused
        $this->actingAs($admin)->put(route('admin.technical-analysis.update', TechnicalAnalysis::first()), $this->analysisForm([
            'channels' => [['name' => 'Biz Channel', 'url' => 'https://biz.example/api/submit']],
            'emails' => [['name' => 'Ramesh Patel', 'email' => 'ramesh@example.com']],
        ]));
        $this->assertSame(1, PressContact::customers()->count());
        $this->actingAs($admin)->post(route('admin.technical-analysis.store'), $this->analysisForm(['channels' => [['name' => 'X', 'url' => 'not a link']]]))
            ->assertSessionHasErrors('channels.0.url');

        // A save that doesn't carry the box (no press_list) never wipes the list
        $this->actingAs($admin)->put(route('admin.news.update', News::create(['title' => 'T', 'description' => 'D', 'status' => 'active'])), ['title' => 'T', 'description' => 'D', 'status' => 'active']);
        $this->assertSame(2, PressContact::count());
    }

    public function test_a_long_imported_list_is_saved_whole_from_the_json_field(): void
    {
        // More rows than PHP's max_input_vars (1000) would let through as separate fields
        $emails = array_map(fn ($n) => ['name' => "Reader {$n}", 'email' => "reader{$n}@example.com"], range(1, 1200));
        $json = json_encode(['channels' => [['name' => 'Biz', 'url' => 'https://biz.example/api/submit']], 'emails' => $emails]);

        $this->actingAs($this->admin())->post(route('admin.technical-analysis.store'), $this->analysisForm(['press_json' => $json]))
            ->assertSessionHas('success');

        $this->assertSame(1200, PressContact::customers()->count());
        $this->assertSame(1, PressContact::channels()->count());
        $this->assertTrue(PressContact::customers()->where('email', 'reader1200@example.com')->where('name', 'Reader 1200')->exists());
    }

    public function test_ticking_send_posts_to_channel_links_and_emails_everyone(): void
    {
        Mail::fake();
        Http::fake([
            'biz.example/*' => Http::response(['ok' => true]),
            'plain.example/*' => Http::response('<html>form</html>', 200, ['Content-Type' => 'text/html']),
        ]);

        $this->actingAs($this->admin())->post(route('admin.technical-analysis.store'), $this->analysisForm([
            'channels' => [['name' => 'Biz Channel', 'url' => 'https://biz.example/api/submit'], ['name' => 'Plain Site', 'url' => 'https://plain.example/contact']],
            'emails' => [['name' => 'Ramesh', 'email' => 'ramesh@example.com']],
            'send_press' => '1',
        ]))->assertRedirect(route('admin.technical-analysis.index'))
            ->assertSessionHas('success', fn ($m) => str_contains($m, 'Sending it to 3 emails'));

        $analysis = TechnicalAnalysis::first();

        // The channel's link gets the full article as JSON
        Http::assertSent(fn ($r) => $r->url() === 'https://biz.example/api/submit'
            && $r['title'] === $analysis->title
            // links point at the live site, not the address the admin panel runs on
            && $r['article_url'] === str_replace(rtrim(url('/'), '/'), 'https://investmentpoint.in', $analysis->url)
            && str_starts_with($r['article_url'], 'https://investmentpoint.in/')
            && $r['support'] === '618' && str_contains($r['content_html'], '<p>First paragraph.</p>') && isset($r['image_url']));
        Mail::assertSent(ArticleMail::class, fn ($m) => $m->hasTo('ramesh@example.com') && !$m->forChannel && $m->article['title'] === $analysis->title);

        // Results are logged; a normal web page is reported as failed, not as sent
        $this->assertSame(2, PressDelivery::where('status', 'sent')->count());
        $this->assertSame('failed', PressDelivery::where('target', 'https://plain.example/contact')->value('status'));
        $this->get(route('admin.technical-analysis.edit', $analysis))->assertSee('Sent before:')->assertSee('Biz Channel · sent', false);
    }

    public function test_attached_files_of_any_type_go_with_every_email_and_are_deleted_after(): void
    {
        Mail::fake();
        Storage::fake('local');
        $files = [
            UploadedFile::fake()->create('Market Report.pdf', 300, 'application/pdf'),
            UploadedFile::fake()->create('notes.docx', 120, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
            UploadedFile::fake()->image('chart.jpg'),
            UploadedFile::fake()->createWithContent('levels.txt', 'Support 618'),
            UploadedFile::fake()->create('Charts Oct.rar', 200, 'application/vnd.rar'),
        ];

        $this->actingAs($this->admin())->post(route('admin.technical-analysis.store'), $this->analysisForm([
            'emails' => [['name' => 'Ramesh', 'email' => 'ramesh@example.com'], ['name' => 'Sita', 'email' => 'sita@example.com']],
            'press_files' => $files,
            'send_press' => '1',
        ]))->assertSessionHas('success', fn ($m) => str_contains($m, 'with 5 attached files'));

        $names = ['Market Report.pdf', 'notes.docx', 'chart.jpg', 'levels.txt', 'Charts Oct.rar'];
        foreach (['ramesh@example.com', 'sita@example.com'] as $to) {
            Mail::assertSent(ArticleMail::class, fn ($m) => $m->hasTo($to) && array_column($m->files, 'name') === $names);
        }
        // The copies kept for sending are gone once the emails are out
        $this->assertSame([], Storage::disk('local')->allFiles('press-attachments'));
    }

    public function test_the_email_carries_the_files_and_lists_them(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'ip');
        file_put_contents($path, 'Support 618');
        $mail = new ArticleMail(['category' => 'Technical Analysis', 'title' => 'SBI Cards', 'content_html' => '<p>Hi</p>', 'article_url' => 'https://x.test', 'source_url' => 'https://x.test'], false, [['path' => $path, 'name' => 'levels.txt', 'mime' => 'text/plain']]);

        $this->assertCount(1, $mail->attachments());
        $mail->assertSeeInHtml('Attached file:')->assertSeeInHtml('levels.txt');
        unlink($path);
    }

    public function test_files_that_are_too_big_are_refused(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->post(route('admin.technical-analysis.store'), $this->analysisForm([
            'press_files' => [UploadedFile::fake()->create('huge.zip', 11000)],
        ]))->assertSessionHasErrors('press_files.0');

        $this->actingAs($admin)->post(route('admin.technical-analysis.store'), $this->analysisForm([
            'press_files' => [UploadedFile::fake()->create('a.pdf', 9000), UploadedFile::fake()->create('b.pdf', 9000), UploadedFile::fake()->create('c.pdf', 9000)],
        ]))->assertSessionHasErrors('press_files');
    }

    public function test_nothing_is_sent_for_an_inactive_article(): void
    {
        Mail::fake();
        $admin = $this->admin();
        $rows = ['press_list' => '1', 'emails' => [['name' => 'Ramesh', 'email' => 'ramesh@example.com']], 'send_press' => '1'];

        $this->actingAs($admin)->post(route('admin.news.store'), ['title' => 'Sensex rallies', 'description' => 'Summary', 'status' => 'inactive'] + $rows)
            ->assertSessionHas('success', fn ($m) => str_contains($m, 'Not sent: the article is Inactive'));
        Mail::assertNothingSent();

        $this->actingAs($admin)->put(route('admin.news.update', News::first()), ['title' => 'Sensex rallies', 'description' => 'Summary', 'status' => 'active'] + $rows);
        Mail::assertSent(ArticleMail::class, fn ($m) => $m->hasTo('ramesh@example.com') && $m->article['category'] === 'Market Trend');
    }
}
