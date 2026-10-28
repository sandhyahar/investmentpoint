<?php

namespace Tests\Feature;

use App\Models\SubscriptionRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Protections for admin, members and subscriptions (see App\Http\Middleware\SecurityHeaders / PrivatePage). */
class SecurityTest extends TestCase
{
    use RefreshDatabase;

    private function subscription(array $attributes = []): SubscriptionRequest
    {
        return SubscriptionRequest::create($attributes + [
            'name' => 'Test Reader', 'phone' => '+919825012345', 'plan' => 'Monthly Plan', 'price' => 10000,
            'agreed_at' => now(), 'agreed_to' => SubscriptionRequest::AGREED_TO,
        ]);
    }

    public function test_every_page_sends_browser_security_headers(): void
    {
        $response = $this->get('/');

        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertStringContainsString("object-src 'none'", $response->headers->get('Content-Security-Policy'));
        $this->assertStringContainsString("form-action 'self'", $response->headers->get('Content-Security-Policy'));
        $this->assertStringContainsString('https://api.razorpay.com', $response->headers->get('Permissions-Policy'));
    }

    public function test_member_and_payment_pages_are_never_cached(): void
    {
        $subscription = $this->subscription();

        foreach ([route('member.login'), route('subscription.pay', $subscription)] as $url) {
            $response = $this->get($url)->assertOk();
            $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'), $url);
            $response->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        }
    }

    public function test_a_payment_is_only_confirmed_once(): void
    {
        $subscription = $this->subscription();

        $this->assertTrue($subscription->markPaid('manual', 'pay_1'));
        $expires = $subscription->expires_at;
        $this->assertFalse($subscription->fresh()->markPaid('manual', 'pay_2'));

        $subscription->refresh();
        $this->assertSame('pay_1', $subscription->razorpay_payment_id);
        $this->assertTrue($expires->equalTo($subscription->expires_at));
    }

    public function test_trying_many_unknown_numbers_is_blocked(): void
    {
        config(['subscription.otp.demo' => true]);

        for ($i = 0; $i < 10; $i++) {
            $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class)
                ->post(route('member.otp.send'), ['phone' => '98250' . str_pad((string) $i, 5, '0', STR_PAD_LEFT)])
                ->assertSessionHasErrors('phone');
        }

        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class)
            ->post(route('member.otp.send'), ['phone' => '9825099999'])
            ->assertSessionHasErrors(['phone' => 'Too many tries. Please try again in an hour, or contact us.']);
    }

    public function test_members_cannot_open_the_admin_panel(): void
    {
        $member = User::factory()->create(['role' => 'user']);

        $this->actingAs($member, 'member')->get(route('admin.dashboard'))->assertRedirect(route('admin.login')); // the member login is not the admin login
        $this->actingAs($member)->get(route('admin.subscriptions.index'))->assertRedirect(route('admin.login')); // nor does the old shared login open it
    }

    public function test_there_is_no_public_api(): void
    {
        $this->getJson('/api/user')->assertNotFound();
    }
}
