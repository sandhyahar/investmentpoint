@extends('admin.layouts.app')

@section('page-title', "Expert's View")

@section('content')
<div class="d-flex justify-content-between align-items-center gap-2 flex-wrap mb-3">
  <p class="text-muted mb-0" style="font-size:13px">Total: {{ number_format($posts->total()) }}</p>
  <div class="d-flex gap-2">
    <form action="{{ route('admin.expert-posts.index') }}" method="GET" class="d-flex gap-2">
      <input type="search" name="q" value="{{ $search }}" class="form-control form-control-sm" placeholder="Search title or author" style="border-radius:8px;min-width:220px">
      <button class="btn btn-sm btn-outline-secondary" style="border-radius:8px"><i class="bi bi-search"></i></button>
    </form>
    <a href="{{ route('admin.expert-posts.create') }}" class="btn btn-primary btn-sm" style="border-radius:8px;font-weight:600;white-space:nowrap"><i class="bi bi-plus-lg me-1"></i> Add Article</a>
  </div>
</div>
<form id="bulk-delete-form" action="{{ route('admin.expert-posts.bulk-delete') }}" method="POST" data-bulk-form class="mb-3">
  @csrf
  <button type="submit" class="btn btn-sm btn-outline-danger" data-bulk-submit disabled><i class="bi bi-trash me-1"></i> Delete Selected</button>
</form>

<div class="table-card">
  <table class="table">
    <thead>
      <tr>
        <th><input type="checkbox" data-select-all aria-label="Select all articles"></th>
        <th>Cover</th>
        <th>Title</th>
        <th>Written by</th>
        <th>Date</th>
        <th>Status</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
      @forelse($posts as $post)
      <tr>
        <td><input type="checkbox" name="ids[]" value="{{ $post->id }}" form="bulk-delete-form" data-select-item></td>
        <td>@if($post->image)<img src="{{ $post->imageUrl() }}" alt="" loading="lazy" style="width:70px;height:46px;object-fit:cover;border-radius:6px;border:1px solid #e2e9f0">@endif</td>
        <td style="max-width:420px"><strong style="font-size:13px">{{ Str::limit($post->title, 110) }}</strong>
          @include('admin.partials.view-badge', ['views' => $views[$post->id] ?? 0])</td>
        <td style="font-size:13px">{{ $post->author_name }}</td>
        <td style="font-size:12px;color:#66798d;white-space:nowrap">{{ $post->published_on->format('d M Y') }}</td>
        <td><span class="{{ $post->status === 'active' ? 'badge-active' : 'badge-inactive' }}">{{ ucfirst($post->status) }}</span></td>
        <td style="white-space:nowrap">
          <a href="{{ route('admin.expert-posts.edit', $post) }}" class="btn btn-sm btn-outline-primary" style="border-radius:6px;font-size:11px"><i class="bi bi-pencil"></i></a>
          <form action="{{ route('admin.expert-posts.destroy', $post) }}" method="POST" class="d-inline" onsubmit="return confirm('Delete this article?')">
            @csrf @method('DELETE')
            <button class="btn btn-sm btn-outline-danger" style="border-radius:6px;font-size:11px"><i class="bi bi-trash"></i></button>
          </form>
        </td>
      </tr>
      @empty
      <tr><td colspan="7" class="text-center text-muted py-4">No articles found. <a href="{{ route('admin.expert-posts.create') }}">Add one</a></td></tr>
      @endforelse
    </tbody>
  </table>
</div>

<div class="mt-3">{{ $posts->onEachSide(1)->links() }}</div>
@endsection
