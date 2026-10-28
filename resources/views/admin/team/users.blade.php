@extends('admin.layouts.app')

@section('page-title', 'User & Role Management')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <form method="GET" action="{{ route('admin.team.users.index') }}" class="d-flex gap-2 flex-wrap">
    <input type="search" name="q" value="{{ $search }}" class="form-control form-control-sm" style="width:240px;border-radius:8px" placeholder="Search name, username, email, phone" aria-label="Search users">
    <select name="status" class="form-select form-select-sm" style="width:140px;border-radius:8px" aria-label="Status">
      <option value="">All users</option>
      <option value="active" @selected($status === 'active')>Active</option>
      <option value="inactive" @selected($status === 'inactive')>Inactive</option>
    </select>
    <button class="btn btn-sm btn-primary" style="border-radius:8px;font-weight:600"><i class="bi bi-search"></i></button>
    @if($search !== '' || $status)<a href="{{ route('admin.team.users.index') }}" class="btn btn-sm btn-outline-secondary" style="border-radius:8px">Clear</a>@endif
  </form>
  <div class="d-flex gap-2">
    <a href="{{ route('admin.team.roles.index') }}" class="btn btn-outline-primary btn-sm" style="border-radius:8px;font-weight:600"><i class="bi bi-ui-checks-grid me-1"></i> Roles & Permissions</a>
    <a href="{{ route('admin.team.users.create') }}" class="btn btn-primary btn-sm" style="border-radius:8px;font-weight:600"><i class="bi bi-person-plus-fill me-1"></i> Add User</a>
  </div>
</div>
<p class="text-muted" style="font-size:13px;max-width:860px">Logins for the people who work in the admin panel. <b>Super Admin</b> can do everything (and manage users and roles); every other user gets one <b>role</b> and can only see and use what that role allows. You set the first password; each user changes it in My Account. Members (subscribers) are under Members.</p>

<div class="table-card">
  <table class="table">
    <thead>
      <tr><th>Username (login)</th><th>Name</th><th>Role</th><th>Status</th><th>Last seen</th><th>Actions</th></tr>
    </thead>
    <tbody>
      @forelse($users as $u)
      @php $me = $u->is(auth()->user()); @endphp
      <tr>
        <td><strong>{{ $u->username }}</strong>@if($me)<span class="text-muted" style="font-size:11px"> (you)</span>@endif
</td>
        <td>{{ $u->name }}<div class="text-muted" style="font-size:11px">{{ collect([$u->email, $u->phone])->filter()->implode(' · ') }}</div></td>
        <td>
          @if($u->isAdmin())
            <span class="badge-active"><i class="bi bi-shield-fill-check me-1"></i>Super Admin</span>
          @elseif($u->adminRole)
            <span class="badge-inactive" style="background:#eef2ff;color:#2b3f9e">{{ $u->adminRole->name }}</span>
            @unless($u->adminRole->is_active)<div style="font-size:11px;color:#dc2626">role switched off</div>@endunless
          @else
            <span class="badge-inactive">No role</span>
          @endif
        </td>
        <td>@if($u->is_active)<span class="badge-active">Active</span>@else<span class="badge-inactive">Inactive</span>@endif</td>
        <td style="font-size:12px">{{ $u->last_seen_at?->timezone('Asia/Kolkata')->format('d M Y, h:i A') ?? '-' }}</td>
        <td class="text-nowrap">
          <a href="{{ route('admin.team.users.edit', $u) }}" class="btn btn-sm btn-outline-primary" style="border-radius:6px;font-size:11px" title="Edit"><i class="bi bi-pencil"></i></a>
          @unless($me)
          <form action="{{ route('admin.team.users.status', $u) }}" method="POST" class="d-inline" onsubmit="return confirm(@js(($u->is_active ? 'Deactivate ' : 'Activate ') . $u->username . '?'))">
            @csrf @method('PATCH')
            <button class="btn btn-sm {{ $u->is_active ? 'btn-outline-warning' : 'btn-outline-success' }}" style="border-radius:6px;font-size:11px" title="{{ $u->is_active ? 'Deactivate' : 'Activate' }}"><i class="bi {{ $u->is_active ? 'bi-pause-circle' : 'bi-play-circle' }}"></i></button>
          </form>
          <form action="{{ route('admin.team.users.destroy', $u) }}" method="POST" class="d-inline" onsubmit="return confirm(@js('Delete the login ' . $u->username . '? They will no longer be able to sign in.'))">
            @csrf @method('DELETE')
            <button class="btn btn-sm btn-outline-danger" style="border-radius:6px;font-size:11px" title="Delete"><i class="bi bi-trash"></i></button>
          </form>
          @endunless
        </td>
      </tr>
      @empty
      <tr><td colspan="6" class="text-center text-muted py-4">No users found.</td></tr>
      @endforelse
    </tbody>
  </table>
</div>
<div class="mt-3">{{ $users->links() }}</div>
@endsection
