{{-- Shared fields for Add / Edit edition. $paper is null when adding; $edition: weekly | gujarati | hindi | english --}}
@php
  $daily = $edition !== 'weekly';
  // Daily papers: "INVESTMENT POINT - 01.10.2026", like the posts already on the blogs (owner, 27 Oct 2026)
  $placeholder = $daily ? 'INVESTMENT POINT - 01.10.2026' : 'INVESTMENT POINT WEEKLY - 19.09.2026 TO 25.09.2026';
@endphp
<div class="mb-3">
  <label class="form-label" for="title">Title *</label>
  <input type="text" class="form-control @error('title') is-invalid @enderror" id="title" name="title" value="{{ old('title', $paper?->title) }}" required maxlength="255" placeholder="{{ $placeholder }}">
  <small class="text-muted">{{ $daily ? 'Filled in from the date in the PDF\'s file name when you choose the PDF (you can change it).' : 'Filled in from the PDF\'s file name when you choose the PDF (you can change it).' }}</small>
  @error('title')<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>

<div class="row">
  @unless($daily)
  <div class="col-md-4 mb-3">
    <label class="form-label" for="week_from">Week From</label>
    <input type="date" class="form-control @error('week_from') is-invalid @enderror" id="week_from" name="week_from" value="{{ old('week_from', $paper?->week_from?->format('Y-m-d')) }}">
    @error('week_from')<div class="invalid-feedback">{{ $message }}</div>@enderror
  </div>
  <div class="col-md-4 mb-3">
    <label class="form-label" for="week_to">Week To</label>
    <input type="date" class="form-control @error('week_to') is-invalid @enderror" id="week_to" name="week_to" value="{{ old('week_to', $paper?->week_to?->format('Y-m-d')) }}">
    @error('week_to')<div class="invalid-feedback">{{ $message }}</div>@enderror
  </div>
  @else
  <div class="col-md-4 mb-3">
    <label class="form-label" for="published_on">Edition Date *</label>
    <input type="date" class="form-control @error('published_on') is-invalid @enderror" id="published_on" name="published_on" value="{{ old('published_on', $paper?->published_on?->format('Y-m-d') ?? now('Asia/Kolkata')->format('Y-m-d')) }}" required>
    @error('published_on')<div class="invalid-feedback">{{ $message }}</div>@enderror
  </div>
  @endunless
</div>

{{-- Publish later (every edition): on its own row under the week / edition date --}}
@include('admin.partials.schedule-fields', ['item' => $paper])

@if($paper && $paper->pages)
<div class="mb-2">
  <label class="form-label">Current Pages</label>
  <div class="d-flex flex-wrap gap-2">
    @foreach($paper->pageImages() as $n => $sheet)
      <a href="{{ $sheet['full'] }}" target="_blank" rel="noopener" style="position:relative;display:block">
        <img src="{{ $sheet['thumb'] }}" alt="Page {{ $n + 1 }}" style="width:80px;height:110px;object-fit:cover;object-position:top;border-radius:6px;border:1px solid #e3e7f2">
        <span style="position:absolute;left:4px;bottom:4px;background:rgba(20,31,85,.85);color:#fff;font-size:10px;font-weight:700;padding:1px 6px;border-radius:10px">{{ $n + 1 }}</span>
      </a>
    @endforeach
  </div>
</div>
@endif

{{-- A daily edition's pages are on Blogger: not replaced from here --}}
@if($paper && $edition !== 'weekly')
<p class="form-text mb-3">The pages come from the Blogger post. To change them, edit the post in Blogger.</p>
@else
@include('admin.partials.pdf-pages', ['pdfLabel' => 'Newspaper PDF', 'pdfRequired' => !$paper, 'pdfDailyTitle' => $daily, 'pdfHint' => $paper ? 'Choose a new PDF only to replace all the pages above. Leave it empty to keep them.' : 'Upload the edition\'s PDF: every page becomes a page image automatically, in order.'])
@endif

<div class="mb-3" style="max-width:360px">
  <label class="form-label" for="status">Status *</label>
  <select class="form-select" id="status" name="status">
    <option value="active" @selected(old('status', $paper?->status ?? 'active') === 'active')>Active (shown on the website)</option>
    <option value="inactive" @selected(old('status', $paper?->status) === 'inactive')>Inactive (hidden)</option>
  </select>
</div>
