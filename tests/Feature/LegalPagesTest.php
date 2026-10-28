<?php

namespace Tests\Feature;

use App\Models\LegalPage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LegalPagesTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::forceCreate([
            'name' => 'Administrator', 'username' => 'admin-legal', 'email' => 'admin-legal@example.com',
            'role' => 'admin', 'password' => bcrypt('secret'),
        ]);
    }

    public function test_every_legal_page_shows_its_original_text_until_edited(): void
    {
        $this->get(route('disclaimer'))->assertOk()->assertSee('Disclaimer &amp; Disclosure Statement', false)
            ->assertSee('General Disclaimer')->assertSee('Have a question? Ask us');
        $this->get(route('privacy'))->assertOk()->assertSee('Information We Collect')->assertSee('href="#sec-2"', false);
        $this->get(route('terms'))->assertOk()->assertSee('Acceptance of Agreement')->assertSee('Part II: Affiliate Program Terms of Service');
        $this->get(route('refund'))->assertOk()->assertSee('Strict No-Refund');
        $this->get(route('cookies'))->assertOk()->assertSee('Types of Cookies We Use')->assertSee('href="#sec-9"', false)
            ->assertSee('📅 Effective Date:') // emoji kept whole in the chip
            ->assertDontSee('<span><b></b></span>', false);
    }

    public function test_admin_can_edit_the_cookie_policy(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->get(route('admin.legal-pages.index'))->assertOk()->assertSee('Cookie Policy');
        $this->actingAs($admin)->get(route('admin.legal-pages.edit', 'cookies'))->assertOk()->assertSee('What Are Cookies?');

        $this->actingAs($admin)->put(route('admin.legal-pages.update', 'cookies'), [
            'title' => 'Cookie Policy', 'meta' => '📅 Effective Date: May 19, 2025',
            'content' => '<div class="tc-sec"><h3><em>1</em> Cookies</h3><p>Updated cookie text</p></div>',
        ])->assertRedirect(route('admin.legal-pages.edit', 'cookies'));

        $this->get(route('cookies'))->assertOk()->assertSee('Updated cookie text')->assertDontSee('What Are Cookies?');
    }

    public function test_admin_edit_shows_on_the_website_and_unsafe_code_is_removed(): void
    {
        $this->actingAs($this->admin())->put(route('admin.legal-pages.update', 'privacy'), [
            'title' => 'Our Privacy Promise',
            'subtitle' => 'Short and simple',
            'meta' => "📅 Effective Date: Jan 1, 2027",
            'content' => '<div class="tc-sec"><h3><em>1</em> New section</h3><p>Edited text</p><script>alert(1)</script></div>',
        ])->assertRedirect(route('admin.legal-pages.edit', 'privacy'));

        $this->assertStringNotContainsString('<script', LegalPage::where('slug', 'privacy')->value('content'));

        $this->get(route('privacy'))->assertOk()
            ->assertSee('Our Privacy Promise')->assertSee('Edited text')->assertSee('<b>Jan 1, 2027</b>', false)
            ->assertSee('<div class="tc-sec">', false)->assertSee('href="#sec-1"', false)
            ->assertDontSee('alert(1)', false)->assertDontSee('Information We Collect');
    }

    public function test_restore_goes_back_to_the_original_text(): void
    {
        LegalPage::create(['slug' => 'refund', 'title' => 'Changed', 'content' => '<p>Changed text</p>']);

        $this->actingAs($this->admin())->delete(route('admin.legal-pages.reset', 'refund'))->assertRedirect();

        $this->get(route('refund'))->assertOk()->assertSee('Strict No-Refund')->assertDontSee('Changed text');
    }

    public function test_admin_screens_open_and_guests_cannot_edit(): void
    {
        $this->put(route('admin.legal-pages.update', 'terms'), ['title' => 'x', 'content' => '<p>x</p>'])->assertRedirect();
        $this->assertDatabaseCount('legal_pages', 0);

        $admin = $this->admin();
        $this->actingAs($admin)->get(route('admin.legal-pages.index'))->assertOk()->assertSee('Terms of Refund &amp; Cancellation Policy', false);
        $this->actingAs($admin)->get(route('admin.legal-pages.edit', 'terms'))->assertOk()->assertSee('Acceptance of Agreement');
        $this->actingAs($admin)->get(route('admin.legal-pages.edit', 'unknown'))->assertNotFound();
    }

    public function test_likes_and_experts_are_gone(): void
    {
        $this->assertFalse(\Illuminate\Support\Facades\Route::has('like.toggle'));
        $this->assertFalse(\Illuminate\Support\Facades\Route::has('admin.experts.index'));
        $this->get('/')->assertOk()->assertDontSee('like-btn', false);
    }
}
