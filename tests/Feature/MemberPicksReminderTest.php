<?php

namespace Tests\Feature;

use App\Models\MemberPick;
use App\Models\SubscriptionRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Admin -> Member Picks: "Send a WhatsApp reminder to subscribers" on the Add / Edit forms. */
class MemberPicksReminderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-12 04:00:00'); // 9:30 AM in India
        Cache::flush();
        config(['subscription.whatsapp.provider' => 'secondtick',
            'subscription.whatsapp.secondtick' => ['base_url' => 'https://app.secondtick.com/api', 'vendor_uid' => 'vendor-1', 'token' => 'st-token', 'from_phone_number_id' => null],
            'subscription.picks' => ['whatsapp_template' => 'member_stocks_updated', 'whatsapp_template_language' => 'en']]);
        Http::fake(['app.secondtick.com/*' => Http::response(['result' => 'success'])]);

        $this->actingAs(User::forceCreate(['name' => 'Administrator', 'username' => 'admin-picks', 'email' => 'admin-picks@example.com', 'role' => 'admin', 'password' => bcrypt('secret')]));
    }

    private function subscriber(string $name, string $phone, array $attributes = []): SubscriptionRequest
    {
        return SubscriptionRequest::create($attributes + [
            'name' => $name, 'phone' => $phone, 'plan' => 'Monthly Plan', 'price' => 10000,
            'agreed_at' => now(), 'agreed_to' => SubscriptionRequest::AGREED_TO,
            'payment_status' => 'paid', 'payment_mode' => 'manual', 'paid_at' => now(),
            'starts_at' => now()->subDays(3), 'expires_at' => now()->addDays(27),
        ]);
    }

    private function rows(): array
    {
        return [['stock_name' => 'Reliance Industries Ltd', 'cmp' => '2880', 'support' => '2840', 'resistance' => '2940', 'achieved' => ' 2935 ']];
    }

    public function test_adding_stocks_reminds_every_running_subscriber_once(): void
    {
        $this->subscriber('Ravi Patel', '+919825012345');
        $this->subscriber('Ravi Patel', '+919825012345', ['starts_at' => now()->addDays(27), 'expires_at' => now()->addDays(57)]); // renewed early
        $this->subscriber('Meena Shah', '+919898000001', ['whatsapp' => '+919898000002']);
        $this->subscriber('Expired Member', '+919898000003', ['starts_at' => now()->subDays(40), 'expires_at' => now()->subDays(10)]);
        $this->subscriber('Cancelled Member', '+919898000004', ['status' => 'cancelled']);
        $this->subscriber('Unpaid Reader', '+919898000005', ['payment_status' => 'pending', 'payment_mode' => null]);

        $this->get(route('admin.member-picks.create'))->assertOk()->assertSee('Send a WhatsApp reminder to subscribers')
            ->assertSee('name="notify" value="1" checked', false);

        $this->post(route('admin.member-picks.store'), ['pick_date' => '2026-10-12', 'notify' => '1',
            'rows' => [...$this->rows(), ['stock_name' => ' HDFC  Bank Ltd ', 'support' => '1610'], ['stock_name' => '']]])
            ->assertRedirect()->assertSessionHas('success', '2 stock(s) added for 12 Oct 2026. WhatsApp reminder sent to 2 subscriber(s).');

        Http::assertSentCount(2);
        Http::assertSent(fn ($r) => $r->url() === 'https://app.secondtick.com/api/vendor-1/contact/send-template-message'
            && $r['phone_number'] === '919825012345' && $r['template_name'] === 'member_stocks_updated'
            && $r['field_1'] === 'Ravi Patel' && $r['field_2'] === '12 Oct 2026' && $r['field_3'] === 'Reliance Industries Ltd, HDFC Bank Ltd');
        Http::assertSent(fn ($r) => $r['phone_number'] === '919898000002' && $r['field_1'] === 'Meena Shah');

        // Sent for this date: the box is no longer ticked by default
        $this->get(route('admin.member-picks.edit', MemberPick::first()))->assertOk()
            ->assertSee('Already sent for this date at 9:30 AM')->assertDontSee('name="notify" value="1" checked', false);
    }

    public function test_nothing_is_sent_when_the_box_is_unticked_or_the_date_is_in_the_future(): void
    {
        $this->subscriber('Ravi Patel', '+919825012345');

        $this->post(route('admin.member-picks.store'), ['pick_date' => '2026-10-12', 'rows' => $this->rows()])
            ->assertSessionHas('success', '1 stock(s) added for 12 Oct 2026.');
        $this->post(route('admin.member-picks.store'), ['pick_date' => '2026-10-13', 'rows' => $this->rows(), 'notify' => '1'])
            ->assertSessionHas('success', '1 stock(s) added for 13 Oct 2026. WhatsApp reminder not sent: members only see these stocks from 13 Oct 2026.');

        Http::assertNothingSent();
    }

    public function test_editing_a_stock_reminds_subscribers_and_reports_failures(): void
    {
        $this->subscriber('Ravi Patel', '+919825012345');
        $pick = MemberPick::create(['pick_date' => '2026-10-12', 'stock_name' => 'HDFC Bank Ltd', 'cmp' => '1640', 'support' => '1610', 'resistance' => '1670']);
        $update = ['pick_date' => '2026-10-12', 'stock_name' => 'HDFC Bank Ltd', 'support' => '1600', 'notify' => '1'];

        $this->put(route('admin.member-picks.update', $pick), $update)
            ->assertSessionHas('success', 'Stock updated successfully. WhatsApp reminder sent to 1 subscriber(s).');
        Http::assertSent(fn ($r) => $r['field_2'] === '12 Oct 2026' && $r['field_3'] === 'HDFC Bank Ltd');
        $this->assertSame('1600', $pick->fresh()->support);

        Http::swap(new \Illuminate\Http\Client\Factory);
        Http::fake(['app.secondtick.com/*' => Http::response(['result' => 'failed', 'message' => 'Template not found'])]);
        $this->put(route('admin.member-picks.update', $pick), $update)
            ->assertSessionHas('success', 'Stock updated successfully.')
            ->assertSessionHas('error', 'WhatsApp reminder failed for 1 subscriber(s): Template not found');
    }

    public function test_updating_the_achieved_price_sends_the_stock_name_without_the_price(): void
    {
        $this->subscriber('Ravi Patel', '+919825012345');
        $pick = MemberPick::create(['pick_date' => '2026-10-12', 'stock_name' => 'Glenmark Pharma', 'cmp' => '2300', 'support' => '2323', 'resistance' => '2288-2260']);

        // The edit form ticks the reminder box by itself when the Achieved box is filled in or changed
        $this->get(route('admin.member-picks.edit', $pick))->assertOk()
            ->assertSee("achieved.addEventListener('input'", false)->assertSee('and its Achieved price');

        $this->put(route('admin.member-picks.update', $pick), ['pick_date' => '2026-10-12', 'stock_name' => 'Glenmark Pharma', 'cmp' => '2300',
            'support' => '2323', 'resistance' => '2288-2260', 'achieved' => '2265', 'notify' => '1'])
            ->assertSessionHas('success', 'Stock updated successfully. WhatsApp reminder sent to 1 subscriber(s).');

        Http::assertSent(fn ($r) => $r['field_1'] === 'Ravi Patel' && $r['field_2'] === '12 Oct 2026' && $r['field_3'] === 'Glenmark Pharma'
            && !str_contains(json_encode($r->data()), '2265') && !str_contains(json_encode($r->data()), '2,265'));
    }

    public function test_the_achieved_template_is_used_when_set_and_the_general_one_when_whatsapp_refuses_it(): void
    {
        config(['subscription.picks.achieved_template' => 'update_achive_stock', 'subscription.picks.achieved_template_language' => 'en_US']);
        $this->subscriber('Ravi Patel', '+919825012345');
        $pick = MemberPick::create(['pick_date' => '2026-10-12', 'stock_name' => 'Glenmark Pharma', 'cmp' => '2300', 'support' => '2323']);
        $update = ['pick_date' => '2026-10-12', 'stock_name' => 'Glenmark Pharma', 'cmp' => '2300', 'support' => '2323', 'achieved' => '2265', 'notify' => '1'];

        $this->put(route('admin.member-picks.update', $pick), $update)
            ->assertSessionHas('success', 'Stock updated successfully. WhatsApp reminder sent to 1 subscriber(s).')->assertSessionMissing('error');
        Http::assertSentCount(1);
        Http::assertSent(fn ($r) => $r['template_name'] === 'update_achive_stock' && $r['field_1'] === '12 Oct 2026, 9:30 AM'
            && $r['field_2'] === 'Ravi Patel' && $r['field_3'] === '₹2,265' && $r['field_4'] === 'Glenmark Pharma' && !isset($r['field_5']));

        // A stock without an achieved price still uses the general template
        $other = MemberPick::create(['pick_date' => '2026-10-12', 'stock_name' => 'Nifty Future', 'cmp' => '22707']);
        $this->put(route('admin.member-picks.update', $other), ['pick_date' => '2026-10-12', 'stock_name' => 'Nifty Future', 'cmp' => '22710', 'notify' => '1']);
        Http::assertSent(fn ($r) => $r['template_name'] === 'member_stocks_updated' && $r['field_3'] === 'Nifty Future');

        // Not approved yet: the general template goes out instead, and the admin is told
        Http::swap(new \Illuminate\Http\Client\Factory);
        Http::fake(['app.secondtick.com/*' => fn ($request) => $request['template_name'] === 'update_achive_stock'
            ? Http::response(['result' => 'failed', 'message' => 'Template not found'])
            : Http::response(['result' => 'success'])]);
        $this->put(route('admin.member-picks.update', $pick), $update)
            ->assertSessionHas('success', 'Stock updated successfully. WhatsApp reminder sent to 1 subscriber(s).')
            ->assertSessionHas('error', 'The "update_achive_stock" template was not accepted by WhatsApp (Template not found), so the general reminder template was used.');
        Http::assertSent(fn ($r) => $r['template_name'] === 'member_stocks_updated' && $r['field_3'] === 'Glenmark Pharma');
    }

    public function test_achieved_replaces_symbol_on_the_forms_the_list_and_the_member_card(): void
    {
        $this->get(route('admin.member-picks.create'))->assertOk()->assertSee('Achieved')->assertDontSee('Symbol')->assertSee('rows[0][achieved]', false);

        $this->post(route('admin.member-picks.store'), ['pick_date' => '2026-10-12', 'rows' => $this->rows()])->assertSessionHasNoErrors();
        $pick = MemberPick::first();
        $this->assertSame('2935', $pick->achieved);

        $this->get(route('admin.member-picks.edit', $pick))->assertOk()->assertSee('name="achieved" value="2935"', false)->assertDontSee('Symbol');
        $this->put(route('admin.member-picks.update', $pick), ['pick_date' => '2026-10-12', 'stock_name' => 'Reliance Industries Ltd', 'cmp' => '2880', 'achieved' => '2950'])
            ->assertSessionHasNoErrors();
        $this->assertSame('2950', $pick->fresh()->achieved);

        $this->get(route('admin.member-picks.index'))->assertOk()->assertSee('Achieved')->assertSee('₹2,950');
        // The admin can always open the member page: the achieved level sits where the symbol used to be
        $this->get(route('member.dashboard'))->assertOk()->assertSee('class="pk-ach"', false)->assertSee('₹2,950')->assertDontSee('pk-sym"', false);
    }

    public function test_a_description_opens_from_the_stock_box_with_the_update_time(): void
    {
        $this->get(route('admin.member-picks.create'))->assertOk()->assertSee('rows[0][description]', false)->assertSee('data-desc-toggle="0"', false);

        // Two stocks: one with a description (rich text from the editor), one with the editor left empty
        $this->post(route('admin.member-picks.store'), ['pick_date' => '2026-10-12', 'rows' => [
            ['stock_name' => 'Glenmark Pharma', 'cmp' => '2300', 'description' => '<h2>Why this level</h2><p>Strong <b>volume</b> near support.</p><script>alert(1)</script>'],
            ['stock_name' => 'Nifty Future', 'cmp' => '22707', 'description' => '<p><br></p>'],
        ]])->assertSessionHasNoErrors();

        $described = MemberPick::where('stock_name', 'Glenmark Pharma')->first();
        $plain = MemberPick::where('stock_name', 'Nifty Future')->first();
        $this->assertTrue($described->hasDescription());
        $this->assertNull($plain->description);

        $this->get(route('admin.member-picks.edit', $described))->assertOk()->assertSee('name="description"', false)->assertSee('Why this level');
        $this->put(route('admin.member-picks.update', $plain), ['pick_date' => '2026-10-12', 'stock_name' => 'Nifty Future', 'cmp' => '22707', 'description' => '<p>Range bound today.</p>'])
            ->assertSessionHasNoErrors();
        $this->assertSame('<p>Range bound today.</p>', $plain->fresh()->description);
        $plain->fresh()->update(['description' => null]);

        // Member page (the admin can always open it): time on every box, the dialog only for the described stock
        $page = $this->get(route('member.dashboard'))->assertOk()
            ->assertSee('Updated')->assertSee('12 Oct 2026, 9:30 AM')
            ->assertSee('id="pk-dialog"', false)->assertSee('Read full description')
            ->assertSee('<h2>Why this level</h2>', false)->assertSee('Strong <b>volume</b> near support.', false)
            ->assertDontSee('alert(1)', false);
        $this->assertSame(1, substr_count($page->getContent(), 'data-pick-open tabindex'));
    }

    public function test_without_the_template_the_stock_is_saved_and_the_admin_is_told(): void
    {
        config(['subscription.picks.whatsapp_template' => null]);
        $this->subscriber('Ravi Patel', '+919825012345');

        $this->get(route('admin.member-picks.create'))->assertOk()->assertSee('WHATSAPP_PICKS_TEMPLATE');
        $this->post(route('admin.member-picks.store'), ['pick_date' => '2026-10-12', 'rows' => $this->rows(), 'notify' => '1'])
            ->assertSessionHas('success', '1 stock(s) added for 12 Oct 2026.')
            ->assertSessionHas('error', 'WhatsApp reminder not sent: WHATSAPP_PICKS_TEMPLATE (with the WhatsApp API keys) is not set in .env.');

        $this->assertSame(1, MemberPick::count());
        Http::assertNothingSent();
    }
}
