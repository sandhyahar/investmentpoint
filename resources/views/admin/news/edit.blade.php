@extends('admin.layouts.app')

@section('page-title', 'Edit News')

@section('content')
<div class="mb-3">
  <a href="{{ route('admin.news.index') }}" class="btn btn-sm btn-outline-secondary" style="border-radius:8px;font-size:12px"><i class="bi bi-arrow-left me-1"></i> Back to News</a>
</div>

<div class="form-card" style="max-width:700px">
  <form action="{{ route('admin.news.update', $news) }}" method="POST" enctype="multipart/form-data">
    @csrf @method('PUT')
    <div class="mb-3">
      <label class="form-label" for="title">Title *</label>
      <input type="text" class="form-control @error('title') is-invalid @enderror" id="title" name="title" value="{{ old('title', $news->title) }}" required>
      @error('title')<div class="invalid-feedback">{{ $message }}</div>@enderror
      @include('admin.partials.title-suggest', ['type' => 'news'])
      @include('admin.partials.auto-english', ['fields' => ['title' => 'text', 'content' => 'html']])
    </div>
    @include('admin.partials.share-name-field', ['type' => 'news', 'item' => $news])
    @include('admin.news.partials.ai-writer')
    <div class="mb-3">
      <label class="form-label" for="content">Full Article</label>
      <textarea class="form-control rich-editor @error('content') is-invalid @enderror" id="content" name="content" rows="14">{{ old('content', $news->content) }}</textarea>
      <small class="text-muted">Shown on the article page. Leave an empty line between paragraphs. Its first lines are also the short text on the home page and news cards.</small>
      @error('content')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="mb-3">
      <label class="form-label" for="image">Image (jpg, png, webp — max 2MB)</label>
      @if($news->image)
      <div class="mb-2"><img src="{{ Str::startsWith($news->image, ['http://', 'https://']) ? $news->image : asset('storage/' . $news->image) }}" style="width:120px;height:80px;object-fit:cover;border-radius:8px" alt="Current image"></div>
      @endif
      <input type="file" data-ai-image="16:9" data-ai-look="bull-bear" data-ai-prompt-from="title" class="form-control @error('image') is-invalid @enderror" id="image" name="image" accept="image/jpeg,image/png,image/webp">
      <small class="text-muted">Leave empty to keep the current image</small>
      @error('image')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="mb-3">
      <label class="form-label" for="status">Status *</label>
      <select class="form-select @error('status') is-invalid @enderror" id="status" name="status">
        <option value="active" {{ old('status', $news->status) == 'active' ? 'selected' : '' }}>Active</option>
        <option value="inactive" {{ old('status', $news->status) == 'inactive' ? 'selected' : '' }}>Inactive</option>
      </select>
      @error('status')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="mb-3 form-check">
      <input class="form-check-input @error('is_featured') is-invalid @enderror" type="checkbox" id="is_featured" name="is_featured" value="1" {{ old('is_featured', $news->is_featured) ? 'checked' : '' }}>
      <label class="form-check-label" for="is_featured">Set as homepage hero story</label>
      @error('is_featured')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    </div>
    @include('admin.press.partials.send-box', ['type' => 'news', 'article' => $news])
    <button type="submit" class="btn btn-primary" style="border-radius:8px;font-weight:600"><i class="bi bi-check-lg me-1"></i> Update News</button>
  </form>
</div>
@endsection
