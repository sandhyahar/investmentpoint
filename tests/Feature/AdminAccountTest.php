<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/** Admin -> My Account: changing the password. */
class AdminAccountTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::forceCreate(['name' => 'Administrator', 'username' => 'admin-acc', 'email' => 'admin-acc@example.com', 'role' => 'admin', 'password' => Hash::make('Old@Password1')]);
    }

    public function test_admin_changes_the_password_and_stays_signed_in(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);

        // Only the new password is asked for (twice), each box with a show / hide button
        $this->get(route('admin.account.edit'))->assertOk()->assertDontSee('current_password', false)
            ->assertSee('data-toggle-password="password"', false)->assertSee('data-toggle-password="password_confirmation"', false);

        $this->put(route('admin.account.password'), ['password' => 'New@Password22', 'password_confirmation' => 'New@Password22'])
            ->assertRedirect(route('admin.account.edit'))->assertSessionHas('success');

        $this->assertTrue(Hash::check('New@Password22', $admin->fresh()->password));
        $this->get(route('admin.account.edit'))->assertOk(); // this browser is still signed in
    }

    public function test_a_weak_or_mistyped_new_password_changes_nothing(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->put(route('admin.account.password'), ['password' => 'New@Password22', 'password_confirmation' => 'New@Password23'])
            ->assertSessionHasErrors('password');
        $this->actingAs($admin)->put(route('admin.account.password'), ['password' => 'simple', 'password_confirmation' => 'simple'])
            ->assertSessionHasErrors('password');

        $this->assertTrue(Hash::check('Old@Password1', $admin->fresh()->password));
    }

    public function test_the_password_cannot_be_changed_without_being_signed_in(): void
    {
        $admin = $this->admin();

        $this->put(route('admin.account.password'), ['password' => 'New@Password22', 'password_confirmation' => 'New@Password22'])
            ->assertRedirect(route('admin.login'));

        $this->assertTrue(Hash::check('Old@Password1', $admin->fresh()->password));
    }

    public function test_a_browser_holding_the_old_password_is_sent_to_the_admin_login(): void
    {
        $this->actingAs($this->admin())->withSession(['password_hash_web' => 'hash-of-an-older-password'])
            ->get(route('admin.account.edit'))->assertRedirect(route('admin.login'));
    }
}
