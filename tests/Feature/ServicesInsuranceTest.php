<?php

namespace Tests\Feature;

use App\Mail\InsuranceEnquiryMail;
use App\Models\InsuranceEnquiry;
use App\Models\InsuranceProduct;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/** Header: Home, Editor's View, ... Services (Stock Market = Subscription, Insurance = Life Insurance); the page and its enquiries */
class ServicesInsuranceTest extends TestCase
{
    use RefreshDatabase;

    /** A real city and PIN code from India Post's list (public/data/india-pincodes.json) */
    private const PLACE = ['address' => '12, Patel Colony', 'city' => 'Jamnagar', 'state' => 'Gujarat', 'pincode' => '360110'];

    private function admin(): User
    {
        return User::forceCreate(['name' => 'Admin', 'username' => 'admin-ins', 'role' => 'admin', 'password' => Hash::make('x')]);
    }

    public function test_the_header_has_editors_view_after_home_and_services_with_stock_market_and_insurance(): void
    {
        $nav = str($this->get(route('home'))->assertOk()->getContent())->between('<nav>', '</nav>');
        $at = fn (string $text) => strpos((string) $nav, $text);

        // Home, Editor's View, E-Paper, Media, Services; no Expert's View
        $this->assertStringNotContainsString(route('experts-view.index'), (string) $nav);
        $this->assertTrue($at('>Home<') < $at("Editor's View") && $at("Editor's View") < $at('E-Paper') && $at('E-Paper') < $at('Media') && $at('Media') < $at('Services'));
        // Services: Stock Market opens the Subscription page, Insurance the Life Insurance page; no "Life Insurance" item
        $services = str((string) $nav)->after('Services');
        $this->assertStringContainsString('href="' . route('subscription') . '"><i class="bi bi-graph-up-arrow"></i> Stock Market', (string) $services);
        $this->assertStringContainsString('href="' . route('services.insurance', 'life') . '"><i class="bi bi-shield-check"></i> Insurance', (string) $services);
        $this->assertStringNotContainsString('Life Insurance', (string) $services);
        $this->assertStringNotContainsString('General Insurance', (string) $services);
        $this->get(route('services.insurance', 'general'))->assertNotFound();
        $this->get('/services/stock-market')->assertRedirect('/subscription'); // the old address
        // Expert's View is still in the footer
        $this->get(route('home'))->assertSee(route('experts-view.index'), false);
    }

    public function test_the_insurance_page_has_the_four_plans_and_join_as_advisor_without_kotak(): void
    {
        $this->get(route('services.insurance', 'life'))->assertOk()
            ->assertSeeInOrder(['Products', 'Join As Advisor'])
            ->assertSeeInOrder(['1. Term Plan', '2. Child Saving Plan', '3. Wealth Creation Plan', '4. Retirement Plans'])
            ->assertDontSee('Kotak')->assertDontSee('As Adviser')
            ->assertSee('Age *')->assertSee('City *')->assertSee('PIN code *')->assertSee('Select PIN code')
            ->assertDontSee('Fills in by itself')->assertSee('<input type="hidden" name="state" data-state>', false) // no State box: it comes with the city
            ->assertSee('india-pincodes.json', false) // the city / PIN code list the page's script loads
            ->assertSee('Insurance is the subject matter of solicitation');
        $this->assertSame(['Term Plan', 'Child Saving Plan', 'Wealth Creation Plan', 'Retirement Plans'], InsuranceProduct::for('life')->pluck('name')->all());
    }

    public function test_a_reader_ticks_plans_and_the_admin_sees_the_enquiry(): void
    {
        [$term, $wealth] = [InsuranceProduct::where('name', 'Term Plan')->value('id'), InsuranceProduct::where('name', 'Wealth Creation Plan')->value('id')];

        // Plans, a 10-digit mobile, an age of 18-80 and the city's own PIN code are needed
        $this->post(route('services.insurance.enquire', 'life'), ['kind' => 'product', 'name' => 'Ravi', 'contact' => '12345', 'age' => '15', 'pincode' => '400001'] + self::PLACE)
            ->assertSessionHasErrorsIn('product', ['products', 'contact', 'age', 'pincode']);
        $this->assertSame(0, InsuranceEnquiry::count());

        $this->post(route('services.insurance.enquire', 'life'), ['kind' => 'product', 'name' => 'Ravi Patel', 'contact' => '+91 98765 43210', 'age' => '42',
            'products' => [$wealth, $term]] + self::PLACE)
            ->assertRedirect(route('services.insurance', 'life') . '#product')->assertSessionHas('enquiry_sent', 'product');
        $enquiry = InsuranceEnquiry::sole();
        $this->assertSame(['product', '9876543210', 42, ['Term Plan', 'Wealth Creation Plan'], '12, Patel Colony', 'Jamnagar', 'Gujarat', '360110'],
            [$enquiry->kind, $enquiry->contact, $enquiry->age, $enquiry->products, $enquiry->address, $enquiry->city, $enquiry->state, $enquiry->pincode]);

        // Admin: a "new" badge in the sidebar, the details in the list, then no longer new
        $this->actingAs($this->admin());
        $this->get(route('admin.dashboard'))->assertSee('Insurance Enquiries');
        $this->get(route('admin.insurance-enquiries.index'))->assertOk()
            ->assertSee('Ravi Patel')->assertSee('42 yrs')->assertSee('9876543210')->assertSee('Term Plan, Wealth Creation Plan')
            ->assertSee('12, Patel Colony')->assertSee('Jamnagar')->assertSee('Gujarat - 360110')->assertSee('New');
        $this->assertNotNull($enquiry->fresh()->read_at);
        $this->delete(route('admin.insurance-enquiries.destroy', $enquiry))->assertRedirect();
        $this->assertSame(0, InsuranceEnquiry::count());
    }

    public function test_someone_joins_as_advisor_without_ticking_plans(): void
    {
        $this->post(route('services.insurance.enquire', 'life'), ['kind' => 'adviser', 'name' => 'Meera Shah', 'contact' => '9123456789', 'age' => '29',
            'address' => 'Kalawad Road', 'city' => 'Rajkot', 'state' => 'Gujarat', 'pincode' => '360001'])
            ->assertRedirect(route('services.insurance', 'life') . '#adviser');
        $this->assertSame(['adviser', null, 29, 'Rajkot'], [InsuranceEnquiry::sole()->kind, InsuranceEnquiry::sole()->products, InsuranceEnquiry::sole()->age, InsuranceEnquiry::sole()->city]);

        // A bot filling the hidden field is ignored
        $this->post(route('services.insurance.enquire', 'life'), ['kind' => 'adviser', 'name' => 'Bot', 'contact' => '9123456789', 'age' => '30', 'website' => 'spam.example'] + self::PLACE)
            ->assertSessionHas('enquiry_sent');
        $this->assertSame(1, InsuranceEnquiry::count());

        $this->actingAs($this->admin())->get(route('admin.insurance-enquiries.index', ['kind' => 'adviser']))->assertSee('Meera Shah')->assertSee('Join As Advisor (1)');
    }

    public function test_each_enquiry_is_emailed_with_its_details_to_the_address_set_by_the_admin(): void
    {
        Mail::fake();
        $term = InsuranceProduct::where('name', 'Term Plan')->value('id');

        // "Get a call back": to insurance@example.com to start with, with every detail
        $this->post(route('services.insurance.enquire', 'life'), ['kind' => 'product', 'name' => 'Ravi Patel', 'contact' => '98765 43210', 'age' => '42', 'products' => [$term]] + self::PLACE)
            ->assertSessionHas('enquiry_sent', 'product');
        Mail::assertSent(InsuranceEnquiryMail::class, function (InsuranceEnquiryMail $mail) {
            $html = $mail->render();

            return $mail->hasTo('insurance@example.com') && $mail->envelope()->subject === 'New insurance enquiry (Get a call back): Ravi Patel, 9876543210'
                && collect(['Products: Get a call back', 'Life Insurance', 'Ravi Patel', '9876543210', '42 years', 'Term Plan', '12, Patel Colony', 'Jamnagar', 'Gujarat - 360110'])
                    ->every(fn ($text) => str_contains($html, $text));
        });

        // The admin changes the address: "Join now" goes there; a bot, or an empty address, sends nothing
        $this->actingAs($this->admin());
        $this->get(route('admin.insurance-products.index'))->assertSee('Email each enquiry to')->assertSee('insurance@example.com');
        $this->put(route('admin.insurance-products.settings'), ['notify_email' => 'not-an-email'])->assertSessionHasErrors('notify_email');
        $this->put(route('admin.insurance-products.settings'), ['notify_email' => 'agent@example.com'])->assertSessionHas('success');
        $this->post(route('services.insurance.enquire', 'life'), ['kind' => 'adviser', 'name' => 'Meera Shah', 'contact' => '9123456789', 'age' => '29'] + self::PLACE);
        Mail::assertSent(InsuranceEnquiryMail::class, fn ($mail) => $mail->hasTo('agent@example.com') && str_contains($mail->render(), 'Join As Advisor'));
        $this->post(route('services.insurance.enquire', 'life'), ['kind' => 'adviser', 'name' => 'Bot', 'contact' => '9123456789', 'age' => '30', 'website' => 'x'] + self::PLACE);
        $this->put(route('admin.insurance-products.settings'), ['notify_email' => ''])->assertSessionHas('success');
        $this->post(route('services.insurance.enquire', 'life'), ['kind' => 'adviser', 'name' => 'Asha', 'contact' => '9123456780', 'age' => '35'] + self::PLACE);
        Mail::assertSentCount(2);
        $this->assertSame(3, InsuranceEnquiry::count());
    }

    public function test_a_mail_problem_never_stops_the_form(): void
    {
        Mail::shouldReceive('to')->andThrow(new \RuntimeException('No such host'));

        $this->post(route('services.insurance.enquire', 'life'), ['kind' => 'adviser', 'name' => 'Meera Shah', 'contact' => '9123456789', 'age' => '29'] + self::PLACE)
            ->assertRedirect(route('services.insurance', 'life') . '#adviser')->assertSessionHas('enquiry_sent', 'adviser');
        $this->assertSame(1, InsuranceEnquiry::count());
    }

    public function test_the_admin_manages_the_plans_and_can_show_general_insurance(): void
    {
        $this->actingAs($this->admin());
        $this->get(route('admin.insurance-products.index'))->assertOk()->assertSee('Term Plan')->assertSee('hidden')->assertDontSee('Insurance company in the captions');

        $this->post(route('admin.insurance-products.store'), ['insurance' => 'life', 'name' => 'Health Rider', 'description' => 'Extra cover for illness'])->assertSessionHas('success');
        $rider = InsuranceProduct::where('name', 'Health Rider')->firstOrFail();
        $this->assertSame(5, $rider->sort_order); // after the 4 plans
        // Hidden plans are not on the website and cannot be sent
        $this->put(route('admin.insurance-products.update', $rider), ['name' => 'Health Rider', 'sort_order' => 5])->assertSessionHas('success');
        $this->assertFalse($rider->fresh()->is_active);
        $this->get(route('services.insurance', 'life'))->assertDontSee('Health Rider');
        $this->post(route('services.insurance.enquire', 'life'), ['kind' => 'product', 'name' => 'A', 'contact' => '9876543210', 'age' => '40', 'products' => [$rider->id]] + self::PLACE)
            ->assertSessionHasErrorsIn('product', 'products.0');

        // General Insurance shown: in the Services menu and on its own page
        $this->put(route('admin.insurance-products.settings'), ['general_enabled' => '1'])->assertSessionHas('success');
        $this->assertSame(['life', 'general'], InsuranceProduct::shown());
        $this->get(route('home'))->assertSee(route('services.insurance', 'general'), false);
        $this->get(route('services.insurance', 'general'))->assertOk()->assertSee('General Insurance');

        $this->delete(route('admin.insurance-products.destroy', $rider))->assertSessionHas('success');
        $this->assertNull($rider->fresh());
        Setting::set('insurance_general_enabled', '');
    }
}
