@extends('admin.layouts.app')

@section('page-title', 'Review User Blog')

@section('content')
<div class="form-card" style="max-width:900px">
  <p class="text-muted" style="font-size:13px">Submitted {{ $blog->created_at->format('d M Y, h:i A') }} · Slug: {{ $blog->slug }}</p>
  <form action="{{ route('admin.blogs.update', $blog) }}" method="POST">
    @csrf @method('PUT')
    <div class="d-flex align-items-center gap-3 mb-3 p-3" style="background:#f6f8fc;border:1px solid #e8edf2;border-radius:10px">
      <img src="{{ $blog->writer_photo_url }}" alt="Writer photo" style="width:64px;height:64px;border-radius:50%;object-fit:{{ $blog->has_writer_photo ? 'cover' : 'contain' }};background:#fff;border:1px solid #e2e9f0;padding:{{ $blog->has_writer_photo ? '0' : '6px' }}">
      <div class="flex-grow-1">
        <label for="author_name">Writer name <span class="text-danger">*</span></label>
        <input class="form-control @error('author_name') is-invalid @enderror" id="author_name" name="author_name" value="{{ old('author_name', $blog->writer_name) }}" required minlength="2" maxlength="100">
        @error('author_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
        <div class="form-text">Shown on the website under the blog title.</div>
        <label for="author_email" class="mt-2">Writer email <small class="text-muted">(not shown on the website)</small></label>
        <input type="email" class="form-control @error('author_email') is-invalid @enderror" id="author_email" name="author_email" value="{{ old('author_email', $blog->author_email ?: $blog->author?->email) }}" maxlength="150" placeholder="Not given (blog sent before email was required)">
        @error('author_email')<div class="invalid-feedback">{{ $message }}</div>@enderror
        <div class="row g-2 mt-1">
          <div class="col-md-6">
            <label for="author_designation">Designation <small class="text-muted">(optional)</small></label>
            <input class="form-control @error('author_designation') is-invalid @enderror" id="author_designation" name="author_designation" value="{{ old('author_designation', $blog->author_designation) }}" maxlength="100" placeholder="e.g. SEBI Research Analyst">
            @error('author_designation')<div class="invalid-feedback">{{ $message }}</div>@enderror
          </div>
          <div class="col-md-6">
            <label for="author_reg_no">RA / IA / CA No. <small class="text-muted">(optional)</small></label>
            <input class="form-control @error('author_reg_no') is-invalid @enderror" id="author_reg_no" name="author_reg_no" value="{{ old('author_reg_no', $blog->author_reg_no) }}" maxlength="50" placeholder="e.g. INH000012345">
            @error('author_reg_no')<div class="invalid-feedback">{{ $message }}</div>@enderror
          </div>
        </div>
        <label for="author_website" class="mt-2">Website link <small class="text-muted">(optional · shown on the blog)</small></label>
        <input class="form-control @error('author_website') is-invalid @enderror" id="author_website" name="author_website" value="{{ old('author_website', $blog->author_website) }}" maxlength="255" placeholder="e.g. www.yourwebsite.com">
        @error('author_website')<div class="invalid-feedback">{{ $message }}</div>@enderror
        @if($blog->has_writer_photo)
        <div class="form-check mt-2">
          <input class="form-check-input" type="checkbox" id="remove_photo" name="remove_photo" value="1">
          <label class="form-check-label" for="remove_photo" style="font-weight:500">Remove profile picture (show company logo instead)</label>
        </div>
        @else
        <small class="text-muted">No profile picture uploaded, so the company logo is shown.</small>
        @endif
      </div>
    </div>
    <div class="mb-3"><label for="title">Title</label><input class="form-control" id="title" name="title" value="{{ old('title', $blog->title) }}" required></div>
    <div class="mb-3"><label for="content">Description</label><textarea class="form-control rich-editor" id="content" name="content" rows="16" required>{{ old('content', $blog->content) }}</textarea></div>
    <div class="mb-3"><label for="status">Approval Status</label><select class="form-select" id="status" name="status" required><option value="pending" @selected(old('status', $blog->status) === 'pending')>Pending</option><option value="approved" @selected(old('status', $blog->status) === 'approved')>Approved - Publish</option><option value="rejected" @selected(old('status', $blog->status) === 'rejected')>Rejected</option></select>
      @if($blog->status !== 'approved')<div class="form-text"><i class="bi bi-envelope"></i> Approving publishes the blog and emails it automatically to the writer's email above.</div>@endif
    </div>
    <button class="btn btn-primary" type="submit">Save Review</button>
    <a class="btn btn-light" href="{{ route('admin.blogs.index') }}">Cancel</a>
  </form>
</div>
@endsection
