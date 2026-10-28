<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Problems found on launch day (12 Oct 2026). */
class LivePageFixesTest extends TestCase
{
    use RefreshDatabase;

    public function test_structured_data_has_a_real_schema_org_context(): void
    {
        $this->get(route('home'))->assertOk()
            ->assertSee('"@context":"https://schema.org"', false)
            ->assertDontSee('__contextArgs', false);
    }

    public function test_subscription_and_login_forms_are_never_swapped_by_live_updates(): void
    {
        $this->get(route('subscription'))->assertOk()->assertSee('<main class="sp-page" data-live="off">', false)
            ->assertSee("window.addEventListener('pageshow', sync)", false);
        $this->get(route('member.login'))->assertOk()->assertSee('<main class="sp-page" data-live="off">', false);
    }
}
