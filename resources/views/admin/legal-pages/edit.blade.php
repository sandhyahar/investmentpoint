@extends('admin.layouts.app')

@section('page-title', 'Edit ' . $page->info()['menu'])

@section('content')
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
  <a href="{{ route('admin.legal-pages.index') }}" class="btn btn-sm btn-outline-secondary" style="border-radius:8px;font-size:12px"><i class="bi bi-arrow-left me-1"></i> Back to Legal Pages</a>
  <a href="{{ route($page->info()['route']) }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-primary" style="border-radius:8px;font-size:12px"><i class="bi bi-box-arrow-up-right me-1"></i> View on website</a>
</div>

<div class="form-card" style="max-width:1000px">
  <form action="{{ route('admin.legal-pages.update', $page->slug) }}" method="POST">
    @csrf @method('PUT')
    <div class="mb-3">
      <label class="form-label" for="title">Page title *</label>
      <input type="text" class="form-control @error('title') is-invalid @enderror" id="title" name="title" value="{{ old('title', $page->title) }}" maxlength="200" required>
      @error('title')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="mb-3">
      <label class="form-label" for="subtitle">Line under the title</label>
      <input type="text" class="form-control @error('subtitle') is-invalid @enderror" id="subtitle" name="subtitle" value="{{ old('subtitle', $page->subtitle) }}" maxlength="500">
      @error('subtitle')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="mb-3">
      <label class="form-label" for="meta">Highlights under the title</label>
      <textarea class="form-control @error('meta') is-invalid @enderror" id="meta" name="meta" rows="3" maxlength="1000">{{ old('meta', $page->meta) }}</textarea>
      <small class="text-muted">One per line, e.g. <code>📅 Effective Date: May 19, 2025</code>. The part after the colon is shown in bold.</small>
      @error('meta')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="mb-3">
      <label class="form-label" for="content">Page text *</label>
      <textarea class="form-control rich-editor @error('content') is-invalid @enderror" id="content" name="content" rows="24" required
        data-editor-css="{{ view('partials.legal-styles')->render() }}" data-editor-body-class="tc-doc">{{ old('content', $content) }}</textarea>
      <small class="text-muted">Each <b>Heading 3</b> becomes a section in the "Contents" list on the page. The coloured boxes keep their design - just change the text inside them.</small>
      @error('content')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    </div>
    <button type="submit" class="btn btn-primary" style="border-radius:8px;font-weight:600"><i class="bi bi-check-lg me-1"></i> Save page</button>
  </form>

  @if($page->exists)
  <form action="{{ route('admin.legal-pages.reset', $page->slug) }}" method="POST" class="mt-3" onsubmit="return confirm('Throw away your changes and go back to the original text of this page?')">
    @csrf @method('DELETE')
    <button class="btn btn-sm btn-outline-danger" style="border-radius:8px;font-size:12px"><i class="bi bi-arrow-counterclockwise me-1"></i> Restore original text</button>
  </form>
  @endif
</div>
@endsection
