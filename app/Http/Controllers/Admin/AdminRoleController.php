<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminRole;
use App\Support\AdminPages;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

/**
 * Role & Permission Management (Super Admin only): roles with a name, description, Active switch and
 * View / Create / Edit / Delete ticks for each admin page.
 */
class AdminRoleController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->input('q'));
        $roles = AdminRole::withCount(['users', 'permissions'])
            ->when($search !== '', fn ($q) => $q->where(fn ($q) => $q->where('name', 'like', "%{$search}%")->orWhere('description', 'like', "%{$search}%")))
            ->orderBy('name')->paginate(20)->withQueryString();

        return view('admin.team.roles', compact('roles', 'search'));
    }

    public function create()
    {
        return view('admin.team.role', ['role' => null, 'ticked' => []]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $role = DB::transaction(function () use ($data) {
            $role = AdminRole::create($data['role']);
            $role->syncPermissions($data['permissions']);

            return $role;
        });
        Log::warning('Admin role created', ['role' => $role->name, 'permissions' => $role->permissionKeys(), 'by' => $request->user()->username]);

        return redirect()->route('admin.team.roles.index')->with('success', 'Role "' . $role->name . '" created with ' . count($role->permissionKeys()) . ' permission(s).');
    }

    public function edit(AdminRole $adminRole)
    {
        return view('admin.team.role', ['role' => $adminRole, 'ticked' => $adminRole->permissionKeys()]);
    }

    public function update(Request $request, AdminRole $adminRole)
    {
        $data = $this->validated($request, $adminRole);
        DB::transaction(function () use ($adminRole, $data) {
            $adminRole->update($data['role']);
            $adminRole->syncPermissions($data['permissions']);
        });
        Log::warning('Admin role changed', ['role' => $adminRole->name, 'active' => $adminRole->is_active, 'permissions' => $adminRole->permissionKeys(), 'by' => $request->user()->username]);

        return redirect()->route('admin.team.roles.index')->with('success', 'Permissions of "' . $adminRole->name . '" saved. Its users have them from their next click.');
    }

    public function destroy(Request $request, AdminRole $adminRole)
    {
        if ($count = $adminRole->users()->count()) {
            return back()->with('error', 'Role "' . $adminRole->name . '" still has ' . $count . ' user(s). Give them another role first, or switch the role off instead.');
        }
        $adminRole->delete();
        Log::warning('Admin role deleted', ['role' => $adminRole->name, 'by' => $request->user()->username]);

        return redirect()->route('admin.team.roles.index')->with('success', 'Role "' . $adminRole->name . '" deleted.');
    }

    /** @return array{role: array, permissions: array} */
    private function validated(Request $request, ?AdminRole $role = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:60', Rule::unique('admin_roles', 'name')->ignore($role)],
            'description' => ['nullable', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
            'permissions' => ['required', 'array', 'min:1'],
            'permissions.*' => ['string', Rule::in(AdminPages::allPermissions())],
        ], [
            'permissions.required' => 'Tick at least one permission for this role.',
            'permissions.*.in' => 'An unknown permission was sent. Reload the page and try again.',
            'name.unique' => 'A role with this name already exists.',
        ]);

        return [
            'role' => ['name' => trim($data['name']), 'description' => $data['description'] ?? null, 'is_active' => $request->boolean('is_active')],
            'permissions' => $data['permissions'],
        ];
    }
}
