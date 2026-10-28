@extends('admin.layouts.app')

@section('page-title', 'Disclaimer Questions')

@section('content')
<div class="alert alert-info d-flex gap-2 align-items-start" style="font-size:13px;border-radius:10px">
  <i class="bi bi-info-circle-fill"></i>
  <div>Questions readers send from the <b>"Ask a question"</b> form at the bottom of the
    <a href="{{ route('disclaimer') }}#ask" target="_blank" rel="noopener">Disclaimer page</a>
    (website footer → Disclaimer). Open one to read it, reply by phone or email, then delete it.</div>
</div>
<div class="d-flex justify-content-between align-items-center mb-3">
  <p class="text-muted mb-0" style="font-size:13px">Total: {{ $queries->total() }}</p>
</div>
<div class="table-card">
  <table class="table">
    <thead><tr><th>#</th><th>From</th><th>Question</th><th>Files</th><th>Received</th><th>Actions</th></tr></thead>
    <tbody>
      @forelse($queries as $q)
      <tr @if(!$q->read_at) style="background:#fffbeb" @endif>
        <td>{{ $q->id }}</td>
        <td><strong>{{ $q->name }}</strong>@if(!$q->read_at) <span class="badge-pending">New</span>@endif<br><small class="text-muted">{{ $q->contact }}</small></td>
        <td style="max-width:360px">{{ Str::limit($q->message, 90) }}</td>
        <td>@if($q->attachments)<i class="bi bi-paperclip"></i> {{ count($q->attachments) }}@else<span class="text-muted">—</span>@endif</td>
        <td style="font-size:12px;color:#66798d">{{ $q->created_at->format('d M Y, h:i A') }}</td>
        <td>
          <a href="{{ route('admin.queries.show', $q) }}" class="btn btn-sm btn-outline-primary" style="border-radius:6px;font-size:11px"><i class="bi bi-eye"></i> View</a>
          <form action="{{ route('admin.queries.destroy', $q) }}" method="POST" class="d-inline" onsubmit="return confirm('Delete this question and its files?')">
            @csrf @method('DELETE')
            <button class="btn btn-sm btn-outline-danger" style="border-radius:6px;font-size:11px"><i class="bi bi-trash"></i></button>
          </form>
        </td>
      </tr>
      @empty
      <tr><td colspan="6" class="text-center text-muted py-4">No questions yet. They appear here when a reader uses the "Ask a question" form on the Disclaimer page.</td></tr>
      @endforelse
    </tbody>
  </table>
</div>
<div class="mt-3">{{ $queries->links() }}</div>
@endsection

@section('extra-css')
<style>.badge-pending{background:#fef3c7;color:#92400e;font-weight:700;font-size:10px;padding:2px 8px;border-radius:20px;margin-left:4px}</style>
@endsection
