@extends('admin.layouts.app')

@section('page-title', 'Add Media Article')

@section('content')
<div class="mb-3">
  <a href="{{ route('admin.media-articles.index') }}" class="btn btn-sm btn-outline-secondary" style="border-radius:8px;font-size:12px"><i class="bi bi-arrow-left me-1"></i> Back to Media Articles</a>
</div>

{{-- One PDF (the form below, unchanged) or many PDFs / a folder at once (Bulk upload) --}}
<ul class="nav nav-pills mb-3 gap-2" role="tablist" style="font-size:13.5px;font-weight:600">
  <li class="nav-item" role="presentation"><button type="button" class="nav-link active" data-ma-tab="single" role="tab" aria-selected="true" style="border-radius:8px"><i class="bi bi-file-earmark-pdf me-1"></i> Single PDF</button></li>
  <li class="nav-item" role="presentation"><button type="button" class="nav-link" data-ma-tab="bulk" role="tab" aria-selected="false" style="border-radius:8px"><i class="bi bi-files me-1"></i> Bulk upload (many PDFs / folder)</button></li>
</ul>

<div data-ma-panel="single">
  <div class="form-card" style="max-width:820px">
    <form action="{{ route('admin.media-articles.store') }}" method="POST" enctype="multipart/form-data">
      @csrf
      @include('admin.media-articles._form', ['article' => null])
      <button type="submit" class="btn btn-primary" style="border-radius:8px;font-weight:600"><i class="bi bi-check-lg me-1"></i> Add Article</button>
    </form>
  </div>
</div>

<div data-ma-panel="bulk" hidden>
  @include('admin.media-articles._bulk')
</div>

<script>
// Tabs: Single PDF / Bulk upload (the open tab is remembered in the address: #bulk)
(function () {
  const tabs = document.querySelectorAll('[data-ma-tab]');
  function open(name) {
    tabs.forEach(t => { const on = t.dataset.maTab === name; t.classList.toggle('active', on); t.setAttribute('aria-selected', on); });
    document.querySelectorAll('[data-ma-panel]').forEach(p => { p.hidden = p.dataset.maPanel !== name; });
    history.replaceState(null, '', name === 'bulk' ? '#bulk' : location.pathname + location.search);
  }
  tabs.forEach(t => t.addEventListener('click', () => open(t.dataset.maTab)));
  if (location.hash === '#bulk') open('bulk');
})();
</script>
@endsection
