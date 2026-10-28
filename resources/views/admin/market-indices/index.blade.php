@extends('admin.layouts.app')

@section('page-title', 'Market Indices')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
  <p class="text-muted mb-0" style="font-size:13px">Total: {{ $indices->total() }}</p>
  <a href="{{ route('admin.market-indices.create') }}" class="btn btn-primary btn-sm" style="border-radius:8px;font-weight:600"><i class="bi bi-plus-lg me-1"></i> Add Index</a>
</div>
<form id="bulk-delete-form" action="{{ route('admin.market-indices.bulk-delete') }}" method="POST" data-bulk-form class="mb-3">
  @csrf
  <button type="submit" class="btn btn-sm btn-outline-danger" data-bulk-submit disabled><i class="bi bi-trash me-1"></i> Delete Selected</button>
</form>

<div class="table-card">
  <table class="table">
    <thead>
      <tr>
        <th><input type="checkbox" data-select-all aria-label="Select all market indices"></th>
        <th>#</th>
        <th>Name</th>
        <th>Symbol</th>
        <th>Value</th>
        <th>Change %</th>
        <th>Direction</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
      @forelse($indices as $index)
      @php
        // Prefer the live price when this row is in the ticker and the site has fetched it
        $q = $live['m' . $index->id] ?? null;
        if ($q && ($q['symbol'] ?? null) === $index->symbol) {
            $index->value = $q['price'];
            $index->change_percent = abs($q['change_percent']);
            $index->direction = $q['change_percent'] < 0 ? 'down' : 'up';
        }
      @endphp
      <tr>
        <td><input type="checkbox" name="ids[]" value="{{ $index->id }}" form="bulk-delete-form" data-select-item></td>
        <td>{{ $index->id }}</td>
        <td><strong>{{ $index->name }}</strong>@if(in_array($index->id, $tickerIds)) <span class="badge-active" style="font-size:10px;margin-left:6px">In ticker</span>@endif</td>
        <td><code>{{ $index->symbol ?? '—' }}</code></td>
        <td>{{ number_format($index->value, 2) }}</td>
        <td>
          <span class="{{ $index->direction == 'up' ? 'text-success' : 'text-danger' }}" style="font-weight:700">
            {{ $index->direction == 'up' ? '▲' : '▼' }} {{ number_format($index->change_percent, 2) }}%
          </span>
        </td>
        <td><span class="{{ $index->direction == 'up' ? 'badge-active' : 'badge-inactive' }}">{{ ucfirst($index->direction) }}</span></td>
        <td>
          <a href="{{ route('admin.market-indices.edit', $index) }}" class="btn btn-sm btn-outline-primary" style="border-radius:6px;font-size:11px"><i class="bi bi-pencil"></i></a>
          <form action="{{ route('admin.market-indices.destroy', $index) }}" method="POST" class="d-inline" onsubmit="return confirm('Delete this index?')">
            @csrf @method('DELETE')
            <button class="btn btn-sm btn-outline-danger" style="border-radius:6px;font-size:11px"><i class="bi bi-trash"></i></button>
          </form>
        </td>
      </tr>
      @empty
      <tr><td colspan="8" class="text-center text-muted py-4">No market indices found. <a href="{{ route('admin.market-indices.create') }}">Create one</a></td></tr>
      @endforelse
    </tbody>
  </table>
</div>
<div class="mt-3">{{ $indices->links() }}</div>
@endsection
