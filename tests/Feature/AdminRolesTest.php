<?php

namespace Tests\Feature;

use App\Models\AdminRole;
use App\Models\LatestNews;
use App\Models\User;
use App\Support\AdminPages;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/** Role & Permission Management + User & Role Management (role-based admin access) */
class AdminRolesTest extends TestCase
{
    use RefreshDatabase;

    private const DESKTOP = 'desktop|1920x1080|mouse';

    private function superAdmin(): User
    {
        return User::forceCreate(['name' => 'Nikhil Bhatt', 'username' => 'nikhillbhatt', 'email' => 'boss@example.com', 'role' => 'admin', 'password' => Hash::make('Admin@12345')]);
    }

    private function role(array $permissions, array $extra = []): AdminRole
    {
        $role = AdminRole::create(['name' => 'News Editor', 'is_active' => true] + $extra);
        $role->syncPermissions($permissions);

        return $role;
    }

    private function staff(AdminRole $role, array $extra = []): User
    {
        return User::forceCreate(['name' => 'Rahul Patel', 'username' => 'rahulpatel', 'role' => 'staff', 'admin_role_id' => $role->id, 'password' => Hash::make('Editor@2026x')] + $extra);
    }

    private function latestNews(): LatestNews
    {
        return LatestNews::create(['title' => 'Sensex rises 500 points as banks gain', 'status' => 'active', 'description' => 'Banks led the gains.']);
    }

    // 1. The Super Admin opens every admin page
    public function test_the_super_admin_opens_every_admin_page(): void
    {
        $this->actingAs($this->superAdmin());
        foreach (AdminPages::PAGES as [, , $route]) {
            $this->get(route($route))->assertOk();
        }
        $this->get(route('admin.team.users.index'))->assertOk()->assertSee('User &amp; Role Management', false);
        $this->get(route('admin.team.roles.index'))->assertOk();
    }

    // 2 + 3. The Super Admin creates a role and ticks permissions; only real permissions are saved
    public function test_the_super_admin_creates_a_role_with_ticked_permissions(): void
    {
        $this->actingAs($this->superAdmin());
        $this->get(route('admin.team.roles.create'))->assertOk()
            ->assertSee('Select all')->assertSee('Deselect all')->assertSee('Save Permissions')->assertSee('value="latest-news.delete"', false);

        // A made-up permission (or an action a page does not have) is refused
        $this->post(route('admin.team.roles.store'), ['name' => 'News Editor', 'permissions' => ['latest-news.create', 'team.users.create']])
            ->assertSessionHasErrors('permissions.1');
        $this->post(route('admin.team.roles.store'), ['name' => 'News Editor', 'permissions' => ['settings.create']])
            ->assertSessionHasErrors('permissions.0');

        $this->post(route('admin.team.roles.store'), ['name' => 'News Editor', 'description' => 'Writes the news', 'is_active' => '1', 'permissions' => ['latest-news.create', 'news.edit']])
            ->assertRedirect(route('admin.team.roles.index'))->assertSessionHas('success');

        $role = AdminRole::firstOrFail();
        $this->assertSame('Writes the news', $role->description);
        $this->assertTrue($role->is_active);
        // Create / Edit bring View with them (the list page)
        $this->assertSame(['news.view', 'news.edit', 'latest-news.view', 'latest-news.create'], $role->permissionKeys());

        // Duplicate names are refused; editing shows the role with its ticks
        $this->post(route('admin.team.roles.store'), ['name' => 'News Editor', 'permissions' => ['news.view']])->assertSessionHasErrors('name');
        $this->get(route('admin.team.roles.edit', $role))->assertOk()->assertSee('Writes the news');
        $this->assertMatchesRegularExpression('/value="news\.edit"[^>]*checked/', $this->get(route('admin.team.roles.edit', $role))->getContent());
        $this->assertDoesNotMatchRegularExpression('/value="news\.delete"[^>]*checked/', $this->get(route('admin.team.roles.edit', $role))->getContent());
    }

    // 4. The Super Admin creates a user with a role
    public function test_the_super_admin_creates_a_user_with_a_role(): void
    {
        $role = $this->role(['news.view']);
        $this->actingAs($this->superAdmin());
        // Eye buttons show / hide both password boxes
        $this->get(route('admin.team.users.create'))->assertOk()
            ->assertSee('data-toggle-password="password"', false)->assertSee('data-toggle-password="password_confirmation"', false);

        $this->post(route('admin.team.users.store'), [
            'name' => 'Rahul Patel', 'username' => 'rahulpatel', 'email' => 'Rahul@Example.com', 'phone' => '+91 98250 12345',
            'access' => (string) $role->id, 'is_active' => '1', 'must_change_password' => '1',
            'password' => 'Temp@Pass2026', 'password_confirmation' => 'Temp@Pass2026',
        ])->assertRedirect(route('admin.team.users.index'))->assertSessionHas('success');

        $user = User::where('username', 'rahulpatel')->firstOrFail();
        $this->assertSame('staff', $user->role);
        $this->assertTrue($user->adminRole->is($role));
        $this->assertSame('rahul@example.com', $user->email);
        $this->assertTrue($user->is_active);
        $this->assertFalse($user->must_change_password); // keeps the given password (owner, 23 Oct 2026)
        $this->assertNotSame('Temp@Pass2026', $user->password);
        $this->assertTrue(Hash::check('Temp@Pass2026', $user->password));

        // Username / email must be unique; the role must be a real role id (not a name, not made up)
        $this->post(route('admin.team.users.store'), ['name' => 'X', 'username' => 'rahulpatel', 'email' => 'rahul@example.com', 'access' => 'News Editor', 'is_active' => '1', 'password' => 'Temp@Pass2026', 'password_confirmation' => 'Temp@Pass2026'])
            ->assertSessionHasErrors(['username', 'email', 'access']);
        $this->post(route('admin.team.users.store'), ['name' => 'X', 'username' => 'x-user', 'access' => '999', 'password' => 'Temp@Pass2026', 'password_confirmation' => 'Temp@Pass2026'])
            ->assertSessionHasErrors('access');
    }

    // 5 + 6. The new user logs in and sees only their pages
    public function test_a_role_user_logs_in_and_sees_only_the_allowed_pages(): void
    {
        $this->staff($this->role(['news.view', 'news.create', 'latest-news.view']));

        // No dashboard in the role: the login lands on the Welcome page (hello, role, username, their pages),
        // not straight in one of their pages (owner, 23 Oct 2026)
        $this->post('/admin/login', ['username' => 'rahulpatel', 'password' => 'Editor@2026x', 'device' => self::DESKTOP])
            ->assertRedirect(route('admin.welcome'));
        $this->get(route('admin.dashboard'))->assertRedirect(route('admin.welcome'));
        $this->get(route('admin.welcome'))->assertOk()
            ->assertSee('Hello, Rahul Patel!')->assertSee('👋')->assertSee('News Editor')->assertSee('rahulpatel')
            ->assertSee('href="' . route('admin.news.index') . '"', false)->assertSee('href="' . route('admin.latest-news.index') . '"', false)
            ->assertDontSee('href="' . route('admin.settings.edit') . '"', false);

        $this->get(route('admin.news.index'))->assertOk()
            ->assertSee('Latest News')
            ->assertDontSee('>Subscriptions', false)->assertDontSee('Site Settings')->assertDontSee('User &amp; Role Management', false)
            // the page script that hides buttons gets the addresses this role may not use
            ->assertSee('new RegExp(r.p)', false);
        $this->get(route('admin.news.create'))->assertOk();
        $this->get(route('admin.account.edit'))->assertOk(); // own password, always
    }

    public function test_the_dashboard_shows_only_the_allowed_widgets(): void
    {
        $this->actingAs($this->staff($this->role(['dashboard.view', 'news.view', 'news.create'])));

        $this->get(route('admin.dashboard'))->assertOk()
            ->assertSee(route('admin.news.create'))->assertDontSee(route('admin.member-picks.create'))->assertDontSee('New subscriptions')->assertDontSee('Analysis posts');
    }

    // 7. Direct addresses of other pages are refused
    public function test_unauthorized_pages_are_refused_by_direct_address(): void
    {
        $this->actingAs($this->staff($this->role(['news.view'])));

        $this->get(route('admin.subscriptions.index'))->assertForbidden();
        $this->get(route('admin.settings.edit'))->assertForbidden();
        $this->get(route('admin.team.users.index'))->assertForbidden();
        $this->get(route('admin.team.roles.index'))->assertForbidden();
        $this->get(route('admin.media.index'))->assertForbidden();
        $this->get(route('admin.news.create'))->assertForbidden(); // View only
        $this->postJson(route('admin.ai-image'), ['prompt' => 'x'])->assertForbidden(); // helper buttons need Create or Edit
    }

    // 8. Create / Edit / Delete requests without that permission are refused (and change nothing)
    public function test_unauthorized_create_edit_and_delete_requests_are_refused(): void
    {
        $item = $this->latestNews();
        $this->actingAs($this->staff($this->role(['latest-news.view', 'latest-news.edit'])));

        $this->get(route('admin.latest-news.edit', $item))->assertOk();
        $this->post(route('admin.latest-news.store'), ['title' => 'New one'])->assertForbidden();
        $this->delete(route('admin.latest-news.destroy', $item))->assertForbidden();
        $this->post(route('admin.latest-news.bulk-delete'), ['ids' => [$item->id]])->assertForbidden();
        $this->assertModelExists($item);
        $this->getJson(route('admin.media.index'))->assertOk(); // Media Library picker inside the edit form

        // Someone with only View cannot edit either
        $this->flushSession();
        $viewerRole = AdminRole::create(['name' => 'Viewer', 'is_active' => true]);
        $viewerRole->syncPermissions(['latest-news.view']);
        $viewer = User::forceCreate(['name' => 'Viewer', 'username' => 'viewer', 'role' => 'staff', 'admin_role_id' => $viewerRole->id, 'password' => Hash::make('x')]);
        $this->actingAs($viewer)->put(route('admin.latest-news.update', $item), ['title' => 'Changed'])->assertForbidden();
        $this->assertSame('Sensex rises 500 points as banks gain', $item->fresh()->title);
    }

    // 9. Only the Super Admin changes a team user's password (owner, 23 Oct 2026; was: the user changed their own)
    public function test_the_super_admin_changes_a_team_users_password(): void
    {
        $user = $this->staff($this->role(['news.view']));
        $this->actingAs($this->superAdmin());

        $this->put(route('admin.team.users.update', $user), ['name' => $user->name, 'username' => 'rahulpatel', 'access' => (string) $user->admin_role_id, 'is_active' => '1',
            'password' => 'Given@New2026', 'password_confirmation' => 'Given@New2026'])->assertRedirect(route('admin.team.users.index'));
        $this->assertTrue(Hash::check('Given@New2026', $user->fresh()->password));
        $this->get(route('admin.team.users.create'))->assertSee('cannot change it; only you can');
    }

    // Team users keep the password the Super Admin gave them: no forced change, and they cannot change it (owner, 23 Oct 2026)
    public function test_a_team_user_keeps_the_given_password_and_only_the_super_admin_changes_it(): void
    {
        $user = $this->staff($this->role(['news.view']), ['must_change_password' => true]); // an older flag is ignored

        $this->post('/admin/login', ['username' => 'rahulpatel', 'password' => 'Editor@2026x', 'device' => self::DESKTOP]);
        $this->get(route('admin.news.index'))->assertOk(); // straight to their pages
        $this->get(route('admin.account.edit'))->assertOk()->assertSee('Your password is set by the Super Admin')
            ->assertDontSee('Change password')->assertDontSee('name="password"', false);

        $this->put(route('admin.account.password'), ['password' => 'Mine@New2026', 'password_confirmation' => 'Mine@New2026'])->assertForbidden();
        $this->assertTrue(Hash::check('Editor@2026x', $user->fresh()->password));

        // The Super Admin still changes their own password in My Account
        $this->flushSession();
        $this->actingAs($this->superAdmin());
        $this->get(route('admin.account.edit'))->assertOk()->assertSee('Change password');
        $this->put(route('admin.account.password'), ['password' => 'Boss@New2026', 'password_confirmation' => 'Boss@New2026'])->assertRedirect(route('admin.account.edit'));
    }

    // 10. Users cannot change their own role or permissions
    public function test_a_role_user_cannot_change_roles_or_permissions(): void
    {
        $role = $this->role(['news.view']);
        $user = $this->staff($role);
        $this->actingAs($user);

        $this->put(route('admin.team.users.update', $user), ['name' => 'R', 'username' => 'rahulpatel', 'access' => 'admin', 'is_active' => '1'])->assertForbidden();
        $this->put(route('admin.team.roles.update', $role), ['name' => 'News Editor', 'permissions' => AdminPages::allPermissions()])->assertForbidden();
        $this->post(route('admin.team.roles.store'), ['name' => 'Mine', 'permissions' => ['settings.edit']])->assertForbidden();
        // Extra fields on the password form do nothing
        $this->put(route('admin.account.password'), ['password' => 'Mine@New2026', 'password_confirmation' => 'Mine@New2026', 'role' => 'admin', 'admin_role_id' => 99]);

        $user->refresh();
        $this->assertSame('staff', $user->role);
        $this->assertSame($role->id, $user->admin_role_id);
        $this->assertSame(['news.view'], $role->fresh()->permissionKeys());
    }

    // 11. Public signup can never make an administrator
    public function test_public_signup_cannot_create_an_administrator(): void
    {
        // "role" is not mass-assignable: a form sending it is ignored
        $user = User::create(['name' => 'Hacker', 'username' => 'hacker', 'password' => Hash::make('x'), 'role' => 'admin', 'admin_role_id' => 1, 'is_active' => true]);
        $this->assertSame('user', $user->fresh()->role);
        $this->assertNull($user->fresh()->admin_role_id);

        // A member login cannot sign in to the admin panel
        $member = User::forceCreate(['name' => 'Member', 'username' => 'm9825012345', 'role' => 'user', 'phone' => '+919825012345', 'password' => Hash::make('anything')]);
        $this->assertFalse($member->canUseAdminPanel());
        $this->post('/admin/login', ['username' => $member->username, 'password' => 'anything', 'device' => self::DESKTOP])->assertSessionHasErrors('username');
    }

    // 12. Inactive accounts and inactive roles cannot sign in, and are signed out at once
    public function test_inactive_accounts_and_roles_are_refused(): void
    {
        $role = $this->role(['news.view']);
        $user = $this->staff($role, ['is_active' => false]);

        $this->post('/admin/login', ['username' => 'rahulpatel', 'password' => 'Editor@2026x', 'device' => self::DESKTOP])
            ->assertSessionHasErrors('username');
        $this->assertGuest();

        // Active again, but the role is switched off
        $user->forceFill(['is_active' => true])->save();
        $role->update(['is_active' => false]);
        $this->post('/admin/login', ['username' => 'rahulpatel', 'password' => 'Editor@2026x', 'device' => self::DESKTOP])
            ->assertSessionHasErrors('username');
        $this->assertGuest();

        // Signed in, then switched off by the Super Admin: thrown out on the next click
        $role->update(['is_active' => true]);
        $this->actingAs($user->fresh())->get(route('admin.news.index'))->assertOk();
        $user->forceFill(['is_active' => false])->save();
        $this->actingAs($user->fresh())->get(route('admin.news.index'))->assertRedirect(route('admin.login'));
        $this->assertGuest();
    }

    // 14. Changing a role's ticks works at once, without any database work by hand
    public function test_changing_permissions_works_at_once(): void
    {
        $role = $this->role(['news.view']);
        $user = $this->staff($role);
        $this->actingAs($user)->get(route('admin.members.index'))->assertForbidden();

        $this->flushSession(); // another person, another browser
        $this->actingAs($this->superAdmin())->put(route('admin.team.roles.update', $role), ['name' => 'News Editor', 'is_active' => '1', 'permissions' => ['members.view']])
            ->assertSessionHasNoErrors()->assertRedirect(route('admin.team.roles.index'));

        $this->flushSession();
        $this->actingAs($user->fresh())->get(route('admin.members.index'))->assertOk();
        $this->get(route('admin.news.index'))->assertForbidden();
    }

    // Safety: the signed-in Super Admin and the last active Super Admin are protected; roles in use stay
    public function test_the_super_admin_cannot_be_removed_by_mistake(): void
    {
        $admin = $this->superAdmin();
        $role = $this->role(['news.view']);
        $this->actingAs($admin);

        $this->delete(route('admin.team.users.destroy', $admin))->assertSessionHas('error');
        $this->patch(route('admin.team.users.status', $admin))->assertSessionHas('error');
        $this->put(route('admin.team.users.update', $admin), ['name' => 'Nikhil', 'username' => 'nikhillbhatt', 'access' => (string) $role->id, 'is_active' => '1'])
            ->assertSessionHasErrors('access');
        $this->assertTrue($admin->fresh()->isAdmin());
        $this->assertTrue($admin->fresh()->is_active);

        $this->staff($role);
        $this->delete(route('admin.team.roles.destroy', $role))->assertSessionHas('error');
        $this->assertModelExists($role);
    }

    public function test_the_super_admin_activates_deactivates_and_resets_a_password(): void
    {
        $admin = $this->superAdmin();
        $user = $this->staff($this->role(['news.view']));
        $member = User::forceCreate(['name' => 'Member', 'username' => 'm9000000001', 'role' => 'user', 'password' => Hash::make('x')]);
        $this->actingAs($admin);

        $this->get(route('admin.team.users.index', ['q' => 'rahul']))->assertSee('rahulpatel')->assertDontSee('m9000000001');
        $this->get(route('admin.team.users.edit', $member))->assertNotFound();

        $this->patch(route('admin.team.users.status', $user))->assertSessionHas('success');
        $this->assertFalse($user->fresh()->is_active);
        $this->get(route('admin.team.users.index', ['status' => 'inactive']))->assertSee('rahulpatel');

        // Reset the password (the user simply uses the new one) and make them Super Admin
        $this->put(route('admin.team.users.update', $user), ['name' => 'Rahul P', 'username' => 'rahulpatel', 'access' => 'admin', 'is_active' => '1',
            'password' => 'Reset@Pass2026', 'password_confirmation' => 'Reset@Pass2026'])->assertRedirect(route('admin.team.users.index'));
        $user->refresh();
        $this->assertTrue($user->isAdmin());
        $this->assertTrue($user->is_active);
        $this->assertFalse($user->must_change_password);
        $this->assertTrue(Hash::check('Reset@Pass2026', $user->password));
    }

    public function test_a_forgotten_password_is_reset_with_the_artisan_command(): void
    {
        $admin = $this->superAdmin();
        \Illuminate\Support\Facades\RateLimiter::hit('admin-login|nikhillbhatt|127.0.0.1', 900);

        $this->artisan('admin:password', ['username' => 'nikhillbhatt'])
            ->expectsQuestion('New password (at least 10 characters, upper- and lower-case, a number and a symbol)', 'Fresh@Pass2026')
            ->expectsQuestion('Repeat the new password', 'Fresh@Pass2026')
            ->assertSuccessful();

        $this->assertTrue(Hash::check('Fresh@Pass2026', $admin->fresh()->password));
        $this->assertSame(0, \Illuminate\Support\Facades\RateLimiter::attempts('admin-login|nikhillbhatt|127.0.0.1'));
        $this->artisan('admin:password', ['username' => 'nobody'])->assertFailed();
    }
}
