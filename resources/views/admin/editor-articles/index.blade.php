@extends('admin.layouts.app')

@section('page-title', "Editor's View")

@section('content')
<p class="text-muted" style="font-size:13px">Nikhil Bhatt's own articles. They show on the website under <b>Editor's View</b> in the menu (after Media), newest first, with "✍️ Nikhil Bhatt", the date, views and Share buttons.</p>
<div class="d-flex justify-content-between align-items-center gap-2 flex-wrap mb-3">
  <p class="text-muted mb-0" style="font-size:13px">Total: {{ number_format($articles->total()) }}</p>
  <div class="d-flex gap-2">
    <form action="{{ route('admin.editor-articles.index') }}" method="GET" class="d-flex gap-2">
      <input type="search" name="q" value="{{ $search }}" class="form-control form-control-sm" placeholder="Search title" style="border-radius:8px;min-width:220px">
      <button class="btn btn-sm btn-outline-secondary" style="border-radius:8px"><i class="bi bi-search"></i></button>
    </form>
    <a href="{{ route('admin.editor-articles.create') }}" class="btn btn-primary btn-sm" style="border-radius:8px;font-weight:600;white-space:nowrap"><i class="bi bi-plus-lg me-1"></i> Write Article</a>
  </div>
</div>
<form id="bulk-delete-form" action="{{ route('admin.editor-articles.bulk-delete') }}" method="POST" data-bulk-form class="mb-3">
  @csrf
  <button type="submit" class="btn btn-sm btn-outline-danger" data-bulk-submit disabled><i class="bi bi-trash me-1"></i> Delete Selected</button>
</form>

<div class="table-card">
  <table class="table">
    <thead>
      <tr>
        <th><input type="checkbox" data-select-all aria-label="Select all articles"></th>
        <th>Picture</th>
        <th>Article</th>
        <th>Date</th>
        <th>Status</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
      @forelse($articles as $article)
      <tr>
        <td><input type="checkbox" name="ids[]" value="{{ $article->id }}" form="bulk-delete-form" data-select-item></td>
        <td>@if($article->image_url)<img src="{{ $article->image_url }}" class="table-thumb" alt="" loading="lazy">@else<span class="text-muted" style="font-size:11px">—</span>@endif</td>
        <td style="max-width:420px"><strong style="font-size:13px">{{ $article->title }}</strong>@include('admin.partials.view-badge', ['views' => $views[$article->id] ?? 0])</td>
        <td style="font-size:12px;color:#66798d;white-space:nowrap">{{ $article->published_on->format('d M Y') }}</td>
        <td><span class="{{ $article->status === 'active' ? 'badge-active' : 'badge-inactive' }}">{{ ucfirst($article->status) }}</span></td>
        <td style="white-space:nowrap">
          @if($article->status === 'active')
          <a href="{{ $article->url }}" target="_blank" class="btn btn-sm btn-outline-secondary" style="border-radius:6px;font-size:11px" title="View on site"><i class="bi bi-eye"></i></a>
          @endif
          <a href="{{ route('admin.editor-articles.edit', $article) }}" class="btn btn-sm btn-outline-primary" style="border-radius:6px;font-size:11px" title="Edit"><i class="bi bi-pencil"></i></a>
          <form action="{{ route('admin.editor-articles.destroy', $article) }}" method="POST" class="d-inline" onsubmit="return confirm('Delete this article?')">
            @csrf @method('DELETE')
            <button class="btn btn-sm btn-outline-danger" style="border-radius:6px;font-size:11px" title="Delete"><i class="bi bi-trash"></i></button>
          </form>
        </td>
      </tr>
      @empty
      <tr><td colspan="6" class="text-center text-muted py-4">No articles yet. <a href="{{ route('admin.editor-articles.create') }}">Write the first one</a></td></tr>
      @endforelse
    </tbody>
  </table>
</div>

<div class="mt-3">{{ $articles->onEachSide(1)->links() }}</div>
@endsection
