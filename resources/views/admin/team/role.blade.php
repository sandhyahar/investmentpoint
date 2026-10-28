@extends('admin.layouts.app')

@section('page-title', $role ? 'Edit Role' : 'Add Role')

@section('content')
@php
  $ticked = old('permissions', $ticked);
  $actions = \App\Support\AdminPages::ACTIONS;
  $groups = collect(\App\Support\AdminPages::PAGES)->groupBy(fn ($p) => $p[1], true);
@endphp
<div class="mb-3">
  <a href="{{ route('admin.team.roles.index') }}" class="btn btn-sm btn-outline-secondary" style="border-radius:8px;font-size:12px"><i class="bi bi-arrow-left me-1"></i> Back to Roles</a>
</div>

<form action="{{ $role ? route('admin.team.roles.update', $role) : route('admin.team.roles.store') }}" method="POST" id="roleForm">
  @csrf
  @if($role) @method('PUT') @endif
  <div class="form-card mb-4" style="max-width:980px">
    <div class="row">
      <div class="col-md-5 mb-3">
        <label class="form-label" for="name">Role name *</label>
        <input type="text" class="form-control @error('name') is-invalid @enderror" id="name" name="name" value="{{ old('name', $role->name ?? '') }}" maxlength="60" required placeholder="e.g. News Editor, Market Analyst">
        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
      </div>
      <div class="col-md-7 mb-3">
        <label class="form-label" for="description">Description <span class="text-muted fw-normal">(optional)</span></label>
        <input type="text" class="form-control @error('description') is-invalid @enderror" id="description" name="description" value="{{ old('description', $role->description ?? '') }}" maxlength="255" placeholder="What people with this role do">
        @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
      </div>
    </div>
    <input type="hidden" name="is_active" value="0">
    <div class="form-check form-switch">
      <input class="form-check-input" type="checkbox" role="switch" id="is_active" name="is_active" value="1" @checked(old('is_active', $role->is_active ?? true))>
      <label class="form-check-label" for="is_active" style="font-size:13.5px"><b>Active</b> <span class="text-muted">— switched off, no user of this role can sign in</span></label>
    </div>
  </div>

  <div class="form-card" style="max-width:980px">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-1">
      <h2 style="font-size:16px;font-weight:800;color:#10253f;margin:0"><i class="bi bi-ui-checks-grid me-1"></i> Page permissions *</h2>
      <div class="d-flex gap-2">
        <button type="button" class="btn btn-sm btn-outline-primary" data-tick="all" style="border-radius:8px;font-size:12px;font-weight:600"><i class="bi bi-check2-all me-1"></i> Select all</button>
        <button type="button" class="btn btn-sm btn-outline-secondary" data-tick="none" style="border-radius:8px;font-size:12px;font-weight:600"><i class="bi bi-x-lg me-1"></i> Deselect all</button>
      </div>
    </div>
    <p class="text-muted" style="font-size:12px">Create, Edit and Delete also tick View (the list page). A dash means the page has no such action. My Account (own password) is always open; User & Role Management and Role & Permissions are only for the Super Admin.</p>
    @error('permissions')<div class="alert alert-danger py-2" style="font-size:13px">{{ $message }}</div>@enderror
    @error('permissions.*')<div class="alert alert-danger py-2" style="font-size:13px">{{ $message }}</div>@enderror

    <div class="table-responsive">
      <table class="table align-middle mb-0" style="font-size:13.5px">
        <thead>
          <tr>
            <th>Admin page</th>
            @foreach($actions as $action => $label)
              <th class="text-center" style="width:92px">
                <div>{{ $label }}</div>
                <input class="form-check-input mt-1" type="checkbox" data-col="{{ $action }}" title="{{ $label }}: all pages" aria-label="{{ $label }} on all pages">
              </th>
            @endforeach
            <th class="text-center" style="width:70px">Row</th>
          </tr>
        </thead>
        <tbody>
          @foreach($groups as $group => $pages)
            <tr><td colspan="{{ count($actions) + 2 }}" style="background:#f6f8fc;font-size:11.5px;font-weight:800;text-transform:uppercase;letter-spacing:.05em;color:#6b7a90">{{ $group }}</td></tr>
            @foreach($pages as $page => $p)
            <tr data-row="{{ $page }}">
              <td>{{ $p[0] }}</td>
              @foreach($actions as $action => $label)
                <td class="text-center">
                  @if(in_array($action, $p[3], true))
                    <input class="form-check-input perm" type="checkbox" name="permissions[]" value="{{ $page }}.{{ $action }}" id="perm-{{ $page }}-{{ $action }}"
                      data-page="{{ $page }}" data-action="{{ $action }}" aria-label="{{ $label }} {{ $p[0] }}" @checked(in_array($page . '.' . $action, $ticked, true))>
                  @else
                    <span class="text-muted">—</span>
                  @endif
                </td>
              @endforeach
              <td class="text-center"><input class="form-check-input" type="checkbox" data-rowall="{{ $page }}" aria-label="All actions on {{ $p[0] }}"></td>
            </tr>
            @endforeach
          @endforeach
        </tbody>
      </table>
    </div>

    <div class="d-flex gap-2 mt-3">
      <button type="submit" class="btn btn-primary" style="border-radius:8px;font-weight:600"><i class="bi bi-check-lg me-1"></i> Save Permissions</button>
      <a href="{{ route('admin.team.roles.index') }}" class="btn btn-outline-secondary" style="border-radius:8px;font-weight:600">Cancel</a>
    </div>
  </div>
</form>

<script>
(function () {
  const boxes = Array.from(document.querySelectorAll('#roleForm .perm'));
  const of = (attr, value) => boxes.filter(b => b.dataset[attr] === value);
  const setState = (el, list) => { const n = list.filter(b => b.checked).length; el.checked = n > 0 && n === list.length; el.indeterminate = n > 0 && n < list.length; };
  function sync() {
    document.querySelectorAll('[data-col]').forEach(el => setState(el, of('action', el.dataset.col)));
    document.querySelectorAll('[data-rowall]').forEach(el => setState(el, of('page', el.dataset.rowall)));
  }
  // Create / Edit / Delete need View; taking View away takes the others too
  function needView(box) {
    const page = of('page', box.dataset.page);
    const view = page.find(b => b.dataset.action === 'view');
    if (box.checked && box.dataset.action !== 'view' && view) view.checked = true;
    if (!box.checked && box.dataset.action === 'view') page.forEach(b => { b.checked = false; });
  }
  boxes.forEach(b => b.addEventListener('change', () => { needView(b); sync(); }));
  document.querySelectorAll('[data-col]').forEach(el => el.addEventListener('change', () => {
    of('action', el.dataset.col).forEach(b => { b.checked = el.checked; needView(b); }); sync();
  }));
  document.querySelectorAll('[data-rowall]').forEach(el => el.addEventListener('change', () => {
    of('page', el.dataset.rowall).forEach(b => { b.checked = el.checked; }); sync();
  }));
  document.querySelectorAll('[data-tick]').forEach(btn => btn.addEventListener('click', () => {
    boxes.forEach(b => { b.checked = btn.dataset.tick === 'all'; }); sync();
  }));
  sync();
})();
</script>
@endsection
