{{-- Shared fields for Add / Edit media article. $article is null when adding. --}}
<div class="mb-3">
  <label class="form-label" for="title">Title *</label>
  <input type="text" class="form-control @error('title') is-invalid @enderror" id="title" name="title" value="{{ old('title', $article?->title) }}" placeholder="e.g. INVESTMENT POINT / PAGDANDI - 20.02.2026" required>
  @error('title')<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>
<div class="row">
  <div class="col-md-4 mb-3">
    <label class="form-label" for="column_name">Column</label>
    <input type="text" class="form-control @error('column_name') is-invalid @enderror" id="column_name" name="column_name" value="{{ old('column_name', $article?->column_name) }}" placeholder="e.g. INVESTMENT POINT">
    @error('column_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
  </div>
  <div class="col-md-4 mb-3">
    <label class="form-label" for="publication">Newspaper</label>
    <input type="text" class="form-control @error('publication') is-invalid @enderror" id="publication" name="publication" value="{{ old('publication', $article?->publication) }}" placeholder="e.g. PAGDANDI" list="publicationList">
    @error('publication')<div class="invalid-feedback">{{ $message }}</div>@enderror
    <datalist id="publicationList">
      @foreach(\App\Models\MediaArticle::whereNotNull('publication')->distinct()->orderBy('publication')->take(200)->pluck('publication') as $name)
        <option value="{{ $name }}">
      @endforeach
    </datalist>
  </div>
  <div class="col-md-4 mb-3">
    <label class="form-label" for="published_on">Published Date *</label>
    <input type="date" class="form-control @error('published_on') is-invalid @enderror" id="published_on" name="published_on" value="{{ old('published_on', $article?->published_on?->format('Y-m-d')) }}" required>
    @error('published_on')<div class="invalid-feedback">{{ $message }}</div>@enderror
  </div>
</div>

{{-- Publish later: on the website and Blogger from this date and time --}}
@include('admin.partials.schedule-fields', ['item' => $article])

{{-- The article's current pages (edit) --}}
@if($article && ($sheets = $article->pageImages()))
<div class="mb-3">
  <label class="form-label">Current pages ({{ count($sheets) }})</label>
  <div class="d-flex flex-wrap gap-2">
    @foreach($sheets as $n => $sheet)
      <a href="{{ $sheet['full'] }}" target="_blank" rel="noopener" title="Page {{ $n + 1 }}" style="position:relative;display:block">
        <img src="{{ $sheet['view'] }}" alt="Page {{ $n + 1 }}" style="width:80px;height:110px;object-fit:cover;object-position:top;border-radius:6px;border:1px solid #e3e7f2">
        <span style="position:absolute;left:4px;bottom:4px;background:rgba(20,31,85,.85);color:#fff;font-size:10px;font-weight:700;padding:1px 6px;border-radius:10px">{{ $n + 1 }}</span>
      </a>
    @endforeach
  </div>
</div>
@endif

@include('admin.partials.pdf-pages', ['pdfLabel' => 'Article PDF', 'pdfRequired' => !$article, 'pdfHint' => $article ? 'Choose a new PDF only to replace the pages above. Leave it empty to keep them.' : 'Upload the article\'s PDF: every page becomes a page image automatically, in order (page 1 is the cover in the lists).'])

<div class="mb-3">
  <label class="form-label" for="status">Status *</label>
  <select class="form-select" id="status" name="status">
    <option value="active" @selected(old('status', $article?->status ?? 'active') === 'active')>Active (shown on the website)</option>
    <option value="inactive" @selected(old('status', $article?->status) === 'inactive')>Inactive (hidden)</option>
  </select>
</div>
