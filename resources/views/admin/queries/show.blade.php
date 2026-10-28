@extends('admin.layouts.app')

@section('page-title', 'Disclaimer Question #' . $query->id)

@section('content')
@php
  $isEmail = filter_var($query->contact, FILTER_VALIDATE_EMAIL);
  $phone = preg_replace('/[^0-9+]/', '', $query->contact);
@endphp
<a href="{{ route('admin.queries.index') }}" class="btn btn-sm btn-outline-secondary mb-3" style="border-radius:8px"><i class="bi bi-arrow-left"></i> All questions</a>

<div class="form-card" style="max-width:820px">
  <div class="d-flex flex-wrap justify-content-between gap-3 pb-3 mb-3" style="border-bottom:1px solid #e2e9f0">
    <div>
      <h2 style="font-size:18px;font-weight:800;color:#10253f;margin:0">{{ $query->name }}</h2>
      <div style="font-size:14px;margin-top:4px">
        @if($isEmail)
          <a href="mailto:{{ $query->contact }}"><i class="bi bi-envelope"></i> {{ $query->contact }}</a>
        @elseif(strlen($phone) >= 10)
          <a href="tel:{{ $phone }}"><i class="bi bi-telephone"></i> {{ $query->contact }}</a>
        @else
          {{ $query->contact }}
        @endif
      </div>
    </div>
    <small class="text-muted">Received {{ $query->created_at->format('d M Y, h:i A') }}</small>
  </div>

  <label class="d-block mb-2" style="font-weight:700">Question</label>
  <div style="white-space:pre-wrap;background:#f6f8fc;border:1px solid #e2e9f0;border-radius:10px;padding:14px 16px;font-size:14px;line-height:1.7">{{ $query->message }}</div>

  @if($query->attachments)
    <label class="d-block mt-4 mb-2" style="font-weight:700">Attached files</label>
    <div class="d-flex flex-column gap-2">
      @foreach($query->attachments as $i => $file)
        <a href="{{ route('admin.queries.download', [$query, $i]) }}" class="d-flex align-items-center gap-2" style="border:1px solid #e2e9f0;border-radius:8px;padding:10px 14px;text-decoration:none">
          <i class="bi bi-file-earmark-arrow-down" style="font-size:18px"></i>
          <span style="flex:1;word-break:break-all">{{ $file['name'] }}</span>
          <small class="text-muted">{{ number_format($file['size'] / 1024, 0) }} KB</small>
        </a>
      @endforeach
    </div>
  @endif

  <form action="{{ route('admin.queries.destroy', $query) }}" method="POST" class="mt-4" onsubmit="return confirm('Delete this question and its files?')">
    @csrf @method('DELETE')
    <button class="btn btn-sm btn-outline-danger" style="border-radius:8px"><i class="bi bi-trash me-1"></i> Delete query</button>
  </form>
</div>
@endsection
