@extends('admin.layouts.app')

@section('page-title', 'Newspaper Logos')

@section('content')
{{-- "Trusted by" on the home page: add, edit (logo, name, position), hide / show and delete newspapers --}}
<div class="d-flex flex-wrap gap-2 align-items-center mb-3">
  <a href="{{ route('admin.media-articles.index') }}" class="btn btn-sm btn-outline-secondary" style="border-radius:8px;font-size:12px"><i class="bi bi-arrow-left me-1"></i> Back to Media Articles</a>
  <a href="{{ route('home') }}#trusted-by" target="_blank" rel="noopener" class="btn btn-sm btn-outline-primary ms-auto" style="border-radius:8px;font-size:12px"><i class="bi bi-box-arrow-up-right me-1"></i> See it on the home page</a>
</div>

<p class="text-muted nl-intro">The newspapers in <b>"Trusted by"</b> on the home page. Papers with 10 or more Media Articles appear by themselves; you can add others, change any logo, name or position, hide a paper or delete it. A paper without its own logo shows the masthead of its newest clipping. Best logo: a <b>PNG with a transparent background</b>, wide (about 600 × 200 pixels).</p>

{{-- Add a newspaper --}}
<details class="nl-add mb-3" @if($errors->any() && old('_form') === 'add') open @endif>
  <summary><i class="bi bi-plus-lg"></i> Add newspaper</summary>
  <form action="{{ route('admin.media-articles.logos.store') }}" method="POST" enctype="multipart/form-data" class="nl-form">
    @csrf
    <input type="hidden" name="_form" value="add">
    <div class="nl-fields">
      <div>
        <label class="form-label" for="add_name">Newspaper name *</label>
        <input type="text" class="form-control @error('name') is-invalid @enderror" id="add_name" name="name" value="{{ old('_form') === 'add' ? old('name') : '' }}" maxlength="120" required placeholder="e.g. SANDESH">
        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
        <div class="form-text">The same name as in Media Articles links it to that paper's articles.</div>
      </div>
      <div>
        <label class="form-label" for="add_label">Name shown <span class="text-muted fw-normal">(optional)</span></label>
        <input type="text" class="form-control" id="add_label" name="display_name" value="{{ old('_form') === 'add' ? old('display_name') : '' }}" maxlength="120" placeholder="e.g. Sandesh">
      </div>
      <div>
        <label class="form-label" for="add_order">Position <span class="text-muted fw-normal">(optional)</span></label>
        <input type="number" class="form-control @error('sort_order') is-invalid @enderror" id="add_order" name="sort_order" min="1" max="999" value="{{ old('_form') === 'add' ? old('sort_order') : '' }}" placeholder="1 = first">
        @error('sort_order')<div class="invalid-feedback">{{ $message }}</div>@enderror
      </div>
      <div>
        <label class="form-label" for="add_logo">Logo</label>
        <input type="file" class="form-control @error('logo') is-invalid @enderror" id="add_logo" name="logo" accept="image/png,image/jpeg,image/webp" data-media-skip>
        @error('logo')<div class="invalid-feedback">{{ $message }}</div>@enderror
        <div class="form-text">PNG, JPG or WEBP. Needed for a paper with no Media Articles here.</div>
      </div>
      <div>
        <label class="form-label" for="add_link">Link <span class="text-muted fw-normal">(optional)</span></label>
        <input type="url" class="form-control @error('link') is-invalid @enderror" id="add_link" name="link" value="{{ old('_form') === 'add' ? old('link') : '' }}" maxlength="500" placeholder="https://www.youtube.com/...">
        @error('link')<div class="invalid-feedback">{{ $message }}</div>@enderror
        <div class="form-text">The logo opens this (in a new tab), e.g. a TV channel's YouTube page. Empty = the paper's Media Articles.</div>
      </div>
    </div>
    <button class="btn btn-primary" style="border-radius:8px;font-weight:600"><i class="bi bi-check-lg me-1"></i> Add to Trusted by</button>
  </form>
</details>

{{-- Search + which papers --}}
<div class="d-flex flex-wrap gap-2 align-items-center mb-3">
  <form method="GET" class="d-flex gap-2 nl-search">
    <input type="hidden" name="show" value="{{ $show }}">
    <input type="search" name="q" value="{{ $search }}" class="form-control form-control-sm" placeholder="Search a newspaper" aria-label="Search a newspaper">
    <button class="btn btn-sm btn-primary" style="border-radius:8px"><i class="bi bi-search"></i></button>
    @if($search !== '')<a href="{{ route('admin.media-articles.logos', ['show' => $show]) }}" class="btn btn-sm btn-outline-secondary" style="border-radius:8px">Clear</a>@endif
  </form>
  <div class="nl-tabs">
    @foreach(['trusted' => 'In Trusted by', 'hidden' => 'Hidden', 'all' => 'All newspapers'] as $key => $label)
      <a href="{{ route('admin.media-articles.logos', array_filter(['show' => $key, 'q' => $search ?: null])) }}" @class(['active' => $show === $key])>{{ $label }} <small>{{ $counts[$key] }}</small></a>
    @endforeach
  </div>
</div>

<div class="nl-grid">
  @forelse($papers as $paper)
  <div @class(['nl-card', 'is-hidden' => $paper['hidden']])>
    <div @class(['nl-preview', 'is-official' => $paper['official']])>
      @if($paper['logo'])<img src="{{ $paper['logo'] }}" alt="{{ $paper['label'] }}" loading="lazy">@else<b>{{ $paper['label'] }}</b>@endif
    </div>
    <div class="nl-body">
      <div class="nl-title">
        <strong>{{ $paper['label'] }}</strong>
        @if($paper['label'] !== $paper['name'])<small class="text-muted d-block">{{ $paper['name'] }}</small>@endif
      </div>
      <div class="nl-badges">
        @if($paper['hidden'])<span class="nl-badge grey"><i class="bi bi-eye-slash"></i> Hidden</span>
        @elseif($paper['on_home'])<span class="nl-badge green"><i class="bi bi-check-circle"></i> On the home page</span>
        @else<span class="nl-badge amber">Not shown (under 10 articles)</span>@endif
        @if($paper['manual'])<span class="nl-badge blue">Added by hand</span>@else<span class="nl-badge">{{ number_format($paper['total']) }} articles</span>@endif
        @if($paper['sort_order'])<span class="nl-badge">Position {{ $paper['sort_order'] }}</span>@endif
        <span class="nl-badge">{{ $paper['official'] ? 'Own logo' : 'Masthead' }}</span>
        @if($paper['link'])<a class="nl-badge blue" href="{{ $paper['link'] }}" target="_blank" rel="noopener" title="{{ $paper['link'] }}"><i class="bi bi-box-arrow-up-right"></i> Opens its link</a>@endif
      </div>

      <div class="nl-actions">
        <details class="nl-edit">
          <summary class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil me-1"></i> Edit</summary>
          <form action="{{ route('admin.media-articles.logos.update') }}" method="POST" enctype="multipart/form-data" class="nl-edit-form">
            @csrf @method('PUT')
            <input type="hidden" name="name" value="{{ $paper['name'] }}">
            <label class="form-label">Name shown</label>
            <input type="text" class="form-control form-control-sm" name="display_name" value="{{ $paper['label'] }}" maxlength="120">
            <label class="form-label">Position <span class="text-muted fw-normal">(empty = by number of articles)</span></label>
            <input type="number" class="form-control form-control-sm" name="sort_order" min="1" max="999" value="{{ $paper['sort_order'] }}" placeholder="1 = first">
            <label class="form-label">Link <span class="text-muted fw-normal">(empty = its Media Articles)</span></label>
            <input type="url" class="form-control form-control-sm" name="link" value="{{ $paper['link'] }}" maxlength="500" placeholder="https://www.youtube.com/...">
            <label class="form-label">{{ $paper['official'] ? 'Replace the logo' : 'Upload its logo' }}</label>
            <input type="file" class="form-control form-control-sm" name="logo" accept="image/png,image/jpeg,image/webp" data-media-skip>
            @if($paper['official'] && !$paper['manual'])
              <div class="form-check mt-1"><input class="form-check-input" type="checkbox" name="remove_logo" value="1" id="rm-{{ md5($paper['name']) }}"><label class="form-check-label" for="rm-{{ md5($paper['name']) }}" style="font-size:12.5px">Remove the logo (show the masthead)</label></div>
            @endif
            <button class="btn btn-sm btn-primary mt-2" style="border-radius:6px"><i class="bi bi-check-lg me-1"></i> Save</button>
          </form>
        </details>
        <form action="{{ route('admin.media-articles.logos.update') }}" method="POST" class="d-inline">
          @csrf @method('PUT')
          <input type="hidden" name="name" value="{{ $paper['name'] }}">
          <input type="hidden" name="is_hidden" value="{{ $paper['hidden'] ? 0 : 1 }}">
          <button class="btn btn-sm {{ $paper['hidden'] ? 'btn-outline-success' : 'btn-outline-secondary' }}"><i class="bi {{ $paper['hidden'] ? 'bi-eye' : 'bi-eye-slash' }} me-1"></i>{{ $paper['hidden'] ? 'Show again' : 'Hide' }}</button>
        </form>
        <form action="{{ route('admin.media-articles.logos.destroy') }}" method="POST" class="d-inline" onsubmit="return confirm(@js($paper['manual'] ? 'Delete ' . $paper['label'] . '?' : 'Remove ' . $paper['label'] . ' from Trusted by? Its Media Articles stay.'))">
          @csrf @method('DELETE')
          <input type="hidden" name="name" value="{{ $paper['name'] }}">
          <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash me-1"></i>Delete</button>
        </form>
      </div>
    </div>
  </div>
  @empty
    <p class="text-muted">No newspapers found.</p>
  @endforelse
</div>

<style>
.nl-intro{font-size:13px;max-width:900px}
.nl-add{background:#fff;border:1px solid #e3e8f2;border-radius:14px;padding:12px 16px;box-shadow:0 4px 14px rgba(20,31,85,.05)}
.nl-add>summary{cursor:pointer;font-weight:700;color:#2b3f9e;list-style:none;display:inline-flex;align-items:center;gap:6px}
.nl-add>summary::-webkit-details-marker{display:none}
.nl-form{margin-top:12px}
.nl-fields{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:12px;margin-bottom:12px}
.nl-search{flex:1 1 260px;max-width:380px}
.nl-tabs{display:flex;flex-wrap:wrap;gap:6px}
.nl-tabs a{display:inline-flex;align-items:center;gap:6px;padding:6px 12px;border-radius:20px;border:1px solid #d6def5;background:#fff;color:#2b3f9e;font-size:12.5px;font-weight:700;text-decoration:none}
.nl-tabs a small{font-size:11px;opacity:.75}
.nl-tabs a.active{background:#2b3f9e;border-color:#2b3f9e;color:#fff}
.nl-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:16px}
.nl-card{display:flex;flex-direction:column;background:#fff;border:1px solid #e3e8f2;border-radius:14px;overflow:hidden;box-shadow:0 4px 14px rgba(20,31,85,.05)}
.nl-card.is-hidden{opacity:.7}
.nl-preview{aspect-ratio:3/1;background:#fff;border-bottom:1px solid #eef1f7;display:flex;align-items:center;justify-content:center;overflow:hidden}
.nl-preview img{width:100%;height:100%;object-fit:cover;object-position:top center}
.nl-preview.is-official{padding:10px 18px}
.nl-preview.is-official img{object-fit:contain;object-position:center}
.nl-preview b{font-size:16px;color:#141f55;padding:0 10px;text-align:center}
.nl-body{display:flex;flex-direction:column;gap:8px;padding:12px 14px 14px;flex:1}
.nl-title strong{font-size:14px;color:#141f55}
.nl-badges{display:flex;flex-wrap:wrap;gap:5px}
.nl-badge{font-size:11px;font-weight:700;padding:3px 8px;border-radius:20px;background:#f1f4fa;color:#5f6b85}
.nl-badge.green{background:#e8f7ef;color:#0a7a4a}.nl-badge.amber{background:#fff6e0;color:#8a5a00}.nl-badge.grey{background:#eceff4;color:#5f6b85}.nl-badge.blue{background:#eef2ff;color:#2b3f9e}
.nl-actions{display:flex;flex-wrap:wrap;gap:6px;align-items:flex-start;margin-top:auto}
.nl-actions .btn{border-radius:6px;font-size:12px}
.nl-edit{flex:1 1 100%;order:3}
.nl-edit>summary{list-style:none;display:inline-block}
.nl-edit>summary::-webkit-details-marker{display:none}
.nl-edit[open]>summary{margin-bottom:8px}
.nl-edit-form{display:flex;flex-direction:column;gap:4px;background:#f8f9fd;border:1px solid #e3e8f2;border-radius:10px;padding:10px 12px}
.nl-edit-form .form-label{font-size:12px;font-weight:600;margin:4px 0 0}
.nl-actions>form{order:2}
.nl-actions>.nl-edit{order:1;flex:0 0 auto}
.nl-actions>.nl-edit[open]{flex:1 1 100%;order:4}
@media(max-width:575px){.nl-grid{grid-template-columns:1fr}.nl-search{max-width:none}}
</style>
@endsection
