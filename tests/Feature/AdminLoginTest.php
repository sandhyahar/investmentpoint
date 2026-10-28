<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_login_with_documented_credentials(): void
    {
        User::forceCreate([
            'name' => 'Administrator',
            'username' => 'nikhilbhatt',
            'email' => 'nikhilbhatt@investmentpoint.in',
            'role' => 'admin',
            'password' => Hash::make('Admin@12345'),
        ]);

        // The login page reports the device; the admin panel only works on a computer / laptop
        $response = $this->post('/admin/login', [
            'username' => 'nikhilbhatt',
            'password' => 'Admin@12345',
            'device' => 'desktop|1920x1080|mouse',
        ]);

        $response->assertRedirect('/admin/dashboard');
        $this->assertAuthenticated();
        $this->get('/admin/dashboard')->assertOk();
    }

    public function test_phones_cannot_log_in_to_the_admin_panel(): void
    {
        User::forceCreate(['name' => 'Administrator', 'username' => 'nikhilbhatt', 'email' => 'nikhilbhatt@investmentpoint.in',
            'role' => 'admin', 'password' => Hash::make('Admin@12345')]);

        $this->post('/admin/login', ['username' => 'nikhilbhatt', 'password' => 'Admin@12345', 'device' => 'mobile|390x844|touch'])
            ->assertSessionHasErrors('username');
        $this->assertGuest();
    }
}
