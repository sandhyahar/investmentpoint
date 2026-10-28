@extends('admin.layouts.app')

@section('page-title', 'Media Articles')

@section('content')
<div class="d-flex justify-content-between align-items-center gap-2 flex-wrap mb-3">
  <p class="text-muted mb-0" style="font-size:13px">Total: {{ number_format($articles->total()) }}</p>
  <div class="d-flex gap-2">
    <form action="{{ route('admin.media-articles.index') }}" method="GET" class="d-flex gap-2">
      <input type="search" name="q" value="{{ $search }}" class="form-control form-control-sm" placeholder="Search title or newspaper" style="border-radius:8px;min-width:220px">
      <button class="btn btn-sm btn-outline-secondary" style="border-radius:8px"><i class="bi bi-search"></i></button>
    </form>
    <a href="{{ route('admin.media-articles.logos') }}" class="btn btn-outline-primary btn-sm" style="border-radius:8px;font-weight:600;white-space:nowrap"><i class="bi bi-patch-check me-1"></i> Newspaper Logos</a>
    <a href="{{ route('admin.media-articles.create') }}" class="btn btn-primary btn-sm" style="border-radius:8px;font-weight:600;white-space:nowrap"><i class="bi bi-plus-lg me-1"></i> Add Article</a>
  </div>
</div>
<form id="bulk-delete-form" action="{{ route('admin.media-articles.bulk-delete') }}" method="POST" data-bulk-form class="mb-3">
  @csrf
  <button type="submit" class="btn btn-sm btn-outline-danger" data-bulk-submit disabled><i class="bi bi-trash me-1"></i> Delete Selected</button>
</form>

<div class="table-card">
  <table class="table">
    <thead>
      <tr>
        <th><input type="checkbox" data-select-all aria-label="Select all media articles"></th>
        <th>Image</th>
        <th>Title</th>
        <th>Newspaper</th>
        <th>Date</th>
        <th>Status</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
      @forelse($articles as $article)
      <tr>
        <td><input type="checkbox" name="ids[]" value="{{ $article->id }}" form="bulk-delete-form" data-select-item></td>
        <td>@if($article->image)<img src="{{ $article->imageUrl('w120') }}" alt="" loading="lazy" style="width:46px;height:60px;object-fit:cover;object-position:top;border-radius:6px;border:1px solid #e2e9f0">@endif</td>
        <td style="max-width:320px"><strong style="font-size:13px">{{ Str::limit($article->title, 70) }}</strong>@if($article->column_name)<br><small class="text-muted">{{ $article->column_name }}</small>@endif
          @include('admin.partials.view-badge', ['views' => $views[$article->id] ?? 0])@include('admin.partials.blogger-badge', ['item' => $article])</td>
        <td style="font-size:13px">{{ $article->publication ?? '—' }}</td>
        <td style="font-size:12px;color:#66798d;white-space:nowrap">{{ $article->published_on?->format('d M Y') ?? '—' }}</td>
        <td>
          @if($article->isScheduled())
            {{-- Waiting for its schedule date and time --}}
            <span class="badge-inactive" style="background:#fff6e0;color:#8a5a00" title="Goes live automatically"><i class="bi bi-clock-history me-1"></i>Scheduled</span>
            <div style="font-size:11px;color:#8a5a00;white-space:nowrap;margin-top:2px">{{ $article->publish_at->copy()->timezone('Asia/Kolkata')->format('d M Y, h:i A') }}</div>
          @else
            <span class="{{ $article->status === 'active' ? 'badge-active' : 'badge-inactive' }}">{{ ucfirst($article->status) }}</span>
          @endif
        </td>
        <td style="white-space:nowrap">
          <a href="{{ route('admin.media-articles.edit', $article) }}" class="btn btn-sm btn-outline-primary" style="border-radius:6px;font-size:11px"><i class="bi bi-pencil"></i></a>
          <form action="{{ route('admin.media-articles.destroy', $article) }}" method="POST" class="d-inline" onsubmit="return confirm('Delete this media article?')">
            @csrf @method('DELETE')
            <button class="btn btn-sm btn-outline-danger" style="border-radius:6px;font-size:11px"><i class="bi bi-trash"></i></button>
          </form>
        </td>
      </tr>
      @empty
      <tr><td colspan="7" class="text-center text-muted py-4">No media articles found. <a href="{{ route('admin.media-articles.create') }}">Add one</a></td></tr>
      @endforelse
    </tbody>
  </table>
</div>

<div class="mt-3">{{ $articles->onEachSide(1)->links() }}</div>
@endsection
