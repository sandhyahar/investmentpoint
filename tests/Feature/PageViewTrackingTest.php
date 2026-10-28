<?php

namespace Tests\Feature;

use App\Models\PageView;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/** Website visitors on the admin dashboard: only real readers are counted. */
class PageViewTrackingTest extends TestCase
{
    use RefreshDatabase;

    private const BROWSER = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36';

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        config(['app.track_visits' => true]);
    }

    private function visit(string $agent = self::BROWSER, string $language = 'en-IN,en;q=0.9'): void
    {
        $this->withServerVariables(['HTTP_USER_AGENT' => $agent, 'HTTP_ACCEPT_LANGUAGE' => $language])->get(route('home'))->assertOk();
    }

    public function test_a_reader_is_counted_but_scanners_and_the_admin_are_not(): void
    {
        $this->visit();
        $this->assertSame(1, PageView::count());

        $this->visit('Mozilla/5.0 (compatible; CensysInspect/1.1; +https://about.censys.io/)');
        $this->visit('WhatsApp/2.23.20.0');
        $this->visit(self::BROWSER, ''); // a script pretending to be Chrome: no Accept-Language
        $this->assertSame(1, PageView::count());

        $this->actingAs(User::forceCreate(['name' => 'Administrator', 'username' => 'admin-pv', 'email' => 'admin-pv@example.com', 'role' => 'admin', 'password' => bcrypt('secret')]));
        $this->visit();
        $this->assertSame(1, PageView::count());
    }

    public function test_a_browser_used_for_the_admin_panel_is_never_counted_again(): void
    {
        $admin = User::forceCreate(['name' => 'Administrator', 'username' => 'admin-own', 'email' => 'admin-own@example.com', 'role' => 'admin', 'password' => bcrypt('secret')]);

        // Opening the admin panel marks the browser for a year
        $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk()->assertCookie('ip_staff', '1');

        // The same browser later, logged out (or logged in as a member): not a reader
        $this->app['auth']->forgetGuards();
        auth()->logout();
        $this->withCookie('ip_staff', '1')->withServerVariables(['HTTP_USER_AGENT' => self::BROWSER, 'HTTP_ACCEPT_LANGUAGE' => 'en-IN,en;q=0.9'])->get(route('home'))->assertOk();
        $this->assertGuest();
        $this->assertSame(0, PageView::count());
    }

    public function test_a_crawler_opening_pages_too_fast_stops_being_counted(): void
    {
        for ($i = 0; $i < 20; $i++) {
            $this->visit();
        }

        $this->assertSame(12, PageView::count());
    }
}
