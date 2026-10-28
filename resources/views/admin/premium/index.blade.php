@extends('admin.layouts.app')
@section('page-title', 'Premium Content')
@section('content')
<div class="d-flex justify-content-between align-items-center mb-3"><p class="text-muted mb-0" style="font-size:13px">Total: {{ $premiumContents->total() }}</p><a href="{{ route('admin.premium.create') }}" class="btn btn-primary btn-sm" style="border-radius:8px;font-weight:600"><i class="bi bi-plus-lg me-1"></i> Add Content</a></div>
<form id="bulk-delete-form" action="{{ route('admin.premium.bulk-delete') }}" method="POST" data-bulk-form class="mb-3">@csrf<button type="submit" class="btn btn-sm btn-outline-danger" data-bulk-submit disabled><i class="bi bi-trash me-1"></i> Delete Selected</button></form>
<div class="table-card"><table class="table"><thead><tr><th><input type="checkbox" data-select-all aria-label="Select all premium content"></th><th>Badge</th><th>Title</th><th>Benefits</th><th>Status</th><th>Actions</th></tr></thead><tbody>
@forelse($premiumContents as $premium)<tr><td><input type="checkbox" name="ids[]" value="{{ $premium->id }}" form="bulk-delete-form" data-select-item></td><td>{{ $premium->badge }}</td><td><strong>{{ $premium->title }}</strong></td><td>{{ count($premium->benefits ?? []) }}</td><td><span class="{{ $premium->status === 'active' ? 'badge-active' : 'badge-inactive' }}">{{ ucfirst($premium->status) }}</span></td><td><a href="{{ route('admin.premium.edit', $premium) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a> <form action="{{ route('admin.premium.destroy', $premium) }}" method="POST" class="d-inline" onsubmit="return confirm('Delete this premium content?')">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button></form></td></tr>
@empty<tr><td colspan="5" class="text-center text-muted py-4">No premium content found.</td></tr>@endforelse
</tbody></table></div><div class="mt-3">{{ $premiumContents->links() }}</div>
@endsection
