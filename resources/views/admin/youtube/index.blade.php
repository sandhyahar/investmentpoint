@extends('admin.layouts.app')

@section('page-title', 'YouTube Videos')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
  <p class="text-muted mb-0" style="font-size:13px">Total: {{ $videos->total() }}</p>
  <a href="{{ route('admin.youtube.create') }}" class="btn btn-primary btn-sm" style="border-radius:8px;font-weight:600"><i class="bi bi-plus-lg me-1"></i> Add Video</a>
</div>
<form id="bulk-delete-form" action="{{ route('admin.youtube.bulk-delete') }}" method="POST" data-bulk-form class="mb-3">
  @csrf
  <button type="submit" class="btn btn-sm btn-outline-danger" data-bulk-submit disabled><i class="bi bi-trash me-1"></i> Delete Selected</button>
</form>

<div class="table-card">
  <table class="table">
    <thead>
      <tr>
        <th><input type="checkbox" data-select-all aria-label="Select all videos"></th>
        <th>#</th>
        <th>Thumbnail</th>
        <th>Title</th>
        <th>Published</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
      @forelse($videos as $video)
      <tr>
        <td><input type="checkbox" name="ids[]" value="{{ $video->id }}" form="bulk-delete-form" data-select-item></td>
        <td>{{ $video->id }}</td>
        <td>
          @if($video->video_thumbnail || $video->video_id)
          <img src="{{ $video->thumbnail_url }}" class="table-thumb" alt="">
          @else
          <span class="text-muted" style="font-size:11px">No thumbnail</span>
          @endif
        </td>
        <td style="max-width:250px">
          <strong style="font-size:13px">{{ Str::limit($video->title, 50) }}</strong>
          <br><a href="{{ $video->video_url }}" target="_blank" style="font-size:10px;color:#2b3f9e">{{ Str::limit($video->video_url, 40) }}</a>
        </td>
        <td style="font-size:12px;color:#66798d">{{ $video->published_date ? $video->published_date->format('d M Y') : '-' }}</td>
        <td>
          <a href="{{ route('admin.youtube.edit', $video) }}" class="btn btn-sm btn-outline-primary" style="border-radius:6px;font-size:11px"><i class="bi bi-pencil"></i></a>
          <form action="{{ route('admin.youtube.destroy', $video) }}" method="POST" class="d-inline" onsubmit="return confirm('Delete this video?')">
            @csrf @method('DELETE')
            <button class="btn btn-sm btn-outline-danger" style="border-radius:6px;font-size:11px"><i class="bi bi-trash"></i></button>
          </form>
        </td>
      </tr>
      @empty
      <tr><td colspan="6" class="text-center text-muted py-4">No YouTube videos found. <a href="{{ route('admin.youtube.create') }}">Create one</a></td></tr>
      @endforelse
    </tbody>
  </table>
</div>

<div class="mt-3">{{ $videos->links() }}</div>
@endsection
