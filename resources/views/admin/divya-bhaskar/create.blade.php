@extends('admin.layouts.app')

@section('page-title', 'Add Divya Bhaskar Column')

@section('content')
<div class="mb-3">
  <a href="{{ route('admin.divya-bhaskar.index') }}" class="btn btn-sm btn-outline-secondary" style="border-radius:8px;font-size:12px"><i class="bi bi-arrow-left me-1"></i> Back to Divya Bhaskar</a>
</div>

<div class="form-card" style="max-width:760px">
  <form action="{{ route('admin.divya-bhaskar.store') }}" method="POST">
    @csrf
    @include('admin.divya-bhaskar._form', ['article' => null])
    <button type="submit" class="btn btn-primary" style="border-radius:8px;font-weight:600"><i class="bi bi-check-lg me-1"></i> Add Column</button>
  </form>
</div>
@endsection
