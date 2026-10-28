@extends('admin.layouts.app')

@section('page-title', 'Member Picks')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
  <form method="GET" action="{{ route('admin.member-picks.index') }}" class="d-flex gap-2 align-items-center flex-wrap">
    <input type="date" name="date" value="{{ request('date') }}" class="form-control form-control-sm" style="width:auto;border-radius:8px" aria-label="Date">
    <input type="search" name="q" value="{{ request('q') }}" class="form-control form-control-sm" style="width:200px;border-radius:8px" placeholder="Stock name" aria-label="Search stock">
    <button class="btn btn-sm btn-primary" style="border-radius:8px;font-weight:600"><i class="bi bi-search me-1"></i> Search</button>
    @if(request()->hasAny(['date', 'q']))
      <a href="{{ route('admin.member-picks.index') }}" class="btn btn-sm btn-outline-secondary" style="border-radius:8px">Clear</a>
    @endif
  </form>
  <a href="{{ route('admin.member-picks.create', ['date' => request('date', $today)]) }}" class="btn btn-primary btn-sm" style="border-radius:8px;font-weight:600"><i class="bi bi-plus-lg me-1"></i> Add Stocks</a>
</div>
<p class="text-muted" style="font-size:13px">The member page shows the latest date's stocks on top, and the 7 days before it under "Previous day data" (older days through Search by date). Stocks added for a future date appear on that day.</p>

<form id="bulk-delete-form" action="{{ route('admin.member-picks.bulk-delete') }}" method="POST" data-bulk-form class="mb-3">
  @csrf
  <button type="submit" class="btn btn-sm btn-outline-danger" data-bulk-submit disabled><i class="bi bi-trash me-1"></i> Delete Selected</button>
  <span class="text-muted ms-2" style="font-size:13px">Total: {{ $picks->total() }}</span>
</form>

<div class="table-card">
  <table class="table">
    <thead>
      <tr>
        <th><input type="checkbox" data-select-all aria-label="Select all stocks"></th>
        <th>Stock</th>
        <th>CMP</th>
        <th>Support</th>
        <th>Resistance</th>
        <th>Achieved</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
      @forelse($groups as $day => $dayPicks)
        @php($d = \Illuminate\Support\Carbon::parse($day))
        <tr style="background:#f4f6fd">
          <td colspan="7" style="font-weight:800;color:#141f55">
            {{ $d->format('l, d M Y') }}
            @if($day === $today)<span class="badge-active" style="font-size:10px;margin-left:6px">Today</span>@elseif($day > $today)<span class="badge-inactive" style="font-size:10px;margin-left:6px">Upcoming</span>@endif
            <span class="text-muted fw-normal" style="font-size:12px;margin-left:6px">{{ $dayPicks->count() }} stock(s)</span>
            <a href="{{ route('admin.member-picks.create', ['date' => $day]) }}" class="ms-2" style="font-size:12px;font-weight:600"><i class="bi bi-plus"></i> Add more</a>
          </td>
        </tr>
        @foreach($dayPicks as $pick)
        <tr>
          <td><input type="checkbox" name="ids[]" value="{{ $pick->id }}" form="bulk-delete-form" data-select-item></td>
          <td><strong>{{ $pick->stock_name }}</strong>@if($pick->hasDescription()) <i class="bi bi-card-text text-primary" title="Has a description"></i>@endif</td>
          <td class="fw-semibold">{{ $pick->level('cmp') ?? '-' }}</td>
          <td class="text-danger fw-semibold">{{ $pick->level('support') ?? '-' }}</td>
          <td class="text-primary fw-semibold">{{ $pick->level('resistance') ?? '-' }}</td>
          <td class="text-success fw-semibold">{{ $pick->level('achieved') ?? '-' }}</td>
          <td class="text-nowrap">
            <a href="{{ route('admin.member-picks.edit', $pick) }}" class="btn btn-sm btn-outline-primary" style="border-radius:6px;font-size:11px" title="Edit"><i class="bi bi-pencil"></i></a>
            <form action="{{ route('admin.member-picks.destroy', $pick) }}" method="POST" class="d-inline" onsubmit="return confirm(@js('Delete ' . $pick->stock_name . ' from ' . $d->format('d M Y') . '?'))">
              @csrf @method('DELETE')
              <button class="btn btn-sm btn-outline-danger" style="border-radius:6px;font-size:11px" title="Delete"><i class="bi bi-trash"></i></button>
            </form>
          </td>
        </tr>
        @endforeach
      @empty
        <tr><td colspan="7" class="text-center text-muted py-4">No stocks found. <a href="{{ route('admin.member-picks.create') }}">Add today's stocks</a></td></tr>
      @endforelse
    </tbody>
  </table>
</div>
<div class="mt-3">{{ $picks->links() }}</div>
@endsection
