<?php

namespace Tests\Feature;

use App\Models\PremiumContent;
use App\Models\SubscriptionRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SubscriptionPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_subscription_page_shows_the_two_plans(): void
    {
        $response = $this->get('/subscription');

        $response->assertOk()
            ->assertSee('Choose your plan')
            ->assertSee('Daily Digital News Letter')->assertSee('₹8,000')->assertSee('/ month', false)
            ->assertSee('Weekly Gujarati News Paper')->assertSee('₹10,000')->assertSee('/ year', false)
            ->assertSee('name="plan" value="newsletter"', false)->assertSee('name="plan" value="newspaper"', false)
            ->assertSee('Postal address')->assertSee('name="state"', false)->assertSee('name="city"', false)->assertSee('name="pincode"', false)
            ->assertSee('data/india-pincodes.json', false)->assertSee('WhatsApp number')
            ->assertSee('Educational &amp; Journalism Purposes Only', false)->assertSee('No Buy/Sell Recommendations')
            ->assertSee('name="agree"', false)
            ->assertSee('Nikhil Bhatt')->assertSee('mailto:shreeinvestmentpoint@gmail.com', false)->assertSee('tel:+919939080808', false)
            ->assertSee('Shree Investment Point (owner of this page) and Razorpay')
            ->assertDontSee('Monthly Plan</small>', false)->assertDontSee('Free Trial');

        // ?plan=newspaper opens with the paper chosen
        $this->get('/subscription?plan=newspaper')->assertOk()->assertSee('value="newspaper" class="sp-plan-radio" checked', false);
    }

    public function test_the_admin_sets_both_plan_prices(): void
    {
        $admin = User::forceCreate(['name' => 'Administrator', 'username' => 'admin-price', 'email' => 'admin-price@example.com', 'role' => 'admin', 'password' => bcrypt('secret')]);
        $this->actingAs($admin)->get(route('admin.settings.edit'))->assertOk()
            ->assertSee('Daily Digital News Letter')->assertSee('Weekly Gujarati News Paper')->assertSee('name="price_newspaper"', false);

        $this->actingAs($admin)->post(route('admin.settings.subscription-price'), ['price_newsletter' => '7500', 'price_newspaper' => '9000'])->assertRedirect();
        $this->assertSame('7500', \App\Models\Setting::get('price_newsletter'));
        $this->assertSame('9000', \App\Models\Setting::get('price_newspaper'));
        $this->actingAs($admin)->post(route('admin.settings.subscription-price'), ['price_newsletter' => 'abc', 'price_newspaper' => '9000'])->assertSessionHasErrors('price_newsletter');

        // The page and new subscriptions use them (applied when the app starts)
        SubscriptionRequest::applySavedPrices();
        $this->get('/subscription')->assertOk()->assertSee('₹7,500')->assertSee('₹9,000');
        $this->post(route('subscription.store'), ['plan' => 'newspaper', 'name' => 'Paper Reader', 'phone' => '9825012345',
            'address' => '12 Shanti Nagar, Ring Road', 'state' => 'Gujarat', 'city' => 'Surat', 'pincode' => '395002', 'agree' => '1']);
        $this->assertSame(9000, SubscriptionRequest::sole()->price);
    }

    public function test_the_paper_plan_needs_a_postal_address_and_has_no_member_login(): void
    {
        Carbon::setTestNow('2026-10-15 10:00:00');
        config(['services.razorpay.key' => null, 'services.razorpay.secret' => null, 'subscription.demo_payments' => true, 'subscription.whatsapp.token' => null]);

        $this->post(route('subscription.store'), ['plan' => 'newspaper', 'name' => 'Paper Reader', 'phone' => '9825012345', 'agree' => '1'])
            ->assertSessionHasErrors(['address', 'state', 'city', 'pincode']);
        // The PIN code must be one of the chosen city's (India Post list)
        $this->post(route('subscription.store'), ['plan' => 'newspaper', 'name' => 'Paper Reader', 'phone' => '9825012345',
            'address' => '12 Shanti Nagar', 'state' => 'Gujarat', 'city' => 'Surat', 'pincode' => '110001', 'agree' => '1'])
            ->assertSessionHasErrors('pincode')->assertSessionDoesntHaveErrors(['state', 'city']);
        $this->post(route('subscription.store'), ['plan' => 'newspaper', 'name' => 'Paper Reader', 'phone' => '9825012345',
            'address' => '12 Shanti Nagar', 'state' => 'Narnia', 'city' => 'Surat', 'pincode' => '395002', 'agree' => '1'])
            ->assertSessionHasErrors(['state', 'pincode']);
        $this->assertSame(0, SubscriptionRequest::count());
        $this->post(route('subscription.store'), ['name' => 'No Plan', 'phone' => '9825012345', 'agree' => '1'])->assertSessionHasErrors('plan');

        $this->post(route('subscription.store'), ['plan' => 'newspaper', 'name' => 'Paper Reader', 'phone' => '98250 12345',
            'whatsapp_same' => '0', 'whatsapp' => '9999911111', 'address' => "12 Shanti Nagar,\nRing Road,", 'state' => 'Gujarat', 'city' => 'Surat', 'pincode' => '395002', 'agree' => '1']);
        $s = SubscriptionRequest::sole();
        $this->assertSame(['newspaper', 'Weekly Gujarati News Paper', 10000, 12], [$s->plan_key, $s->plan, $s->price, $s->months]);
        $this->assertSame("12 Shanti Nagar,\nRing Road, Surat, Gujarat - 395002", $s->address);
        $this->assertSame('+919825012345', $s->whatsapp_number); // the paper plan doesn't ask for a WhatsApp number

        $this->get(route('subscription.pay', $s))->assertOk()->assertSee('/ year', false)->assertSee('1 year')->assertSee('Ring Road, Surat');

        // Paid: one year, the receipt (no member login), and no member access
        $this->post(route('subscription.demo', $s))->assertRedirect(route('subscription.pay', $s));
        $s->refresh();
        $this->assertSame('2027-10-15', $s->expires_at->toDateString());
        $this->assertFalse($s->givesMemberAccess());
        $this->assertFalse(SubscriptionRequest::whatsappHasAccess('9825012345'));
        $this->get(route('subscription.pay', $s))->assertOk()->assertSee('posted every week to')->assertDontSee('Login with OTP');
    }

    public function test_the_news_letter_plan_asks_for_whatsapp_and_opens_the_member_page(): void
    {
        Carbon::setTestNow('2026-10-15 10:00:00');
        config(['services.razorpay.key' => null, 'services.razorpay.secret' => null, 'subscription.demo_payments' => true, 'subscription.whatsapp.token' => null]);

        $this->post(route('subscription.store'), ['plan' => 'newsletter', 'name' => 'Daily Reader', 'phone' => '9825012345',
            'whatsapp_same' => '1', 'address' => 'ignored for this plan', 'agree' => '1']);
        $s = SubscriptionRequest::sole();
        $this->assertSame(['newsletter', 'Daily Digital News Letter', 8000, 1, null], [$s->plan_key, $s->plan, $s->price, $s->months, $s->address]);

        $this->post(route('subscription.demo', $s))->assertRedirect(route('member.login'));
        $this->assertSame('2026-11-15', $s->fresh()->expires_at->toDateString());
        $this->assertTrue(SubscriptionRequest::whatsappHasAccess('9825012345'));
    }

    public function test_subscription_page_uses_static_plan_price(): void
    {
        PremiumContent::query()->delete();

        PremiumContent::create([
            'badge' => 'INVESTMENT POINT PREMIUM',
            'title' => 'Premium Market Access',
            'description' => 'Admin-managed premium access',
            'price' => 250.00,
            'billing_period' => 'MONTH',
            'benefits' => ['Daily briefing', 'Market signals'],
            'button_text' => 'Subscription',
            'button_url' => '/subscription',
            'status' => 'active',
        ]);

        $response = $this->get('/subscription');

        // Price is fixed on the page, not taken from the admin Premium Content entries
        $response->assertOk();
        $response->assertSee('₹10,000');
        $response->assertDontSee('₹250');
    }

    public function test_subscribing_needs_the_agreement_and_is_recorded_for_admin(): void
    {
        // Without ticking "I agree": refused, nothing saved
        $this->post(route('subscription.store'), ['plan' => 'newsletter', 'name' => 'Ravi Patel', 'phone' => '98250 12345'])
            ->assertSessionHasErrors('agree');
        $this->assertSame(0, SubscriptionRequest::count());

        // Invalid mobile number
        $this->post(route('subscription.store'), ['plan' => 'newsletter', 'name' => 'Ravi Patel', 'phone' => '12345', 'agree' => '1'])
            ->assertSessionHasErrors('phone');

        // Agreed: saved with the time, IP and what was agreed to, then on to the payment page
        $response = $this->post(route('subscription.store'), ['plan' => 'newsletter', 'name' => 'Ravi Patel', 'phone' => '98250 12345', 'whatsapp_same' => '1', 'email' => 'ravi@example.com', 'agree' => '1']);

        $s = SubscriptionRequest::firstOrFail();
        $response->assertRedirect(route('subscription.pay', $s));
        $this->assertSame('+919825012345', $s->phone);
        $this->assertNotNull($s->agreed_at);
        $this->assertSame(SubscriptionRequest::AGREED_TO, $s->agreed_to);
        $this->assertSame('127.0.0.1', $s->ip);
        $this->assertSame('new', $s->status);
        $this->assertSame('pending', $s->payment_status);
        $this->assertSame(40, strlen($s->token));

        // Bots filling the hidden field are ignored
        $this->post(route('subscription.store'), ['name' => 'Bot', 'phone' => '9825012345', 'agree' => '1', 'website' => 'spam'])
            ->assertSessionHas('subscribed');
        $this->assertSame(1, SubscriptionRequest::count());

        // Admin sees the record and can mark it contacted
        $admin = User::forceCreate(['name' => 'Administrator', 'username' => 'admin-sub', 'email' => 'admin-sub@example.com', 'role' => 'admin', 'password' => bcrypt('secret')]);
        $this->actingAs($admin)->get(route('admin.subscriptions.index'))->assertOk()
            ->assertSee('Ravi Patel')->assertSee('+919825012345')->assertSee('Agreed');
        $this->actingAs($admin)->put(route('admin.subscriptions.update', $s), ['status' => 'contacted'])->assertRedirect();
        $this->assertSame('contacted', $s->fresh()->status);
    }

    private function pendingSubscription(array $attributes = []): SubscriptionRequest
    {
        return SubscriptionRequest::create($attributes + [
            'name' => 'Ravi Patel', 'phone' => '+919825012345', 'plan' => 'Monthly Plan', 'price' => 10000,
            'agreed_at' => now(), 'agreed_to' => SubscriptionRequest::AGREED_TO,
        ]);
    }

    public function test_demo_payment_starts_the_subscription_period_and_sends_whatsapp(): void
    {
        Carbon::setTestNow('2026-09-30 10:00:00');
        config(['services.razorpay.key' => null, 'services.razorpay.secret' => null, 'subscription.demo_payments' => true,
            'subscription.whatsapp.provider' => 'meta',
            'subscription.whatsapp.token' => 'wa-token', 'subscription.whatsapp.phone_number_id' => '12345', 'subscription.whatsapp.template' => null]);
        Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.1']]])]);
        $s = $this->pendingSubscription();

        $this->get(route('subscription.pay', $s))->assertOk()
            ->assertSee('Complete Your Payment')->assertSee('Demo mode')->assertSee('Complete demo payment')
            ->assertDontSee('checkout.razorpay.com');

        $this->post(route('subscription.demo', $s))->assertRedirect(route('member.login'));

        $s->refresh();
        $this->assertTrue($s->isPaid());
        $this->assertSame('demo', $s->payment_mode);
        $this->assertStringStartsWith('demo_', $s->razorpay_payment_id);
        $this->assertSame('2026-09-30', $s->starts_at->toDateString());
        $this->assertSame('2026-10-30', $s->expires_at->toDateString());

        // Receipt shows the period
        $this->get(route('subscription.pay', $s))->assertOk()
            ->assertSee('Subscription Confirmed')->assertSee('30 Sep 2026')->assertSee('30 Oct 2026');

        // WhatsApp confirmation to the reader's number with the dates
        $this->assertSame('sent', $s->whatsapp_status);
        Http::assertSent(fn ($r) => str_contains($r->url(), '/12345/messages')
            && $r['to'] === '919825012345'
            && str_contains($r['text']['body'], '30 Sep 2026 to 30 Oct 2026'));

        // Paying twice changes nothing
        $this->post(route('subscription.demo', $s))->assertRedirect();
        $this->assertSame('2026-10-30', $s->fresh()->expires_at->toDateString());
    }

    private function useSecondtick(): void
    {
        config(['subscription.whatsapp.provider' => 'secondtick',
            'subscription.whatsapp.secondtick' => ['base_url' => 'https://app.secondtick.com/api', 'vendor_uid' => 'vendor-1', 'token' => 'st-token', 'from_phone_number_id' => null],
            'subscription.whatsapp.template' => 'subscription_active', 'subscription.whatsapp.template_language' => 'en_US',
            'subscription.otp.whatsapp_template' => 'login_otp', 'subscription.otp.whatsapp_template_language' => 'en']);
    }

    public function test_subscription_message_is_sent_through_secondtick(): void
    {
        Carbon::setTestNow('2026-09-30 10:00:00');
        config(['services.razorpay.key' => null, 'services.razorpay.secret' => null, 'subscription.demo_payments' => true]);
        $this->useSecondtick();
        Http::fake(['app.secondtick.com/*' => Http::response(['result' => 'success'])]);
        $s = $this->pendingSubscription();

        $this->post(route('subscription.demo', $s))->assertRedirect();

        $this->assertSame('sent', $s->fresh()->whatsapp_status);
        Http::assertSent(fn ($r) => $r->url() === 'https://app.secondtick.com/api/vendor-1/contact/send-template-message'
            && $r->hasHeader('Authorization', 'Bearer st-token')
            && $r['phone_number'] === '919825012345'
            && $r['template_name'] === 'subscription_active' && $r['template_language'] === 'en_US'
            && $r['field_1'] === 'Ravi Patel' && $r['field_3'] === '30 Sep 2026' && $r['field_4'] === '30 Oct 2026');
    }

    public function test_secondtick_failure_is_saved_on_the_subscription(): void
    {
        $this->useSecondtick();
        Http::fake(['app.secondtick.com/*' => Http::response(['result' => 'failed', 'message' => 'Template not found'])]);
        $s = $this->pendingSubscription(['whatsapp' => '+919825012345', 'starts_at' => now(), 'expires_at' => now()->addMonth()]);

        $this->assertFalse(app(\App\Services\WhatsApp::class)->sendSubscriptionConfirmation($s));
        $this->assertSame('failed', $s->fresh()->whatsapp_status);
        $this->assertSame('Template not found', $s->fresh()->whatsapp_error);
    }

    public function test_login_otp_is_sent_through_secondtick(): void
    {
        $this->useSecondtick();
        Http::fake(['app.secondtick.com/*' => Http::response(['result' => 'success'])]);

        app(\App\Services\WhatsApp::class)->sendOtp('+919825012345', '123456');

        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/vendor-1/contact/send-template-message')
            && $r['phone_number'] === '919825012345' && $r['template_name'] === 'login_otp'
            && $r['field_1'] === '123456' && $r['button_0'] === '123456' && $r['copy_code'] === '123456');
    }

    public function test_early_renewal_starts_when_the_current_subscription_ends(): void
    {
        Carbon::setTestNow('2026-09-30 10:00:00');
        config(['services.razorpay.key' => null, 'subscription.demo_payments' => true, 'subscription.whatsapp.token' => null]);

        $this->pendingSubscription(['payment_status' => 'paid', 'payment_mode' => 'demo', 'starts_at' => '2026-09-20', 'expires_at' => '2026-10-20']);
        $renewal = $this->pendingSubscription();
        $this->post(route('subscription.demo', $renewal));

        $renewal->refresh();
        $this->assertSame('2026-10-20', $renewal->starts_at->toDateString());
        $this->assertSame('2026-11-20', $renewal->expires_at->toDateString());
        $this->assertSame('not_configured', $renewal->whatsapp_status);
    }

    public function test_razorpay_payment_is_activated_only_with_a_valid_signature(): void
    {
        config(['services.razorpay.key' => 'rzp_test_abc', 'services.razorpay.secret' => 'shh', 'subscription.whatsapp.token' => null]);
        Http::fake(['api.razorpay.com/v1/orders' => Http::response(['id' => 'order_TEST123', 'amount' => 1000000])]);
        $s = $this->pendingSubscription();

        // Order made for ₹10,000 (in paise), checkout shown; demo button is not available
        $this->get(route('subscription.pay', $s))->assertOk()
            ->assertSee('checkout.razorpay.com')->assertSee('order_TEST123')->assertSee('Razorpay test mode');
        Http::assertSent(fn ($r) => $r['amount'] === 1000000 && $r['currency'] === 'INR');
        $this->assertSame('order_TEST123', $s->fresh()->razorpay_order_id);
        $this->post(route('subscription.demo', $s))->assertNotFound();

        // Forged signature: refused
        $this->post(route('subscription.verify', $s), ['razorpay_payment_id' => 'pay_1', 'razorpay_order_id' => 'order_TEST123', 'razorpay_signature' => 'forged'])
            ->assertSessionHas('payment_error');
        $this->assertFalse($s->fresh()->isPaid());

        // Real signature: paid, test mode
        $this->post(route('subscription.verify', $s), [
            'razorpay_payment_id' => 'pay_1', 'razorpay_order_id' => 'order_TEST123',
            'razorpay_signature' => hash_hmac('sha256', 'order_TEST123|pay_1', 'shh'),
        ])->assertRedirect(route('member.login'));

        $s->refresh();
        $this->assertTrue($s->isPaid());
        $this->assertSame('test', $s->payment_mode);
        $this->assertSame('pay_1', $s->razorpay_payment_id);
        $this->assertNotNull($s->expires_at);
    }

    public function test_admin_sees_the_payment_period_and_whatsapp_link(): void
    {
        config(['subscription.whatsapp.token' => null, 'services.razorpay.key' => null, 'services.razorpay.secret' => null, 'subscription.demo_payments' => true]);
        $s = $this->pendingSubscription(['payment_status' => 'paid', 'payment_mode' => 'demo', 'razorpay_payment_id' => 'demo_x',
            'paid_at' => now(), 'starts_at' => now(), 'expires_at' => now()->addMonth()]);
        $this->pendingSubscription(['name' => 'Unpaid Person', 'phone' => '+919825000000']);

        $admin = User::forceCreate(['name' => 'Administrator', 'username' => 'admin-pay', 'email' => 'admin-pay@example.com', 'role' => 'admin', 'password' => bcrypt('secret')]);
        $this->actingAs($admin)->get(route('admin.subscriptions.index'))->assertOk()
            ->assertSee('Subscription period')->assertSee(SubscriptionRequest::day($s->expires_at))
            ->assertSee('Active')->assertSee('Awaiting payment')->assertSee('Send on WhatsApp')
            ->assertSee('Payments: DEMO');

        $this->actingAs($admin)->get(route('admin.subscriptions.index', ['payment' => 'active']))->assertOk()
            ->assertSee('Ravi Patel')->assertDontSee('Unpaid Person');
    }
}
