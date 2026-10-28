@extends('admin.layouts.app')

@section('page-title', 'Members')

@section('content')
@php($day = fn ($d) => \App\Models\SubscriptionRequest::day($d))
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <form method="GET" action="{{ route('admin.members.index') }}" class="d-flex gap-2">
    <input type="search" name="q" value="{{ $search }}" class="form-control form-control-sm" style="width:260px;border-radius:8px" placeholder="Search WhatsApp no., name, email" aria-label="Search members">
    <button class="btn btn-sm btn-primary" style="border-radius:8px;font-weight:600"><i class="bi bi-search"></i></button>
    @if($search !== '')<a href="{{ route('admin.members.index') }}" class="btn btn-sm btn-outline-secondary" style="border-radius:8px">Clear</a>@endif
  </form>
  <a href="{{ route('admin.members.create') }}" class="btn btn-primary btn-sm" style="border-radius:8px;font-weight:600"><i class="bi bi-plus-lg me-1"></i> Add Member</a>
</div>
<p class="text-muted" style="font-size:13px">Accounts that log in to the member dashboard. Total: {{ $members->total() }}</p>

<div class="table-card">
  <table class="table">
    <thead>
      <tr>
        <th>WhatsApp (login)</th>
        <th>Name</th>
        <th>Email</th>
        <th>Subscribed on</th>
        <th>Expires on</th>
        <th>Status</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
      @forelse($members as $member)
      @php($p = $periods[$member->id])
      @php($running = $p && $p->starts_at?->isPast() && $p->expires_at?->isFuture())
      <tr>
        <td><strong>{{ $member->phone ?: '-' }}</strong><div class="text-muted" style="font-size:11px">Joined {{ $day($member->created_at) }}</div></td>
        <td>{{ $member->name }}</td>
        <td style="font-size:13px">{{ $member->email ?: '-' }}</td>
        <td>{{ $p ? $day($p->starts_at) : '-' }}</td>
        <td>{{ $p ? $day($p->expires_at) : '-' }}</td>
        <td>
          @if($running)
            <span class="badge-active">Active · {{ (int) ceil(now()->diffInDays($p->expires_at, false)) }}d left</span>
          @elseif($p && $p->starts_at?->isFuture())
            <span class="badge-inactive">Starts later</span>
          @elseif($p)
            <span class="badge-inactive">Expired</span>
          @else
            <span class="badge-inactive">No subscription</span>
          @endif
        </td>
        <td class="text-nowrap">
          <a href="{{ route('admin.members.edit', $member) }}" class="btn btn-sm btn-outline-primary" style="border-radius:6px;font-size:11px" title="Edit"><i class="bi bi-pencil"></i></a>
          <form action="{{ route('admin.members.destroy', $member) }}" method="POST" class="d-inline" onsubmit="return confirm(@js('Delete member ' . $member->name . '? They will no longer be able to log in.'))">
            @csrf @method('DELETE')
            <button class="btn btn-sm btn-outline-danger" style="border-radius:6px;font-size:11px" title="Delete"><i class="bi bi-trash"></i></button>
          </form>
        </td>
      </tr>
      @empty
      <tr><td colspan="7" class="text-center text-muted py-4">No members found. <a href="{{ route('admin.members.create') }}">Add one</a></td></tr>
      @endforelse
    </tbody>
  </table>
</div>
<div class="mt-3">{{ $members->links() }}</div>
@endsection
