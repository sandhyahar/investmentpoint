<?php

namespace Tests\Feature;

use App\Models\MemberPick;
use App\Models\News;
use App\Models\SubscriptionRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** php artisan site:clean-test-data: the one-time clean-up before launch. */
class CleanTestDataTest extends TestCase
{
    use RefreshDatabase;

    private function fill(string $paymentMode): void
    {
        User::forceCreate(['name' => 'Administrator', 'username' => 'admin-clean', 'email' => 'admin-clean@example.com', 'role' => 'admin', 'password' => bcrypt('secret')]);
        User::factory()->create(['role' => 'user']);
        News::create(['title' => 'Demo news', 'description' => 'Demo', 'status' => 'active', 'views' => 5]);
        MemberPick::create(['pick_date' => '2026-10-01', 'stock_name' => 'Reliance Industries Ltd', 'support' => '2840']);
        SubscriptionRequest::create(['name' => 'dumy', 'phone' => '+919825012345', 'plan' => 'Monthly Plan', 'price' => 10000,
            'agreed_at' => now(), 'agreed_to' => SubscriptionRequest::AGREED_TO, 'payment_status' => 'paid', 'payment_mode' => $paymentMode]);
        DB::table('page_views')->insert(['route' => 'home', 'path' => '/', 'visitor' => 'abc', 'device' => 'desktop', 'created_at' => now()]);
    }

    public function test_it_only_lists_until_forced_then_deletes_test_data_and_keeps_the_admin(): void
    {
        $this->fill('demo');

        $this->artisan('site:clean-test-data')->expectsOutputToContain('Nothing was deleted')->assertSuccessful();
        $this->assertSame(1, News::count());
        $this->assertSame(1, SubscriptionRequest::count());

        $this->artisan('site:clean-test-data --force')->assertSuccessful();
        $this->assertSame(0, News::count());
        $this->assertSame(0, MemberPick::count());
        $this->assertSame(0, SubscriptionRequest::count());
        $this->assertSame(0, DB::table('page_views')->count());
        $this->assertSame(['admin'], User::pluck('role')->all());
    }

    public function test_it_refuses_once_a_real_payment_exists(): void
    {
        $this->fill('live');

        $this->artisan('site:clean-test-data --force')->expectsOutputToContain('real paid subscription')->assertFailed();
        $this->assertSame(1, News::count());
        $this->assertSame(1, SubscriptionRequest::count());
        $this->assertSame(2, User::count());
    }
}
