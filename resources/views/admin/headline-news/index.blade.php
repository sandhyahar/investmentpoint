{{-- Admin list for Latest News / Most Popular News. $items, $search, $routePrefix, $labels --}}
@extends('admin.layouts.app')

@section('page-title', $labels['title'])

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <p class="text-muted mb-0" style="font-size:13px">Total: {{ $items->total() }}</p>
  <a href="{{ route($routePrefix . '.create') }}" class="btn btn-primary btn-sm" style="border-radius:8px;font-weight:600"><i class="bi bi-plus-lg me-1"></i> Add {{ $labels['singular'] }}</a>
</div>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <form id="bulk-delete-form" action="{{ route($routePrefix . '.bulk-delete') }}" method="POST" data-bulk-form>
    @csrf
    <button type="submit" class="btn btn-sm btn-outline-danger" data-bulk-submit disabled><i class="bi bi-trash me-1"></i> Delete Selected</button>
  </form>
  <form method="GET" action="{{ route($routePrefix . '.index') }}" class="d-flex gap-2">
    <input type="search" name="q" value="{{ $search }}" class="form-control form-control-sm" placeholder="Search title…" aria-label="Search title">
    <button class="btn btn-sm btn-outline-secondary">Search</button>
  </form>
</div>

<div class="table-card">
  <table class="table">
    <thead>
      <tr>
        <th><input type="checkbox" data-select-all aria-label="Select all"></th>
        @if($labels['ranked'])<th>Position</th>@else<th>#</th>@endif
        <th>Image</th>
        <th>Title</th>
        <th>Source</th>
        <th>Status</th>
        <th>Published</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
      @forelse($items as $item)
      <tr>
        <td><input type="checkbox" name="ids[]" value="{{ $item->id }}" form="bulk-delete-form" data-select-item></td>
        <td>{{ $labels['ranked'] ? $item->sort_order : $item->id }}</td>
        <td><img src="{{ $item->image_url }}" class="table-thumb" alt=""></td>
        <td style="max-width:320px"><strong style="font-size:13px">{{ Str::limit($item->title, 70) }}</strong>@include('admin.partials.view-badge', ['views' => $item->views])</td>
        <td style="font-size:12px">{{ $item->source ?: '—' }}</td>
        <td><span class="{{ $item->status == 'active' ? 'badge-active' : 'badge-inactive' }}">{{ ucfirst($item->status) }}</span></td>
        <td style="font-size:12px;color:#66798d">{{ $item->display_date?->format('d M Y, h:i A') }}</td>
        <td class="text-nowrap">
          @if($item->status == 'active')
          <a href="{{ $item->url }}" target="_blank" class="btn btn-sm btn-outline-secondary" style="border-radius:6px;font-size:11px" title="View on site"><i class="bi bi-eye"></i></a>
          @endif
          <a href="{{ route($routePrefix . '.edit', $item->id) }}" class="btn btn-sm btn-outline-primary" style="border-radius:6px;font-size:11px" title="Edit"><i class="bi bi-pencil"></i></a>
          <form action="{{ route($routePrefix . '.destroy', $item->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this news?')">
            @csrf @method('DELETE')
            <button class="btn btn-sm btn-outline-danger" style="border-radius:6px;font-size:11px" title="Delete"><i class="bi bi-trash"></i></button>
          </form>
        </td>
      </tr>
      @empty
      <tr><td colspan="8" class="text-center text-muted py-4">No news found. <a href="{{ route($routePrefix . '.create') }}">Create one</a></td></tr>
      @endforelse
    </tbody>
  </table>
</div>

<div class="mt-3">{{ $items->links() }}</div>
@endsection
