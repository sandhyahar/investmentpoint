<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AboutPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_about_page_shows_editorial_content(): void
    {
        $response = $this->get('/about');

        $response->assertOk();
        $response->assertSee('About Us');
        $response->assertSee('By Nikhil Bhatt');
        $response->assertSee('Business Editor &amp; Publisher', false);
        $response->assertSee('integrity, education, and awareness', false);
        $response->assertSee('www.nikhilbhatt.in');
        // The market ticker bar is hidden on this page (index names can still appear inside the layout's scripts)
        $response->assertDontSee('<div class="ticker">', false);
    }

    public function test_about_page_lists_all_awards_and_the_recognition_box(): void
    {
        $response = $this->get('/about')->assertOk();

        // All 9 listed simply in the "Recognition" box
        $this->assertCount(9, config('awards'));
        foreach (config('awards') as $award) {
            $response->assertSee(e($award['title']), false);
        }
        $response->assertSee('Jamnagar Ratna Award 2023');
        $response->assertSee('Untold Story Award 2024');
        $response->assertDontSee('ab-award-grid');
    }
}