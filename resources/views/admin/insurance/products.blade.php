@extends('admin.layouts.app')

{{-- Admin -> Services -> Insurance Products (Admin\InsuranceProductController): $insurance (life | general), $products,
     $generalShown --}}
@php $label = \App\Models\InsuranceProduct::INSURANCES[$insurance]; @endphp
@section('page-title', 'Insurance Products')

@section('content')
<div class="ins-admin-tabs mb-3">
  @foreach(\App\Models\InsuranceProduct::INSURANCES as $key => $name)
    <a href="{{ route('admin.insurance-products.index', ['insurance' => $key]) }}" @class(['active' => $key === $insurance])>{{ $name }}@if($key === 'general' && !$generalShown) <span class="ins-hidden-tag">hidden</span>@endif</a>
  @endforeach
</div>

<div class="alert alert-info d-flex gap-2 align-items-start" style="font-size:13px;border-radius:10px;max-width:980px">
  <i class="bi bi-info-circle-fill"></i>
  <div>The plans readers can tick on the <b>{{ $label }}</b> page (website menu → Services → Insurance → {{ $label }}, tab "Products").
    What they send is in <a href="{{ route('admin.insurance-enquiries.index') }}">Insurance Enquiries</a>.
    @if(in_array($insurance, \App\Models\InsuranceProduct::shown(), true))
      <a href="{{ route('services.insurance', $insurance) }}" target="_blank" rel="noopener">Open the page <i class="bi bi-box-arrow-up-right"></i></a>
    @else
      <b>This page is hidden on the website</b> until you switch on "Show General Insurance" below.
    @endif
  </div>
</div>

<div class="table-card mb-4" style="max-width:980px">
  <table class="table align-middle">
    <thead><tr><th style="width:80px">Order</th><th>Plan</th><th style="width:120px">On website</th><th style="width:170px">Actions</th></tr></thead>
    <tbody>
      @forelse($products as $product)
        <tr>
          <td colspan="4" class="p-0">
            <form action="{{ route('admin.insurance-products.update', $product) }}" method="POST" class="ins-row">
              @csrf @method('PUT')
              <input type="number" name="sort_order" value="{{ $product->sort_order }}" min="0" max="999" class="form-control form-control-sm" aria-label="Order">
              <div class="ins-row-text">
                <input type="text" name="name" value="{{ $product->name }}" maxlength="120" required class="form-control form-control-sm fw-semibold" aria-label="Plan name">
                <input type="text" name="description" value="{{ $product->description }}" maxlength="300" class="form-control form-control-sm mt-1" placeholder="One line about this plan (optional)" aria-label="Description">
              </div>
              <label class="form-check form-switch m-0"><input class="form-check-input" type="checkbox" name="is_active" value="1" @checked($product->is_active)> <span class="form-check-label" style="font-size:12px">{{ $product->is_active ? 'Shown' : 'Hidden' }}</span></label>
              <div class="d-flex gap-1">
                <button class="btn btn-sm btn-primary" style="border-radius:6px;font-size:12px"><i class="bi bi-check-lg"></i> Save</button>
                <button type="submit" form="del-{{ $product->id }}" class="btn btn-sm btn-outline-danger" style="border-radius:6px;font-size:12px" title="Delete"><i class="bi bi-trash"></i></button>
              </div>
            </form>
            <form id="del-{{ $product->id }}" action="{{ route('admin.insurance-products.destroy', $product) }}" method="POST" class="d-none" onsubmit="return confirm('Delete the plan {{ addslashes($product->name) }}? Enquiries already sent keep its name.')">@csrf @method('DELETE')</form>
          </td>
        </tr>
      @empty
        <tr><td colspan="4" class="text-center text-muted py-4">No plans yet. Add the first one below.</td></tr>
      @endforelse
    </tbody>
  </table>
</div>

<div class="row g-3" style="max-width:1000px">
  <div class="col-md-6">
    <div class="form-card h-100">
      <h5 class="mb-3" style="font-size:16px;font-weight:700"><i class="bi bi-plus-circle me-1"></i> Add a {{ $label }} plan</h5>
      <form action="{{ route('admin.insurance-products.store') }}" method="POST">
        @csrf
        <input type="hidden" name="insurance" value="{{ $insurance }}">
        <div class="mb-2"><label class="form-label" for="new_name">Plan name *</label>
          <input type="text" id="new_name" name="name" value="{{ old('name') }}" maxlength="120" required class="form-control @error('name') is-invalid @enderror" placeholder="e.g. Term Plan">
          @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
        <div class="mb-3"><label class="form-label" for="new_description">One line about it</label>
          <input type="text" id="new_description" name="description" value="{{ old('description') }}" maxlength="300" class="form-control" placeholder="e.g. Pure protection at a low premium"></div>
        <button class="btn btn-primary" style="border-radius:8px;font-weight:600"><i class="bi bi-plus-lg me-1"></i> Add plan</button>
      </form>
    </div>
  </div>
  <div class="col-md-6">
    <div class="form-card h-100">
      <h5 class="mb-3" style="font-size:16px;font-weight:700"><i class="bi bi-sliders me-1"></i> Insurance settings</h5>
      <form action="{{ route('admin.insurance-products.settings') }}" method="POST">
        @csrf @method('PUT')
        <input type="hidden" name="insurance" value="{{ $insurance }}">
        <label class="form-check form-switch mb-3"><input class="form-check-input" type="checkbox" name="general_enabled" value="1" @checked($generalShown)>
          <span class="form-check-label">Show <b>General Insurance</b> in the website's Services menu</span></label>
        <div class="mb-3" style="max-width:420px">
          <label class="form-label fw-semibold" for="notify_email">Email each enquiry to</label>
          <input type="email" id="notify_email" name="notify_email" class="form-control @error('notify_email') is-invalid @enderror" value="{{ old('notify_email', $notifyEmail) }}" placeholder="name@example.com">
          @error('notify_email')<div class="invalid-feedback">{{ $message }}</div>@enderror
          <div class="form-text">Every "Get a call back" and "Join now" sent on the Insurance pages comes to this address with all the details. Leave it empty to stop the emails.</div>
        </div>
        <button class="btn btn-outline-primary" style="border-radius:8px;font-weight:600"><i class="bi bi-check-lg me-1"></i> Save settings</button>
      </form>
    </div>
  </div>
</div>
@endsection

@section('extra-css')
<style>
.ins-admin-tabs{display:flex;flex-wrap:wrap;gap:6px}
.ins-admin-tabs a{font-size:13px;font-weight:600;color:#43546a;background:#fff;border:1px solid #e2e9f0;border-radius:20px;padding:6px 14px;text-decoration:none}
.ins-admin-tabs a.active{background:#2b3f9e;border-color:#2b3f9e;color:#fff}
.ins-hidden-tag{font-size:10px;font-weight:700;background:#fef3c7;color:#92400e;border-radius:10px;padding:1px 7px;margin-left:4px}
.ins-row{display:grid;grid-template-columns:80px minmax(0,1fr) 120px 170px;gap:12px;align-items:center;padding:10px 16px}
@media(max-width:768px){.ins-row{grid-template-columns:70px minmax(0,1fr)}.ins-row>label,.ins-row>div:last-child{grid-column:2}}
</style>
@endsection
