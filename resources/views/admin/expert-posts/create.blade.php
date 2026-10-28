@extends('admin.layouts.app')

@section('page-title', "Add Expert's View Article")

@section('content')
<div class="mb-3">
  <a href="{{ route('admin.expert-posts.index') }}" class="btn btn-sm btn-outline-secondary" style="border-radius:8px;font-size:12px"><i class="bi bi-arrow-left me-1"></i> Back to Expert's View</a>
</div>

<div class="form-card" style="max-width:980px">
  <form action="{{ route('admin.expert-posts.store') }}" method="POST" enctype="multipart/form-data">
    @csrf
    @include('admin.expert-posts._form', ['post' => null])
    <button type="submit" class="btn btn-primary" style="border-radius:8px;font-weight:600"><i class="bi bi-send-fill me-1"></i> Post Article</button>
  </form>
</div>
@endsection
