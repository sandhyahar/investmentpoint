<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminRole;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * User & Role Management (Super Admin only): admin-panel logins with a name, username, first password,
 * Active switch and access ("Super Admin" = everything, or one role = only its permissions). A new or
 * reset password can be marked "change at the next login". Members (subscribers) are not here.
 */
class AdminUserController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->input('q'));
        $status = $request->input('status');
        $users = User::whereIn('role', ['admin', 'staff'])->with('adminRole')
            ->when($search !== '', fn ($q) => $q->where(fn ($q) => $q->where('username', 'like', "%{$search}%")
                ->orWhere('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%")->orWhere('phone', 'like', "%{$search}%")))
            ->when(in_array($status, ['active', 'inactive'], true), fn ($q) => $q->where('is_active', $status === 'active'))
            ->orderByRaw("role = 'admin' desc")->orderBy('name')->paginate(20)->withQueryString();

        return view('admin.team.users', compact('users', 'search', 'status'));
    }

    public function create()
    {
        return view('admin.team.user', ['teamUser' => null, 'roles' => AdminRole::orderBy('name')->get()]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        $user = User::forceCreate([
            'name' => $data['name'],
            'username' => $data['username'],
            'email' => !empty($data['email']) ? Str::lower($data['email']) : null,
            'phone' => $data['phone'] ?? null,
            'password' => Hash::make($data['password']),
            'is_active' => $request->boolean('is_active'),
            'must_change_password' => false, // they keep the password given here; only the Super Admin changes it
        ] + $this->access($data['access']));
        Log::warning('Admin-panel user created', ['username' => $user->username, 'access' => $data['access'], 'by' => $request->user()->username]);

        return redirect()->route('admin.team.users.index')->with('success', 'User ' . $user->username . ' created. They sign in at this admin address'
            . ' with the password you gave them; only you can change it (Edit user).');
    }

    public function edit(User $teamUser)
    {
        $this->ensureTeam($teamUser);

        return view('admin.team.user', ['teamUser' => $teamUser, 'roles' => AdminRole::orderBy('name')->get()]);
    }

    public function update(Request $request, User $teamUser)
    {
        $this->ensureTeam($teamUser);
        $data = $this->validated($request, $teamUser);
        $access = $this->access($data['access']);
        $active = $request->boolean('is_active');

        if ($error = $this->protectSuperAdmin($request, $teamUser, $access['role'] === 'admin' && $active)) {
            return back()->withInput()->withErrors(['access' => $error]);
        }

        $teamUser->forceFill([
            'name' => $data['name'],
            'username' => $data['username'],
            'email' => !empty($data['email']) ? Str::lower($data['email']) : null,
            'phone' => $data['phone'] ?? null,
            'is_active' => $active,
        ] + $access);
        if (!empty($data['password'])) {
            // A new password also signs that user out everywhere ("auth.session" sees the changed hash)
            $teamUser->password = Hash::make($data['password']);
            $teamUser->must_change_password = false;
        }
        $teamUser->save();
        Log::warning('Admin-panel user changed', ['username' => $teamUser->username, 'access' => $data['access'], 'active' => $active, 'new_password' => !empty($data['password']), 'by' => $request->user()->username]);

        return redirect()->route('admin.team.users.index')->with('success', 'User ' . $teamUser->username . ' saved.');
    }

    /** The Activate / Deactivate button in the list */
    public function status(Request $request, User $teamUser)
    {
        $this->ensureTeam($teamUser);
        $active = !$teamUser->is_active;
        if (!$active && ($error = $this->protectSuperAdmin($request, $teamUser, false))) {
            return back()->with('error', $error);
        }
        $teamUser->forceFill(['is_active' => $active])->save();
        Log::warning('Admin-panel user ' . ($active ? 'activated' : 'deactivated'), ['username' => $teamUser->username, 'by' => $request->user()->username]);

        return back()->with('success', $teamUser->username . ($active ? ' can sign in again.' : ' is deactivated and can no longer sign in.'));
    }

    public function destroy(Request $request, User $teamUser)
    {
        $this->ensureTeam($teamUser);
        if ($teamUser->is($request->user())) {
            return back()->with('error', 'You cannot delete your own login.');
        }
        if ($error = $this->protectSuperAdmin($request, $teamUser, false)) {
            return back()->with('error', $error);
        }
        $teamUser->delete();
        Log::warning('Admin-panel user deleted', ['username' => $teamUser->username, 'by' => $request->user()->username]);

        return redirect()->route('admin.team.users.index')->with('success', 'User ' . $teamUser->username . ' deleted. They can no longer sign in.');
    }

    /** Only admin-panel logins here (members are in Members) */
    private function ensureTeam(User $user): void
    {
        abort_unless($user->canUseAdminPanel(), 404);
    }

    /**
     * Why this change may not happen ($staysSuperAdmin: the user is still an active Super Admin afterwards):
     * nobody takes Super Admin from themselves, and there is always one active Super Admin left.
     */
    private function protectSuperAdmin(Request $request, User $user, bool $staysSuperAdmin): ?string
    {
        if ($staysSuperAdmin || !$user->isAdmin()) {
            return null;
        }
        if ($user->is($request->user())) {
            return 'You cannot take Super Admin away from yourself or deactivate your own login.';
        }
        if (!User::where('role', 'admin')->where('is_active', true)->whereKeyNot($user->id)->exists()) {
            return 'This is the only active Super Admin. Make another user Super Admin first.';
        }

        return null;
    }

    /** "admin" = Super Admin (everything); a role id = only that role's permissions */
    private function access(string $access): array
    {
        return $access === 'admin' ? ['role' => 'admin', 'admin_role_id' => null] : ['role' => 'staff', 'admin_role_id' => (int) $access];
    }

    private function validated(Request $request, ?User $user = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'username' => ['required', 'string', 'min:3', 'max:50', 'regex:/^[A-Za-z0-9._-]+$/', Rule::unique('users', 'username')->ignore($user)],
            'email' => ['nullable', 'email', 'max:150', Rule::unique('users', 'email')->ignore($user)],
            'phone' => ['nullable', 'string', 'max:20', 'regex:/^\+?[0-9 ]{7,20}$/'],
            // Only "admin" or the id of an existing role: a role name or any other value is refused
            'access' => ['required', Rule::in(array_merge(['admin'], AdminRole::pluck('id')->map(fn ($id) => (string) $id)->all()))],
            'is_active' => ['nullable', 'boolean'],
            'password' => [$user ? 'nullable' : 'required', 'confirmed', Password::min(10)->mixedCase()->numbers()->symbols()],
        ], [
            'username.regex' => 'Use only letters, numbers, dot, dash or underscore (no spaces).',
            'username.unique' => 'This username is already taken.',
            'phone.regex' => 'Enter a phone number with digits only (a leading + is fine).',
            'access.required' => 'Choose Super Admin or a role.',
            'access.in' => 'Choose Super Admin or a role.',
        ]);
    }
}
