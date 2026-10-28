@extends('admin.layouts.app')

@section('page-title', 'User Blogs')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
  <p class="text-muted mb-0" style="font-size:13px">Total: {{ $blogs->total() }}</p>
</div>
<div class="table-card">
  <table class="table">
    <thead><tr><th>#</th><th>Title</th><th>Author</th><th>Status</th><th>Submitted</th><th>Actions</th></tr></thead>
    <tbody>
      @forelse($blogs as $blog)
      <tr>
        <td>{{ $blog->id }}</td>
        <td style="max-width:300px"><strong>{{ Str::limit($blog->title, 60) }}</strong><br><small class="text-muted">/{{ $blog->slug }}</small>
          @include('admin.partials.view-badge', ['views' => $views[$blog->slug] ?? 0])</td>
        <td>
          <div class="d-flex align-items-center gap-2">
            <img src="{{ $blog->writer_photo_url }}" alt="" style="width:34px;height:34px;border-radius:50%;object-fit:{{ $blog->has_writer_photo ? 'cover' : 'contain' }};background:#fff;border:1px solid #e2e9f0;padding:{{ $blog->has_writer_photo ? '0' : '3px' }}">
            <span>{{ $blog->writer_name }}@if($blog->author_designation || $blog->author_reg_no)<br><small class="text-muted">{{ collect([$blog->author_designation, $blog->author_reg_no ? 'RA / IA / CA No. ' . $blog->author_reg_no : null])->filter()->implode(' · ') }}</small>@endif @if($email = $blog->author_email ?: $blog->author?->email)<br><small class="text-muted"><i class="bi bi-envelope"></i> <a href="mailto:{{ $email }}" class="text-muted">{{ $email }}</a></small>@endif</span>
          </div>
        </td>
        <td><span class="{{ $blog->status === 'approved' ? 'badge-active' : ($blog->status === 'rejected' ? 'badge-inactive' : 'badge-pending') }}">{{ ucfirst($blog->status) }}</span></td>
        <td style="font-size:12px;color:#66798d">{{ $blog->created_at->format('d M Y') }}</td>
        <td>
          <a href="{{ route('admin.blogs.edit', $blog) }}" class="btn btn-sm btn-outline-primary" style="border-radius:6px;font-size:11px"><i class="bi bi-pencil"></i> Review</a>
          <form action="{{ route('admin.blogs.destroy', $blog) }}" method="POST" class="d-inline" onsubmit="return confirm('Delete this blog?')">
            @csrf @method('DELETE')
            <button class="btn btn-sm btn-outline-danger" style="border-radius:6px;font-size:11px"><i class="bi bi-trash"></i></button>
          </form>
        </td>
      </tr>
      @empty
      <tr><td colspan="6" class="text-center text-muted py-4">No user blog submissions yet.</td></tr>
      @endforelse
    </tbody>
  </table>
</div>
<div class="mt-3">{{ $blogs->links() }}</div>
@endsection

@section('extra-css')
<style>.badge-pending{background:#fef3c7;color:#92400e;font-weight:700;font-size:11px;padding:4px 10px;border-radius:20px}</style>
@endsection
