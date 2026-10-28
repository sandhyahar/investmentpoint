@extends('admin.layouts.app')

@section('page-title', 'Market Trend')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
  <p class="text-muted mb-0" style="font-size:13px">Total: {{ $news->total() }}</p>
  <a href="{{ route('admin.news.create') }}" class="btn btn-primary btn-sm" style="border-radius:8px;font-weight:600"><i class="bi bi-plus-lg me-1"></i> Add News</a>
</div>
<form id="bulk-delete-form" action="{{ route('admin.news.bulk-delete') }}" method="POST" data-bulk-form class="mb-3">
  @csrf
  <button type="submit" class="btn btn-sm btn-outline-danger" data-bulk-submit disabled><i class="bi bi-trash me-1"></i> Delete Selected</button>
</form>

<div class="table-card">
  <table class="table">
    <thead>
      <tr>
        <th><input type="checkbox" data-select-all aria-label="Select all news"></th>
        <th>#</th>
        <th>Image</th>
        <th>Title</th>
        <th>Homepage Hero</th>
        <th>Status</th>
        <th>Created</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
      @forelse($news as $item)
      <tr>
        <td><input type="checkbox" name="ids[]" value="{{ $item->id }}" form="bulk-delete-form" data-select-item></td>
        <td>{{ $item->id }}</td>
        <td>
          @if($item->image)
          <img src="{{ Str::startsWith($item->image, ['http://', 'https://']) ? $item->image : asset('storage/' . $item->image) }}" class="table-thumb" alt="">
          @else
          <span class="text-muted" style="font-size:11px">No image</span>
          @endif
        </td>
        <td style="max-width:300px"><strong style="font-size:13px">{{ Str::limit($item->title, 60) }}</strong>@include('admin.partials.view-badge', ['views' => $item->views])@include('admin.partials.short-link')</td>
        <td>
          @if($item->is_featured)
          <span class="badge-active">Market News Hero</span>
          @else
          <span class="badge-inactive">Not Featured</span>
          @endif
        </td>
        <td><span class="{{ $item->status == 'active' ? 'badge-active' : 'badge-inactive' }}">{{ ucfirst($item->status) }}</span></td>
        <td style="font-size:12px;color:#66798d">{{ $item->created_at->format('d M Y') }}</td>
        <td>
          @if($item->status == 'active')
          <a href="{{ $item->url }}" target="_blank" class="btn btn-sm btn-outline-secondary" style="border-radius:6px;font-size:11px" title="View on site"><i class="bi bi-eye"></i></a>
          @include('partials.share-button', ['signed' => true, 'edit' => route('admin.share-link.update', ['news', $item->id]), 'shareName' => $item->share_name, 'autoName' => \App\Support\ShortLink::nameFor($item), 'url' => $item->share_url, 'title' => $item->title, 'class' => 'ip-share-admin', 'iconOnly' => true])
          @endif
          <a href="{{ route('admin.news.edit', $item) }}" class="btn btn-sm btn-outline-primary" style="border-radius:6px;font-size:11px"><i class="bi bi-pencil"></i></a>
          <form action="{{ route('admin.news.destroy', $item) }}" method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this news?')">
            @csrf @method('DELETE')
            <button class="btn btn-sm btn-outline-danger" style="border-radius:6px;font-size:11px"><i class="bi bi-trash"></i></button>
          </form>
        </td>
      </tr>
      @empty
      <tr><td colspan="8" class="text-center text-muted py-4">No news articles found. <a href="{{ route('admin.news.create') }}">Create one</a></td></tr>
      @endforelse
    </tbody>
  </table>
</div>

<div class="mt-3">{{ $news->links() }}</div>
@endsection
