@extends('admin.layouts.app')

{{-- Admin -> Services -> Insurance Enquiries (Admin\InsuranceEnquiryController): $enquiries, $kind (null = all), $counts --}}
@section('page-title', 'Insurance Enquiries')

@section('content')
<div class="ins-admin-tabs mb-3">
  <a href="{{ route('admin.insurance-enquiries.index') }}" @class(['active' => !$kind])>All ({{ $counts->sum() }})</a>
  @foreach(\App\Models\InsuranceEnquiry::KINDS as $key => $name)
    <a href="{{ route('admin.insurance-enquiries.index', ['kind' => $key]) }}" @class(['active' => $kind === $key])>{{ $name }} ({{ $counts[$key] ?? 0 }})</a>
  @endforeach
</div>

<div class="alert alert-info d-flex gap-2 align-items-start" style="font-size:13px;border-radius:10px">
  <i class="bi bi-info-circle-fill"></i>
  <div>Sent from the website's Insurance pages (Services → Insurance): <b>Products</b> = the plans the reader ticked, <b>Join As Advisor</b> = wants to join as an advisor. Call them back, then delete the enquiry. Plans are set in <a href="{{ route('admin.insurance-products.index') }}">Insurance Products</a>.</div>
</div>

<form id="bulk-delete-form" action="{{ route('admin.insurance-enquiries.bulk-delete') }}" method="POST" data-bulk-form class="mb-3">
  @csrf
  <button type="submit" class="btn btn-sm btn-outline-danger" data-bulk-submit disabled><i class="bi bi-trash me-1"></i> Delete Selected</button>
</form>

<div class="table-card">
  <table class="table">
    <thead><tr><th><input type="checkbox" data-select-all aria-label="Select all enquiries"></th><th>Name, age &amp; mobile</th><th>Wants</th><th>Address, city &amp; PIN</th><th>Received</th><th></th></tr></thead>
    <tbody>
      @forelse($enquiries as $e)
      <tr @if(!$e->read_at) style="background:#fffbeb" @endif>
        <td><input type="checkbox" name="ids[]" value="{{ $e->id }}" form="bulk-delete-form" data-select-item></td>
        <td style="white-space:nowrap"><strong>{{ $e->name }}</strong>@if(!$e->read_at) <span class="badge-new">New</span>@endif
          @if($e->age)<small class="text-muted">· {{ $e->age }} yrs</small>@endif<br>
          <a href="tel:+91{{ $e->contact }}" style="font-size:13px"><i class="bi bi-telephone"></i> {{ $e->contact }}</a>
          · <a href="https://wa.me/91{{ $e->contact }}" target="_blank" rel="noopener" style="font-size:13px;color:#128c4b"><i class="bi bi-whatsapp"></i></a></td>
        <td style="max-width:300px">
          <span @class(['kind-tag', 'is-adviser' => $e->kind === 'adviser'])>{{ \App\Models\InsuranceEnquiry::KINDS[$e->kind] ?? $e->kind }}</span>
          <small class="text-muted">{{ \App\Models\InsuranceProduct::INSURANCES[$e->insurance] ?? $e->insurance }}</small>
          @if($e->products)<div style="font-size:13px;margin-top:4px">{{ implode(', ', $e->products) }}</div>@endif
        </td>
        <td style="max-width:280px;font-size:13px"><span style="white-space:pre-line">{{ $e->address }}</span>
          @if($e->city)<div><b>{{ $e->city }}</b>{{ $e->state ? ', ' . $e->state : '' }}{{ $e->pincode ? ' - ' . $e->pincode : '' }}</div>@endif</td>
        <td style="font-size:12px;color:#66798d;white-space:nowrap">{{ $e->created_at->timezone('Asia/Kolkata')->format('d M Y, h:i A') }}</td>
        <td>
          <form action="{{ route('admin.insurance-enquiries.destroy', $e) }}" method="POST" onsubmit="return confirm('Delete this enquiry?')">
            @csrf @method('DELETE')
            <button class="btn btn-sm btn-outline-danger" style="border-radius:6px;font-size:11px" title="Delete"><i class="bi bi-trash"></i></button>
          </form>
        </td>
      </tr>
      @empty
      <tr><td colspan="6" class="text-center text-muted py-4">No enquiries yet. They appear here when someone sends the form on an Insurance page.</td></tr>
      @endforelse
    </tbody>
  </table>
</div>
<div class="mt-3">{{ $enquiries->links() }}</div>
@endsection

@section('extra-css')
<style>
.ins-admin-tabs{display:flex;flex-wrap:wrap;gap:6px}
.ins-admin-tabs a{font-size:13px;font-weight:600;color:#43546a;background:#fff;border:1px solid #e2e9f0;border-radius:20px;padding:6px 14px;text-decoration:none}
.ins-admin-tabs a.active{background:#2b3f9e;border-color:#2b3f9e;color:#fff}
.badge-new{background:#fef3c7;color:#92400e;font-weight:700;font-size:10px;padding:2px 8px;border-radius:20px;margin-left:4px}
.kind-tag{display:inline-block;font-size:11px;font-weight:700;background:#e8f0ff;color:#2b3f9e;border-radius:12px;padding:2px 9px;margin-right:4px}
.kind-tag.is-adviser{background:#fff3d6;color:#8a5a00}
</style>
@endsection
