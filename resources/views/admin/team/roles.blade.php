@extends('admin.layouts.app')

@section('page-title', 'Role & Permission Management')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <form method="GET" action="{{ route('admin.team.roles.index') }}" class="d-flex gap-2">
    <input type="search" name="q" value="{{ $search }}" class="form-control form-control-sm" style="width:240px;border-radius:8px" placeholder="Search roles" aria-label="Search roles">
    <button class="btn btn-sm btn-primary" style="border-radius:8px;font-weight:600"><i class="bi bi-search"></i></button>
    @if($search !== '')<a href="{{ route('admin.team.roles.index') }}" class="btn btn-sm btn-outline-secondary" style="border-radius:8px">Clear</a>@endif
  </form>
  <div class="d-flex gap-2">
    <a href="{{ route('admin.team.users.index') }}" class="btn btn-outline-primary btn-sm" style="border-radius:8px;font-weight:600"><i class="bi bi-people-fill me-1"></i> Users</a>
    <a href="{{ route('admin.team.roles.create') }}" class="btn btn-primary btn-sm" style="border-radius:8px;font-weight:600"><i class="bi bi-plus-lg me-1"></i> Add Role</a>
  </div>
</div>
<p class="text-muted" style="font-size:13px;max-width:860px">A role is a set of permissions: for each admin page, whether its users may <b>View</b>, <b>Create</b>, <b>Edit</b> or <b>Delete</b>. Give a role to users in User & Role Management. A role that is switched off stops all its users from signing in.</p>

<div class="table-card">
  <table class="table">
    <thead>
      <tr><th>Role</th><th>Description</th><th>Permissions</th><th>Users</th><th>Status</th><th>Updated</th><th>Actions</th></tr>
    </thead>
    <tbody>
      @forelse($roles as $role)
      <tr>
        <td><strong>{{ $role->name }}</strong><div class="text-muted" style="font-size:11px">Created {{ $role->created_at?->timezone('Asia/Kolkata')->format('d M Y') }}</div></td>
        <td style="font-size:13px;max-width:320px">{{ $role->description ?: '-' }}</td>
        <td>{{ $role->permissions_count }}</td>
        <td>{{ $role->users_count }}</td>
        <td>@if($role->is_active)<span class="badge-active">Active</span>@else<span class="badge-inactive">Inactive</span>@endif</td>
        <td style="font-size:12px">{{ $role->updated_at?->timezone('Asia/Kolkata')->format('d M Y, h:i A') }}</td>
        <td class="text-nowrap">
          <a href="{{ route('admin.team.roles.edit', $role) }}" class="btn btn-sm btn-outline-primary" style="border-radius:6px;font-size:11px" title="Edit permissions"><i class="bi bi-pencil"></i></a>
          <form action="{{ route('admin.team.roles.destroy', $role) }}" method="POST" class="d-inline" onsubmit="return confirm(@js('Delete the role ' . $role->name . '?'))">
            @csrf @method('DELETE')
            <button class="btn btn-sm btn-outline-danger" style="border-radius:6px;font-size:11px" title="Delete"><i class="bi bi-trash"></i></button>
          </form>
        </td>
      </tr>
      @empty
      <tr><td colspan="7" class="text-center text-muted py-4">No roles yet. <a href="{{ route('admin.team.roles.create') }}">Add a role</a>, e.g. "News Editor" with Market Trend and Latest News.</td></tr>
      @endforelse
    </tbody>
  </table>
</div>
<div class="mt-3">{{ $roles->links() }}</div>
@endsection
