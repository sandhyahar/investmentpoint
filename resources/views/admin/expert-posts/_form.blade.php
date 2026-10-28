{{-- Shared fields for Add / Edit Expert's View article. $post is null when adding. --}}
<div class="mb-3">
  <label class="form-label" for="title">Title *</label>
  <input type="text" class="form-control @error('title') is-invalid @enderror" id="title" name="title" value="{{ old('title', $post?->title) }}" required>
  @error('title')<div class="invalid-feedback">{{ $message }}</div>@enderror
  @include('admin.partials.title-suggest', ['type' => 'article'])
  @include('admin.partials.auto-english', ['fields' => ['title' => 'text', 'content' => 'html']])
</div>
<div class="row">
  <div class="col-md-6 mb-3">
    <label class="form-label" for="author">Written by</label>
    <input type="text" class="form-control @error('author') is-invalid @enderror" id="author" name="author" value="{{ old('author', $post?->author) }}" placeholder="e.g. Srishti Mendiratta" list="authorList">
    <datalist id="authorList">
      @foreach(\App\Models\ExpertPost::whereNotNull('author')->distinct()->orderBy('author')->pluck('author') as $name)<option value="{{ $name }}">@endforeach
    </datalist>
    @error('author')<div class="invalid-feedback">{{ $message }}</div>@enderror
    <small class="text-muted">Client or guest expert name. Leave empty to show "Investment Point".</small>
  </div>
  <div class="col-md-3 mb-3">
    <label class="form-label" for="published_on">Date *</label>
    <input type="date" class="form-control @error('published_on') is-invalid @enderror" id="published_on" name="published_on" value="{{ old('published_on', $post?->published_on?->format('Y-m-d') ?? now()->format('Y-m-d')) }}" required>
    @error('published_on')<div class="invalid-feedback">{{ $message }}</div>@enderror
  </div>
  <div class="col-md-3 mb-3">
    <label class="form-label" for="status">Status *</label>
    <select class="form-select" id="status" name="status">
      <option value="active" @selected(old('status', $post?->status ?? 'active') === 'active')>Active</option>
      <option value="inactive" @selected(old('status', $post?->status) === 'inactive')>Inactive (hidden)</option>
    </select>
  </div>
</div>

<div class="mb-3">
  <label class="form-label" for="image">Cover Image</label>
  @if($post?->image)
    <div class="mb-2 d-flex align-items-center gap-3">
      <img src="{{ $post->imageUrl() }}" alt="" style="height:90px;border-radius:8px;border:1px solid #e2e9f0">
      <label class="form-check-label" style="font-weight:500"><input type="checkbox" class="form-check-input me-1" name="remove_image" value="1"> Remove cover</label>
    </div>
  @endif
  <input type="file" class="form-control @error('image') is-invalid @enderror" id="image" name="image" accept="image/png,image/jpeg,image/webp">
  @error('image')<div class="invalid-feedback">{{ $message }}</div>@enderror
  <small class="text-muted">Shown on the article card. JPG, PNG or WEBP · max 4MB.</small>
</div>

<div class="mb-3">
  <label class="form-label" for="content">Article *</label>
  <textarea class="form-control rich-editor @error('content') is-invalid @enderror" id="content" name="content" rows="18">{{ old('content', $post?->content) }}</textarea>
  @error('content')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
</div>
