@extends('admin.layouts.app')

@php $label = \App\Models\DigitalNewspaper::EDITIONS[$edition]; @endphp
@section('page-title', 'Add ' . $label . ' Edition')

@section('content')
<div class="mb-3">
  <a href="{{ route('admin.digital-newspaper.index', ['edition' => $edition]) }}" class="btn btn-sm btn-outline-secondary" style="border-radius:8px;font-size:12px"><i class="bi bi-arrow-left me-1"></i> Back to {{ $label }}{{ $edition === 'weekly' ? '' : ' E-Paper' }}</a>
</div>

{{-- How an upload goes to Blogger (email for the daily papers) --}}
@include('admin.digital-newspaper._straight')

{{-- One PDF (the form below) or many PDFs / a folder at once (Bulk upload), like Media Articles. The "For Blogger (no email)"
     tab was removed (owner, 27 Oct 2026): daily editions made in Blogger come in with "Get all editions from Blogger" on the list --}}
<ul class="nav nav-pills mb-3 gap-2" role="tablist" style="font-size:13.5px;font-weight:600">
  <li class="nav-item" role="presentation"><button type="button" class="nav-link active" data-ep-tab="single" role="tab" aria-selected="true" style="border-radius:8px"><i class="bi bi-file-earmark-pdf me-1"></i> Single PDF{{ $edition !== 'weekly' ? ' (by email)' : '' }}</button></li>
  <li class="nav-item" role="presentation"><button type="button" class="nav-link" data-ep-tab="bulk" role="tab" aria-selected="false" style="border-radius:8px"><i class="bi bi-files me-1"></i> Bulk upload (many PDFs / folder){{ $edition !== 'weekly' ? ' (by email)' : '' }}</button></li>
</ul>

<div data-ep-panel="single">
  <div class="form-card" style="max-width:820px">
    <form action="{{ route('admin.digital-newspaper.store') }}" method="POST" enctype="multipart/form-data">
      @csrf
      <input type="hidden" name="edition" value="{{ $edition }}">
      @include('admin.digital-newspaper._form', ['paper' => null, 'edition' => $edition])
      <button type="submit" class="btn btn-primary" style="border-radius:8px;font-weight:600"><i class="bi bi-check-lg me-1"></i> Add Edition</button>
    </form>
  </div>
</div>

<div data-ep-panel="bulk" hidden>
  @include('admin.digital-newspaper._bulk', ['edition' => $edition])
</div>

<script>
// Tabs: Single PDF / Bulk upload (the open tab is remembered in the address: #bulk)
(function () {
  const tabs = document.querySelectorAll('[data-ep-tab]');
  function open(name) {
    tabs.forEach(t => { const on = t.dataset.epTab === name; t.classList.toggle('active', on); t.setAttribute('aria-selected', on); });
    document.querySelectorAll('[data-ep-panel]').forEach(p => { p.hidden = p.dataset.epPanel !== name; });
    history.replaceState(null, '', name === 'bulk' ? '#bulk' : location.pathname + location.search);
  }
  tabs.forEach(t => t.addEventListener('click', () => open(t.dataset.epTab)));
  if (location.hash === '#bulk') open('bulk');
})();
</script>
@endsection
