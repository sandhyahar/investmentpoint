@extends('admin.layouts.app')

@section('page-title', 'Divya Bhaskar')

@section('content')
<div class="d-flex justify-content-between align-items-center gap-2 flex-wrap mb-3">
  <p class="text-muted mb-0" style="font-size:13px">Total: {{ number_format($articles->total()) }}</p>
  <div class="d-flex gap-2">
    <form action="{{ route('admin.divya-bhaskar.index') }}" method="GET" class="d-flex gap-2">
      <input type="search" name="q" value="{{ $search }}" class="form-control form-control-sm" placeholder="Search headline" style="border-radius:8px;min-width:220px">
      <button class="btn btn-sm btn-outline-secondary" style="border-radius:8px"><i class="bi bi-search"></i></button>
    </form>
    <a href="{{ route('admin.divya-bhaskar.create') }}" class="btn btn-primary btn-sm" style="border-radius:8px;font-weight:600;white-space:nowrap"><i class="bi bi-plus-lg me-1"></i> Add Column</a>
  </div>
</div>
<form id="bulk-delete-form" action="{{ route('admin.divya-bhaskar.bulk-delete') }}" method="POST" data-bulk-form class="mb-3">
  @csrf
  <button type="submit" class="btn btn-sm btn-outline-danger" data-bulk-submit disabled><i class="bi bi-trash me-1"></i> Delete Selected</button>
</form>

<div class="table-card">
  <table class="table">
    <thead>
      <tr>
        <th><input type="checkbox" data-select-all aria-label="Select all columns"></th>
        <th>Date</th>
        <th>Headline</th>
        <th>Status</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
      @forelse($articles as $article)
      <tr>
        <td><input type="checkbox" name="ids[]" value="{{ $article->id }}" form="bulk-delete-form" data-select-item></td>
        <td style="font-size:12px;color:#66798d;white-space:nowrap">{{ $article->published_on->format('d M Y') }}</td>
        <td style="max-width:520px"><span style="font-size:13px;font-weight:600">{{ Str::limit($article->headline, 140) }}</span><br><a href="{{ $article->link }}" target="_blank" rel="noopener noreferrer" style="font-size:11px;color:#2b3f9e">{{ $article->link }}</a></td>
        <td><span class="{{ $article->status === 'active' ? 'badge-active' : 'badge-inactive' }}">{{ ucfirst($article->status) }}</span></td>
        <td style="white-space:nowrap">
          <a href="{{ route('admin.divya-bhaskar.edit', $article) }}" class="btn btn-sm btn-outline-primary" style="border-radius:6px;font-size:11px"><i class="bi bi-pencil"></i></a>
          <form action="{{ route('admin.divya-bhaskar.destroy', $article) }}" method="POST" class="d-inline" onsubmit="return confirm('Delete this column?')">
            @csrf @method('DELETE')
            <button class="btn btn-sm btn-outline-danger" style="border-radius:6px;font-size:11px"><i class="bi bi-trash"></i></button>
          </form>
        </td>
      </tr>
      @empty
      <tr><td colspan="5" class="text-center text-muted py-4">No columns found. <a href="{{ route('admin.divya-bhaskar.create') }}">Add one</a></td></tr>
      @endforelse
    </tbody>
  </table>
</div>

<div class="mt-3">{{ $articles->onEachSide(1)->links() }}</div>
@endsection
