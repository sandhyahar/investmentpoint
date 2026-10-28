@extends('admin.layouts.app')

@section('page-title', 'Edit Media Article')

@section('content')
<div class="mb-3 d-flex gap-2 flex-wrap">
  <a href="{{ route('admin.media-articles.index') }}" class="btn btn-sm btn-outline-secondary" style="border-radius:8px;font-size:12px"><i class="bi bi-arrow-left me-1"></i> Back to Media Articles</a>
  @if($mediaArticle->status === 'active')
    <a href="{{ $mediaArticle->url }}" target="_blank" class="btn btn-sm btn-outline-primary" style="border-radius:8px;font-size:12px"><i class="bi bi-box-arrow-up-right me-1"></i> View on website</a>
  @endif
</div>

<div class="form-card" style="max-width:820px">
  <form action="{{ route('admin.media-articles.update', $mediaArticle) }}" method="POST" enctype="multipart/form-data">
    @csrf @method('PUT')
    @include('admin.media-articles._form', ['article' => $mediaArticle])
    <button type="submit" class="btn btn-primary" style="border-radius:8px;font-weight:600"><i class="bi bi-check-lg me-1"></i> Save Changes</button>
  </form>
  <form action="{{ route('admin.media-articles.destroy', $mediaArticle) }}" method="POST" class="mt-3" onsubmit="return confirm('Delete this media article?')">
    @csrf @method('DELETE')
    <button class="btn btn-sm btn-outline-danger" style="border-radius:8px"><i class="bi bi-trash me-1"></i> Delete Article</button>
  </form>
</div>
@endsection
