@extends('admin.layouts.app')

@section('page-title', 'Edit Member')

@section('content')
<div class="mb-3">
  <a href="{{ route('admin.members.index') }}" class="btn btn-sm btn-outline-secondary" style="border-radius:8px;font-size:12px"><i class="bi bi-arrow-left me-1"></i> Back to Members</a>
</div>

<div class="form-card" style="max-width:760px">
  <form action="{{ route('admin.members.update', $member) }}" method="POST">
    @csrf @method('PUT')
    @include('admin.members._form')
    <button type="submit" class="btn btn-primary" style="border-radius:8px;font-weight:600"><i class="bi bi-check-lg me-1"></i> Update Member</button>
  </form>
</div>
@endsection
