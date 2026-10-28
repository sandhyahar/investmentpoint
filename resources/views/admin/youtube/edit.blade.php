@extends('admin.layouts.app')

@section('page-title', 'Edit YouTube Video')

@section('content')
<div class="mb-3">
  <a href="{{ route('admin.youtube.index') }}" class="btn btn-sm btn-outline-secondary" style="border-radius:8px;font-size:12px"><i class="bi bi-arrow-left me-1"></i> Back to Videos</a>
</div>

<div class="form-card" style="max-width:700px">
  <form action="{{ route('admin.youtube.update', $youtube) }}" method="POST">
    @csrf @method('PUT')
    <div class="mb-3">
      <label class="form-label" for="title">Video Title *</label>
      <input type="text" class="form-control @error('title') is-invalid @enderror" id="title" name="title" value="{{ old('title', $youtube->title) }}" required>
      @error('title')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="mb-3">
      <label class="form-label" for="video_url">YouTube Video URL *</label>
      <input type="url" class="form-control @error('video_url') is-invalid @enderror" id="video_url" name="video_url" value="{{ old('video_url', $youtube->video_url) }}" required>
      @error('video_url')<div class="invalid-feedback">{{ $message }}</div>@enderror
      <small class="text-muted">The thumbnail is taken from YouTube automatically.</small>
    </div>
    <div class="row">
      <div class="col-md-6 mb-3">
        <label class="form-label" for="published_date">Published Date *</label>
        <input type="date" class="form-control @error('published_date') is-invalid @enderror" id="published_date" name="published_date" value="{{ old('published_date', $youtube->published_date ? $youtube->published_date->format('Y-m-d') : '') }}" required>
        @error('published_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
      </div>
    </div>
    <button type="submit" class="btn btn-primary" style="border-radius:8px;font-weight:600"><i class="bi bi-check-lg me-1"></i> Update Video</button>
  </form>
</div>
@endsection
