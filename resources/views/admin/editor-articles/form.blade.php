{{-- Write / edit an Editor's View article. $article: a new (unsaved) or existing EditorArticle --}}
@extends('admin.layouts.app')

@section('page-title', $article->exists ? "Edit Editor's View Article" : "Write Editor's View Article")

@section('content')
<div class="mb-3 d-flex gap-2 flex-wrap">
  <a href="{{ route('admin.editor-articles.index') }}" class="btn btn-sm btn-outline-secondary" style="border-radius:8px;font-size:12px"><i class="bi bi-arrow-left me-1"></i> Back to Editor's View</a>
  @if($article->exists && $article->status === 'active')
  <a href="{{ $article->url }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-primary" style="border-radius:8px;font-size:12px"><i class="bi bi-eye me-1"></i> View on website</a>
  @endif
</div>

<div class="form-card" style="max-width:820px">
  <form action="{{ $article->exists ? route('admin.editor-articles.update', $article) : route('admin.editor-articles.store') }}" method="POST" enctype="multipart/form-data">
    @csrf
    @if($article->exists) @method('PUT') @endif

    <div class="d-flex align-items-center gap-2 mb-3 p-2" style="background:#f6f8fc;border:1px solid #e8edf2;border-radius:10px;font-size:13px">
      <img src="{{ asset('images/nikhil-bhatt.png') }}" alt="" style="width:36px;height:36px;border-radius:50%;object-fit:cover;object-position:top">
      <span>Shown on the website as written by <b>Nikhil Bhatt</b>, with the date, views and Share buttons.</span>
    </div>

    <div class="mb-3">
      <label class="form-label" for="title">Title *</label>
      <input type="text" class="form-control @error('title') is-invalid @enderror" id="title" name="title" value="{{ old('title', $article->title) }}" required maxlength="255">
      @error('title')<div class="invalid-feedback">{{ $message }}</div>@enderror
      @include('admin.partials.title-suggest', ['type' => 'article'])
      @include('admin.partials.auto-english', ['fields' => ['title' => 'text', 'summary' => 'text', 'content' => 'html']])
    </div>

    <div class="mb-3">
      <label class="form-label" for="summary">Short summary <small class="text-muted fw-normal">(optional)</small></label>
      <textarea class="form-control @error('summary') is-invalid @enderror" id="summary" name="summary" rows="2" maxlength="1000" placeholder="One or two lines shown on the article list and when the link is shared">{{ old('summary', $article->summary) }}</textarea>
      @error('summary')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="mb-3">
      <label class="form-label" for="content">Article *</label>
      <textarea class="form-control rich-editor @error('content') is-invalid @enderror" id="content" name="content" rows="18" data-required>{{ old('content', $article->content) }}</textarea>
      @error('content')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    </div>

    <div class="mb-3">
      <label class="form-label" for="image">Picture <small class="text-muted fw-normal">(optional · jpg, png, webp · max 4MB)</small></label>
      @if($article->image_url)
        <div class="mb-2 d-flex align-items-center gap-3">
          <img src="{{ $article->image_url }}" style="width:120px;height:75px;object-fit:cover;border-radius:8px" alt="Current picture">
          <label class="form-check-label" style="font-weight:500"><input type="checkbox" class="form-check-input me-1" name="remove_image" value="1"> Remove picture</label>
        </div>
      @endif
      <input type="file" data-ai-image="16:9" data-ai-prompt-from="title" class="form-control @error('image') is-invalid @enderror" id="image" name="image" accept="image/jpeg,image/png,image/webp">
      <small class="text-muted">Without a picture the site logo is shown on the list.</small>
      @error('image')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="row">
      <div class="col-md-6 mb-3">
        <label class="form-label" for="published_on">Date *</label>
        <input type="date" class="form-control @error('published_on') is-invalid @enderror" id="published_on" name="published_on" value="{{ old('published_on', $article->published_on?->format('Y-m-d')) }}" required>
        @error('published_on')<div class="invalid-feedback">{{ $message }}</div>@enderror
      </div>
      <div class="col-md-6 mb-3">
        <label class="form-label" for="status">Status *</label>
        <select class="form-select" id="status" name="status">
          <option value="active" @selected(old('status', $article->status) === 'active')>Active (shown on the website)</option>
          <option value="inactive" @selected(old('status', $article->status) === 'inactive')>Inactive (hidden)</option>
        </select>
      </div>
    </div>

    <button type="submit" class="btn btn-primary" style="border-radius:8px;font-weight:600"><i class="bi bi-check-lg me-1"></i> {{ $article->exists ? 'Save Changes' : 'Publish Article' }}</button>
  </form>
</div>
@endsection
