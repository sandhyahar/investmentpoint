@extends('admin.layouts.app')

@php $label = \App\Models\DigitalNewspaper::EDITIONS[$edition]; @endphp
@section('page-title', 'Edit ' . $label . ' Edition')

@section('content')
<div class="mb-3 d-flex gap-2 flex-wrap">
  <a href="{{ route('admin.digital-newspaper.index', ['edition' => $edition]) }}" class="btn btn-sm btn-outline-secondary" style="border-radius:8px;font-size:12px"><i class="bi bi-arrow-left me-1"></i> Back to {{ $label }}{{ $edition === 'weekly' ? '' : ' E-Paper' }}</a>
  @if($paper->status === 'active')
  <a href="{{ $paper->url }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-primary" style="border-radius:8px;font-size:12px"><i class="bi bi-eye me-1"></i> View on website</a>
  @endif
</div>

@include('admin.digital-newspaper._straight')

<div class="form-card" style="max-width:820px">
  <form action="{{ route('admin.digital-newspaper.update', $paper) }}" method="POST" enctype="multipart/form-data">
    @csrf @method('PUT')
    @include('admin.digital-newspaper._form', ['paper' => $paper, 'edition' => $edition])
    <button type="submit" class="btn btn-primary" style="border-radius:8px;font-weight:600"><i class="bi bi-check-lg me-1"></i> Save Changes</button>
  </form>
  <form action="{{ route('admin.digital-newspaper.destroy', $paper) }}" method="POST" class="mt-3" onsubmit="return confirm('Delete this edition?')">
    @csrf @method('DELETE')
    <button class="btn btn-sm btn-outline-danger" style="border-radius:8px"><i class="bi bi-trash me-1"></i> Delete Edition</button>
  </form>
</div>
@endsection
