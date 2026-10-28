@extends('admin.layouts.app')

@section('page-title', 'Edit Divya Bhaskar Column')

@section('content')
<div class="mb-3 d-flex gap-2 flex-wrap">
  <a href="{{ route('admin.divya-bhaskar.index') }}" class="btn btn-sm btn-outline-secondary" style="border-radius:8px;font-size:12px"><i class="bi bi-arrow-left me-1"></i> Back to Divya Bhaskar</a>
  <a href="{{ $article->link }}" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-primary" style="border-radius:8px;font-size:12px"><i class="bi bi-box-arrow-up-right me-1"></i> Open on Divya Bhaskar</a>
</div>

<div class="form-card" style="max-width:760px">
  <form action="{{ route('admin.divya-bhaskar.update', $article) }}" method="POST">
    @csrf @method('PUT')
    @include('admin.divya-bhaskar._form', ['article' => $article])
    <button type="submit" class="btn btn-primary" style="border-radius:8px;font-weight:600"><i class="bi bi-check-lg me-1"></i> Save Changes</button>
  </form>
  <form action="{{ route('admin.divya-bhaskar.destroy', $article) }}" method="POST" class="mt-3" onsubmit="return confirm('Delete this column?')">
    @csrf @method('DELETE')
    <button class="btn btn-sm btn-outline-danger" style="border-radius:8px"><i class="bi bi-trash me-1"></i> Delete Column</button>
  </form>
</div>
@endsection
