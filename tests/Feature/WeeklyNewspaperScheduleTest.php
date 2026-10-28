<?php

namespace Tests\Feature;

use App\Models\DigitalNewspaper;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Add Weekly News Paper Edition: a publish date and time; readers see the edition automatically from then on */
class WeeklyNewspaperScheduleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        \Illuminate\Support\Facades\Http::preventStrayRequests();
        Carbon::setTestNow(Carbon::parse('2026-10-22 12:00', 'Asia/Kolkata'));
        $this->actingAs(User::forceCreate(['name' => 'Admin', 'username' => 'admin-np', 'role' => 'admin', 'password' => Hash::make('x')]));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function add(array $extra = []): DigitalNewspaper
    {
        $this->post(route('admin.digital-newspaper.store', ['edition' => 'weekly']), $extra + [
            'title' => 'INVESTMENT POINT WEEKLY - 24.10.2026 TO 30.10.2026', 'week_from' => '2026-10-24', 'week_to' => '2026-10-30', 'status' => 'active',
            'page_files' => [UploadedFile::fake()->image('page-01.jpg', 300, 420)],
        ])->assertRedirect()->assertSessionHasNoErrors();

        return DigitalNewspaper::latest('id')->firstOrFail();
    }

    public function test_the_weekly_form_has_the_schedule_after_the_week_instead_of_the_edition_date(): void
    {
        $html = $this->get(route('admin.digital-newspaper.create', ['edition' => 'weekly']))->assertOk()
            ->assertSee('Schedule Date')->assertSee('Schedule Time')->assertSee('type="time"', false)->assertSee('Clear schedule')
            ->assertDontSee('Edition Date')->assertDontSee('name="published_on"', false)->getContent();
        $this->assertTrue(strpos($html, 'name="week_to"') < strpos($html, 'name="publish_date"')); // under Week From / Week To

        // The daily e-papers keep their Edition Date, with the schedule under it
        $html = $this->get(route('admin.digital-newspaper.create', ['edition' => 'gujarati']))->assertOk()
            ->assertSee('Edition Date')->assertSee('name="publish_time"', false)->getContent();
        $this->assertTrue(strpos($html, 'name="published_on"') < strpos($html, 'name="publish_date"'));
    }

    public function test_a_date_without_a_time_is_midnight_and_a_time_needs_a_date(): void
    {
        $paper = $this->add(['publish_date' => '2026-10-25']);
        $this->assertSame('2026-10-25 00:00', $paper->publish_at->copy()->timezone('Asia/Kolkata')->format('Y-m-d H:i'));

        $count = DigitalNewspaper::count();
        $this->post(route('admin.digital-newspaper.store', ['edition' => 'weekly']), ['title' => 'X', 'status' => 'active', 'publish_time' => '09:00',
            'page_files' => [UploadedFile::fake()->image('p.jpg', 300, 420)]])->assertSessionHasErrors('publish_date');
        $this->assertSame($count, DigitalNewspaper::count());
    }

    public function test_a_special_without_a_week_is_dated_by_its_schedule(): void
    {
        $paper = $this->add(['title' => 'INVESTMENT POINT DIWALI SPECIAL', 'week_from' => '', 'week_to' => '', 'publish_date' => '2026-11-08', 'publish_time' => '06:00']);
        $this->assertSame('2026-11-08', $paper->published_on->toDateString());
    }

    public function test_a_scheduled_edition_is_hidden_until_its_time_then_shows_by_itself(): void
    {
        $paper = $this->add(['publish_date' => '2026-10-24', 'publish_time' => '09:30']); // India time

        // Saved in UTC: 09:30 IST = 04:00 UTC
        $this->assertSame('2026-10-24 04:00:00', $paper->publish_at->utc()->format('Y-m-d H:i:s'));
        $this->assertTrue($paper->isScheduled());

        // Readers: not yet (list, page, sitemap)
        $this->get(route('digital-newspaper.index'))->assertOk()->assertDontSee('24.10.2026 TO 30.10.2026');
        $this->get($paper->url)->assertNotFound();
        $this->assertSame(0, DigitalNewspaper::active()->count());

        // Admin list: Scheduled with the time
        $this->get(route('admin.digital-newspaper.index', ['edition' => 'weekly']))->assertOk()
            ->assertSee('Scheduled')->assertSee('24 Oct 2026, 09:30 AM');

        // One minute before: still hidden; at the time: shown
        Carbon::setTestNow(Carbon::parse('2026-10-24 09:29', 'Asia/Kolkata'));
        $this->assertSame(0, DigitalNewspaper::active()->count());
        Carbon::setTestNow(Carbon::parse('2026-10-24 09:30', 'Asia/Kolkata'));
        $this->get(route('digital-newspaper.index'))->assertOk()->assertSee('24.10.2026 TO 30.10.2026');
        $this->get($paper->fresh()->url)->assertOk();
    }

    public function test_without_a_schedule_it_shows_at_once_and_a_schedule_can_be_cleared(): void
    {
        $now = $this->add();
        $this->assertNull($now->publish_at);
        $this->get($now->url)->assertOk();

        $later = $this->add(['title' => 'INVESTMENT POINT WEEKLY - 31.10.2026 TO 06.11.2026', 'week_from' => '2026-10-31', 'week_to' => '2026-11-06', 'publish_date' => '2026-10-31', 'publish_time' => '10:00']);
        $this->get(route('admin.digital-newspaper.edit', $later))->assertOk()->assertSee('value="2026-10-31"', false)->assertSee('value="10:00"', false)->assertSee('Scheduled: goes live 31 Oct 2026, 10:00 AM');

        // Clearing the schedule publishes it now
        $this->put(route('admin.digital-newspaper.update', $later), ['title' => $later->title, 'week_from' => '2026-10-31', 'week_to' => '2026-11-06', 'status' => 'active', 'publish_date' => '', 'publish_time' => ''])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertNull($later->fresh()->publish_at);
        $this->get($later->fresh()->url)->assertOk();
    }

    public function test_an_inactive_edition_stays_hidden_even_after_its_time(): void
    {
        $paper = $this->add(['status' => 'inactive', 'publish_date' => '2026-10-22', 'publish_time' => '11:00']); // time already passed
        $this->assertFalse($paper->isScheduled());
        $this->get($paper->url)->assertNotFound();
    }
}
