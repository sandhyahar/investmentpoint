@extends('admin.layouts.app')

@section('page-title', "Edit Expert's View Article")

@section('content')
<div class="mb-3 d-flex gap-2 flex-wrap">
  <a href="{{ route('admin.expert-posts.index') }}" class="btn btn-sm btn-outline-secondary" style="border-radius:8px;font-size:12px"><i class="bi bi-arrow-left me-1"></i> Back to Expert's View</a>
  @if($post->status === 'active')
    <a href="{{ $post->url }}" target="_blank" class="btn btn-sm btn-outline-primary" style="border-radius:8px;font-size:12px"><i class="bi bi-box-arrow-up-right me-1"></i> View on website</a>
  @endif
</div>

<div class="form-card" style="max-width:980px">
  <form action="{{ route('admin.expert-posts.update', $post) }}" method="POST" enctype="multipart/form-data">
    @csrf @method('PUT')
    @include('admin.expert-posts._form', ['post' => $post])
    <button type="submit" class="btn btn-primary" style="border-radius:8px;font-weight:600"><i class="bi bi-check-lg me-1"></i> Save Changes</button>
  </form>
  <form action="{{ route('admin.expert-posts.destroy', $post) }}" method="POST" class="mt-3" onsubmit="return confirm('Delete this article?')">
    @csrf @method('DELETE')
    <button class="btn btn-sm btn-outline-danger" style="border-radius:8px"><i class="bi bi-trash me-1"></i> Delete Article</button>
  </form>
</div>
@endsection
