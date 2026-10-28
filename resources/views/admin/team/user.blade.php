@extends('admin.layouts.app')

@section('page-title', $teamUser ? 'Edit User' : 'Add User')

@section('content')
@php
  $access = old('access', $teamUser ? ($teamUser->isAdmin() ? 'admin' : (string) $teamUser->admin_role_id) : '');
  $isMe = $teamUser && $teamUser->is(auth()->user());
@endphp
<div class="mb-3">
  <a href="{{ route('admin.team.users.index') }}" class="btn btn-sm btn-outline-secondary" style="border-radius:8px;font-size:12px"><i class="bi bi-arrow-left me-1"></i> Back to Users</a>
</div>

<div class="form-card" style="max-width:780px">
  <form action="{{ $teamUser ? route('admin.team.users.update', $teamUser) : route('admin.team.users.store') }}" method="POST" autocomplete="off">
    @csrf
    @if($teamUser) @method('PUT') @endif
    <div class="row">
      <div class="col-md-6 mb-3">
        <label class="form-label" for="name">Full name *</label>
        <input type="text" class="form-control @error('name') is-invalid @enderror" id="name" name="name" value="{{ old('name', $teamUser->name ?? '') }}" maxlength="100" required>
        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
      </div>
      <div class="col-md-6 mb-3">
        <label class="form-label" for="username">Username (for login) *</label>
        <input type="text" class="form-control @error('username') is-invalid @enderror" id="username" name="username" value="{{ old('username', $teamUser->username ?? '') }}" maxlength="50" required autocomplete="off" placeholder="e.g. rahulpatel">
        @error('username')<div class="invalid-feedback">{{ $message }}</div>@enderror
      </div>
      <div class="col-md-6 mb-3">
        <label class="form-label" for="email">Email <span class="text-muted fw-normal">(optional)</span></label>
        <input type="email" class="form-control @error('email') is-invalid @enderror" id="email" name="email" value="{{ old('email', $teamUser->email ?? '') }}" maxlength="150">
        @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
      </div>
      <div class="col-md-6 mb-3">
        <label class="form-label" for="phone">Phone <span class="text-muted fw-normal">(optional)</span></label>
        <input type="tel" class="form-control @error('phone') is-invalid @enderror" id="phone" name="phone" value="{{ old('phone', $teamUser->phone ?? '') }}" maxlength="20">
        @error('phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
      </div>
      <div class="col-md-6 mb-3">
        <label class="form-label" for="access">Role *</label>
        <select class="form-select @error('access') is-invalid @enderror" id="access" name="access" required>
          <option value="" disabled @selected($access === '')>Choose…</option>
          <option value="admin" @selected($access === 'admin')>Super Admin (everything)</option>
          @foreach($roles as $role)
            <option value="{{ $role->id }}" @selected($access === (string) $role->id)>{{ $role->name }}{{ $role->is_active ? '' : ' (switched off)' }}</option>
          @endforeach
        </select>
        @error('access')<div class="invalid-feedback">{{ $message }}</div>@enderror
        <div class="form-text">@if($roles->isEmpty())No roles yet: <a href="{{ route('admin.team.roles.create') }}">add a role</a> to give only some pages.@else Permissions of each role: <a href="{{ route('admin.team.roles.index') }}">Roles & Permissions</a>.@endif</div>
      </div>
      <div class="col-md-6 mb-3 d-flex align-items-end">
        <input type="hidden" name="is_active" value="0">
        <div class="form-check form-switch mb-2">
          <input class="form-check-input" type="checkbox" role="switch" id="is_active" name="is_active" value="1" @checked(old('is_active', $teamUser->is_active ?? true)) @disabled($isMe)>
          <label class="form-check-label" for="is_active"><b>Active</b> <span class="text-muted" style="font-size:12.5px">— can sign in</span></label>
          @if($isMe)<input type="hidden" name="is_active" value="1">@endif
        </div>
      </div>
    </div>

    <hr>
    <h6 class="mb-1" style="font-weight:700">{{ $teamUser ? 'Reset password' : 'Initial password' }}</h6>
    <p class="text-muted mb-3" style="font-size:12px">{{ $teamUser ? 'Leave empty to keep the current password. A new password signs this user out everywhere.' : 'Tell this password to the user.' }} At least 10 characters, with upper- and lower-case letters, a number and a symbol. Passwords are stored encrypted and are never shown again.</p>
    <div class="row">
      <div class="col-md-6 mb-3">
        <label class="form-label" for="password">{{ $teamUser ? 'New password' : 'Password *' }}</label>
        <div class="input-group has-validation">
          <input type="password" class="form-control @error('password') is-invalid @enderror" id="password" name="password" @if(!$teamUser) required @endif minlength="10" autocomplete="new-password">
          <button type="button" class="btn btn-outline-secondary" data-toggle-password="password" aria-label="Show password" title="Show password"><i class="bi bi-eye"></i></button>
          @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
      </div>
      <div class="col-md-6 mb-3">
        <label class="form-label" for="password_confirmation">Confirm password</label>
        <div class="input-group">
          <input type="password" class="form-control" id="password_confirmation" name="password_confirmation" @if(!$teamUser) required @endif autocomplete="new-password">
          <button type="button" class="btn btn-outline-secondary" data-toggle-password="password_confirmation" aria-label="Show password" title="Show password"><i class="bi bi-eye"></i></button>
        </div>
      </div>
    </div>
    {{-- The user keeps this password: only the Super Admin changes it, here (owner, 23 Oct 2026) --}}
    <p class="text-muted mb-3" style="font-size:12.5px"><i class="bi bi-key me-1"></i> The user signs in with this password and cannot change it; only you can, here.</p>

    <div class="d-flex gap-2">
      <button type="submit" class="btn btn-primary" style="border-radius:8px;font-weight:600"><i class="bi bi-check-lg me-1"></i> {{ $teamUser ? 'Save User' : 'Create User' }}</button>
      <a href="{{ route('admin.team.users.index') }}" class="btn btn-outline-secondary" style="border-radius:8px;font-weight:600">Cancel</a>
    </div>
  </form>
</div>

<script>
// Eye buttons: show / hide what is typed in each password box
document.querySelectorAll('[data-toggle-password]').forEach(function (button) {
  button.addEventListener('click', function () {
    const input = document.getElementById(button.dataset.togglePassword);
    const show = input.type === 'password';
    input.type = show ? 'text' : 'password';
    button.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
    button.title = show ? 'Hide password' : 'Show password';
    button.innerHTML = show ? '<i class="bi bi-eye-slash"></i>' : '<i class="bi bi-eye"></i>';
  });
});
</script>
@endsection
