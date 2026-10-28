@extends('admin.layouts.app')

@section('page-title', 'My Account')

@section('content')
<div class="row g-4">
  <div class="col-lg-7">
    <div class="form-card">
      @unless($user->isAdmin())
      {{-- Team users keep the password the Super Admin gave them (owner, 23 Oct 2026) --}}
      <h5 style="font-weight:800;font-size:16px;margin-bottom:4px"><i class="bi bi-person-badge-fill me-1" style="color:#2b3f9e"></i> Your login</h5>
      <p class="text-muted" style="font-size:13px">Signed in as <b>{{ $user->username }}</b> ({{ $user->adminRole->name ?? 'no role' }}).</p>
      <div class="alert alert-light border py-2 mb-0" style="font-size:13px;border-radius:10px"><i class="bi bi-key me-1"></i> Your password is set by the Super Admin. To change it, please ask the Super Admin.</div>
      @else
      <h5 style="font-weight:800;font-size:16px;margin-bottom:4px"><i class="bi bi-shield-lock-fill me-1" style="color:#2b3f9e"></i> Change password</h5>
      <p class="text-muted" style="font-size:13px">Signed in as <b>{{ $user->username }}</b> (Super Admin). Changing the password logs out every other device using this account.</p>

      <form method="POST" action="{{ route('admin.account.password') }}" autocomplete="off">
        @csrf @method('PUT')
        <div class="mb-3">
          <label for="password">New password</label>
          <div class="input-group">
            <input type="password" class="form-control @error('password') is-invalid @enderror" id="password" name="password" required autocomplete="new-password" minlength="10">
            <button type="button" class="btn btn-outline-secondary" data-toggle-password="password" aria-label="Show password" title="Show password"><i class="bi bi-eye"></i></button>
            @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
          </div>
          <small class="text-muted" style="font-size:12px">At least 10 characters, with upper- and lower-case letters, a number and a symbol (e.g. <code>@ # $ !</code>).</small>
        </div>
        <div class="mb-3">
          <label for="password_confirmation">Repeat new password</label>
          <div class="input-group">
            <input type="password" class="form-control @error('password_confirmation') is-invalid @enderror" id="password_confirmation" name="password_confirmation" required autocomplete="new-password">
            <button type="button" class="btn btn-outline-secondary" data-toggle-password="password_confirmation" aria-label="Show password" title="Show password"><i class="bi bi-eye"></i></button>
            @error('password_confirmation')<div class="invalid-feedback">{{ $message }}</div>@enderror
          </div>
        </div>
        <button class="btn btn-primary" style="border-radius:8px;font-weight:600"><i class="bi bi-check-lg me-1"></i> Change password</button>
      </form>
      @endunless
    </div>
  </div>

  <div class="col-lg-5">
    <div class="widget">
      <h5 style="font-weight:800;font-size:15px"><i class="bi bi-shield-check me-1" style="color:#0a9f63"></i> How the admin panel is protected</h5>
      <ul style="font-size:13px;color:#4a5a6e;padding-left:18px;margin:10px 0 0;line-height:1.7">
        <li>After {{ config('admin.max_attempts') }} wrong passwords the login is locked for {{ config('admin.lockout_minutes') }} minutes.</li>
        <li>Only the Super Admin and the users made in User &amp; Role Management can sign in here; each sees and uses only what their role allows.</li>
        @if(config('admin.desktop_only'))<li>The admin panel works only on a computer or laptop; phones and tablets are refused and signed out.</li>@endif
        <li>You are logged out after {{ config('admin.idle_minutes') }} minutes without activity.</li>
        <li>Admin pages are never stored in the browser cache and are hidden from Google.</li>        @if(config('admin.path') === 'admin')
          <li class="text-warning-emphasis"><b>Tip:</b> on the live site, change the admin address with <code>ADMIN_PATH=</code> in <code>.env</code> so it isn't the easy-to-guess <code>/admin</code>.</li>
        @endif
      </ul>
    </div>
  </div>
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
