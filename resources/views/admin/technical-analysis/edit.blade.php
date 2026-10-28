@extends('admin.layouts.app')

@section('page-title', 'Edit Technical Analysis')

@section('content')
<div class="mb-3">
  <a href="{{ route('admin.technical-analysis.index') }}" class="btn btn-sm btn-outline-secondary" style="border-radius:8px;font-size:12px"><i class="bi bi-arrow-left me-1"></i> Back to Analysis</a>
</div>

<div class="form-card" style="max-width:700px">
  <form action="{{ route('admin.technical-analysis.update', $technicalAnalysis) }}" method="POST" enctype="multipart/form-data">
    @csrf @method('PUT')
    <div class="mb-3">
      <label class="form-label" for="title">Title *</label>
      <input type="text" class="form-control @error('title') is-invalid @enderror" id="title" name="title" value="{{ old('title', $technicalAnalysis->title) }}" required>
      @error('title')<div class="invalid-feedback">{{ $message }}</div>@enderror
      @include('admin.partials.title-suggest', ['type' => 'analysis'])
      @include('admin.partials.auto-english', ['fields' => ['title' => 'text', 'content' => 'html']])
    </div>
    @include('admin.partials.share-name-field', ['type' => 'analysis', 'item' => $technicalAnalysis])
    <div class="row">
      {{-- The date shown on the card and the article: the admin's choice (empty = no date is shown) --}}
      <div class="col-sm-6 col-lg-3 mb-3">
        <label class="form-label" for="published_on">Date</label>
        <input type="date" class="form-control @error('published_on') is-invalid @enderror" id="published_on" name="published_on" value="{{ old('published_on', $technicalAnalysis->published_on?->format('Y-m-d')) }}">
        @error('published_on')<div class="invalid-feedback">{{ $message }}</div>@enderror
      </div>
      <div class="col-sm-6 col-lg-3 mb-3">
        <label class="form-label" for="cmp">CMP (₹)</label>
        <input type="text" inputmode="decimal" maxlength="50" class="form-control @error('cmp') is-invalid @enderror" id="cmp" name="cmp" value="{{ old('cmp', $technicalAnalysis->cmp) }}" placeholder="e.g. 640">
        @error('cmp')<div class="invalid-feedback">{{ $message }}</div>@enderror
      </div>
      <div class="col-sm-6 col-lg-3 mb-3">
        <label class="form-label" for="support">Support (₹)</label>
        <input type="text" inputmode="decimal" maxlength="50" class="form-control @error('support') is-invalid @enderror" id="support" name="support" value="{{ old('support', $technicalAnalysis->support) }}" placeholder="e.g. 616 or 610 - 620">
        @error('support')<div class="invalid-feedback">{{ $message }}</div>@enderror
      </div>
      <div class="col-sm-6 col-lg-3 mb-3">
        <label class="form-label" for="resistance">Resistance (₹)</label>
        <input type="text" inputmode="decimal" maxlength="50" class="form-control @error('resistance') is-invalid @enderror" id="resistance" name="resistance" value="{{ old('resistance', $technicalAnalysis->resistance) }}" placeholder="e.g. 660 or 655/670">
        @error('resistance')<div class="invalid-feedback">{{ $message }}</div>@enderror
      </div>
    </div>
    @include('admin.technical-analysis.partials.ai-writer')
    <div class="mb-3">
      <label class="form-label" for="content">Full Analysis</label>
      <textarea class="form-control rich-editor @error('content') is-invalid @enderror" id="content" name="content" rows="14">{{ old('content', $technicalAnalysis->content) }}</textarea>
      <small class="text-muted">Shown on the analysis page. Leave an empty line between paragraphs. Its first lines are also the short text on cards without CMP / support / resistance.</small>
      @error('content')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="mb-3">
      <label class="form-label" for="image">Image (jpg, png, webp — max 2MB)</label>
      @if($technicalAnalysis->image)
      <div class="mb-2"><img src="{{ Str::startsWith($technicalAnalysis->image, ['http://', 'https://']) ? $technicalAnalysis->image : asset('storage/' . $technicalAnalysis->image) }}" style="width:120px;height:80px;object-fit:cover;border-radius:8px" alt="Current"></div>
      @endif
      <input type="file" data-ai-image="16:9" data-ai-prompt-from="title" class="form-control @error('image') is-invalid @enderror" id="image" name="image" accept="image/jpeg,image/png,image/webp">
      <small class="text-muted">Leave empty to keep the current image</small>
      @error('image')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="mb-3">
      <label class="form-label" for="status">Status *</label>
      <select class="form-select @error('status') is-invalid @enderror" id="status" name="status">
        <option value="active" {{ old('status', $technicalAnalysis->status) == 'active' ? 'selected' : '' }}>Active</option>
        <option value="inactive" {{ old('status', $technicalAnalysis->status) == 'inactive' ? 'selected' : '' }}>Inactive</option>
      </select>
      @error('status')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    @include('admin.press.partials.send-box', ['type' => 'analysis', 'article' => $technicalAnalysis])
    <button type="submit" class="btn btn-primary" style="border-radius:8px;font-weight:600"><i class="bi bi-check-lg me-1"></i> Update Analysis</button>
  </form>
</div>
@endsection
