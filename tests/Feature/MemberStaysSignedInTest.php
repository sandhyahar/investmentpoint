<?php

namespace Tests\Feature;

use App\Models\SubscriptionRequest;
use App\Models\User;
use App\Services\LoginOtp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/**
 * Members stay signed in while they keep visiting (the OTP login's "remember me" cookie) and are
 * signed out after more than a day away.
 */
class MemberStaysSignedInTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-12 08:00:00');
    }

    private function member(array $attributes = []): User
    {
        return User::factory()->create($attributes + ['role' => 'user', 'phone' => '+919825012345', 'remember_token' => 'member-token-1234567890']);
    }

    /** The cookie the OTP login leaves in the browser: id | token | password hash */
    private function rememberCookie(User $user): array
    {
        return [Auth::guard('member')->getRecallerName() => $user->id . '|' . $user->remember_token . '|' . Auth::guard('member')->hashPasswordForCookie($user->password)];
    }

    public function test_a_member_whose_session_ran_out_is_signed_back_in_and_the_cookie_is_renewed(): void
    {
        $member = $this->member(['last_seen_at' => now()->subHours(20)]);

        // A new visit with no session, only the remember cookie, 20 hours after the last one
        $response = $this->withCookies($this->rememberCookie($member))->get(route('member.dashboard'));

        $response->assertOk()->assertCookie(Auth::guard('member')->getRecallerName()); // renewed
        $this->assertAuthenticatedAs($member, 'member');
        $this->assertSame('member-token-1234567890', $member->fresh()->remember_token);
        $this->assertTrue($member->fresh()->last_seen_at->equalTo(now())); // this visit is noted
    }

    public function test_a_member_away_for_more_than_a_day_is_logged_out(): void
    {
        $member = $this->member(['last_seen_at' => now()->subHours(25)]);

        $this->withCookies($this->rememberCookie($member))->get(route('member.dashboard'))
            ->assertRedirect(route('member.login'))
            ->assertSessionHas('success', 'You were logged out because you did not visit for more than 1 day. Please log in again.');
        $this->assertGuest('member');

        // Other pages simply open as a visitor
        $this->app['auth']->forgetGuards();
        $member->forceFill(['last_seen_at' => now()->subDays(3)])->save();
        $this->withCookies($this->rememberCookie($member))->get(route('home'))->assertOk();
        $this->assertGuest('member');
    }

    public function test_logging_in_again_with_an_otp_starts_the_clock_again(): void
    {
        config(['subscription.otp.demo' => true, 'subscription.otp.whatsapp_template' => null]);
        $member = $this->member(['last_seen_at' => now()->subDays(6)]);
        SubscriptionRequest::create(['name' => 'Ravi Patel', 'phone' => '+919825012345', 'plan' => 'Monthly Plan', 'price' => 10000,
            'agreed_at' => now(), 'agreed_to' => SubscriptionRequest::AGREED_TO, 'payment_status' => 'paid', 'payment_mode' => 'manual',
            'paid_at' => now(), 'starts_at' => now()->subDays(10), 'expires_at' => now()->addDays(20), 'user_id' => $member->id]);

        $code = app(LoginOtp::class)->send('+919825012345');
        $this->withSession(['otp_phone' => '+919825012345'])->post(route('member.otp.verify'), ['otp' => $code])
            ->assertRedirect(route('member.dashboard'));

        $this->get(route('member.dashboard'))->assertOk(); // not thrown out again for the old absence
        $this->assertAuthenticatedAs($member, 'member');
    }

    public function test_logging_out_on_one_device_keeps_the_members_other_devices_signed_in(): void
    {
        $member = $this->member();

        $this->actingAs($member, 'member')->post(route('member.logout'))->assertRedirect(route('member.login'));
        $this->assertGuest('member');
        // The token is unchanged, so the remember cookie on the member's other devices still works
        $this->assertSame('member-token-1234567890', $member->fresh()->remember_token);

        $this->app['auth']->forgetGuards();
        $this->withCookies($this->rememberCookie($member))->get(route('member.dashboard'))->assertOk();
        $this->assertAuthenticatedAs($member, 'member');
    }

    public function test_an_admin_is_not_handled_by_this(): void
    {
        $admin = User::forceCreate(['name' => 'Administrator', 'username' => 'admin-keep', 'email' => 'admin-keep@example.com', 'role' => 'admin', 'password' => bcrypt('secret')]);
        $admin->forceFill(['last_seen_at' => now()->subDays(30)])->save();

        // Signed in through the admin login: never logged out here, and no remember cookie is sent
        $this->actingAs($admin)->get(route('home'))->assertOk()->assertCookieMissing(Auth::guard('member')->getRecallerName());
        $this->assertAuthenticatedAs($admin);
    }

    public function test_signing_in_to_the_admin_panel_in_the_same_browser_does_not_sign_the_member_out(): void
    {
        config(['subscription.otp.demo' => true, 'subscription.otp.whatsapp_template' => null]);
        $member = $this->member();
        SubscriptionRequest::create(['name' => 'Ravi Patel', 'phone' => '+919825012345', 'plan' => 'Monthly Plan', 'price' => 10000,
            'agreed_at' => now(), 'agreed_to' => SubscriptionRequest::AGREED_TO, 'payment_status' => 'paid', 'payment_mode' => 'manual',
            'paid_at' => now(), 'starts_at' => now()->subDays(10), 'expires_at' => now()->addDays(20), 'user_id' => $member->id]);
        $admin = User::forceCreate(['name' => 'Administrator', 'username' => 'admin-both', 'email' => 'admin-both@example.com', 'role' => 'admin', 'password' => bcrypt('Admin@12345')]);
        $memberKey = Auth::guard('member')->getName();
        $adminKey = Auth::guard('web')->getName();

        // The member logs in with the OTP...
        $code = app(LoginOtp::class)->send('+919825012345');
        $this->withSession(['otp_phone' => '+919825012345'])->post(route('member.otp.verify'), ['otp' => $code])->assertRedirect(route('member.dashboard'));
        $this->assertAuthenticatedAs($member, 'member');
        $this->assertGuest('web');

        // ...then the admin panel is opened in the same browser: both logins live side by side
        $this->post(route('admin.login.submit'), ['username' => 'admin-both', 'password' => 'Admin@12345', 'device' => 'desktop|1920x1080|mouse'])
            ->assertRedirect(route('admin.dashboard'))->assertSessionHas($memberKey, $member->id)->assertSessionHas($adminKey, $admin->id);
        $this->get(route('admin.dashboard'))->assertOk();
        $this->get(route('home'))->assertOk()->assertSee('href="' . route('member.dashboard') . '"', false)->assertSessionHas($memberKey, $member->id);
        $this->get(route('member.dashboard'))->assertOk()->assertSee(route('member.logout'), false);

        // The member logging out leaves the admin signed in
        $this->post(route('member.logout'))->assertRedirect(route('member.login'))->assertSessionMissing($memberKey)->assertSessionHas($adminKey, $admin->id);
    }

    public function test_a_member_signed_in_before_the_logins_were_separated_stays_signed_in(): void
    {
        $member = $this->member(['last_seen_at' => now()->subHours(2)]);
        $old = Auth::guard('web');

        // The remember cookie of the old shared login: moved to the member login on the next visit
        $this->withCookies([$old->getRecallerName() => $member->id . '|' . $member->remember_token . '|' . $old->hashPasswordForCookie($member->password)])
            ->get(route('home'))->assertOk()->assertSee('href="' . route('member.dashboard') . '"', false)
            ->assertCookie(Auth::guard('member')->getRecallerName());
        $this->assertAuthenticatedAs($member, 'member');
        $this->assertGuest('web');
    }
}
