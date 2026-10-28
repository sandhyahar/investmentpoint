@extends('admin.layouts.app')

@section('page-title', 'Technical Analysis')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
  <p class="text-muted mb-0" style="font-size:13px">Total: {{ $analyses->total() }}</p>
  <a href="{{ route('admin.technical-analysis.create') }}" class="btn btn-primary btn-sm" style="border-radius:8px;font-weight:600"><i class="bi bi-plus-lg me-1"></i> Add Analysis</a>
</div>
<form id="bulk-delete-form" action="{{ route('admin.technical-analysis.bulk-delete') }}" method="POST" data-bulk-form class="mb-3">
  @csrf
  <button type="submit" class="btn btn-sm btn-outline-danger" data-bulk-submit disabled><i class="bi bi-trash me-1"></i> Delete Selected</button>
</form>

<div class="table-card">
  <table class="table">
    <thead>
      <tr>
        <th><input type="checkbox" data-select-all aria-label="Select all analysis"></th>
        <th>#</th>
        <th>Image</th>
        <th>Title</th>
        <th>Status</th>
        <th>Date</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
      @forelse($analyses as $item)
      <tr>
        <td><input type="checkbox" name="ids[]" value="{{ $item->id }}" form="bulk-delete-form" data-select-item></td>
        <td>{{ $item->id }}</td>
        <td>
          @if($item->image)
          <img src="{{ Str::startsWith($item->image, ['http://', 'https://']) ? $item->image : asset('storage/' . $item->image) }}" class="table-thumb" alt="">
          @else
          <span class="text-muted" style="font-size:11px">No image</span>
          @endif
        </td>
        <td style="max-width:300px"><strong style="font-size:13px">{{ Str::limit($item->title, 60) }}</strong>
          @if($item->cmp || $item->support || $item->resistance)
          <br><small class="text-muted">CMP {{ \App\Models\TechnicalAnalysis::formatLevel($item->cmp) ?? '—' }} · Support {{ \App\Models\TechnicalAnalysis::formatLevel($item->support) ?? '—' }} · Resistance {{ \App\Models\TechnicalAnalysis::formatLevel($item->resistance) ?? '—' }}</small>
          @endif
          @include('admin.partials.view-badge', ['views' => $views[$item->id] ?? 0])
          @include('admin.partials.short-link')
        </td>
        <td><span class="{{ $item->status == 'active' ? 'badge-active' : 'badge-inactive' }}">{{ ucfirst($item->status) }}</span></td>
        <td style="font-size:12px;color:#66798d">{{ $item->published_on?->format('d M Y') ?? '—' }}</td>
        <td>
          @if($item->status == 'active')
          <a href="{{ $item->url }}" target="_blank" class="btn btn-sm btn-outline-secondary" style="border-radius:6px;font-size:11px" title="View on site"><i class="bi bi-eye"></i></a>
          @include('partials.share-button', ['signed' => true, 'edit' => route('admin.share-link.update', ['analysis', $item->id]), 'shareName' => $item->share_name, 'autoName' => \App\Support\ShortLink::nameFor($item), 'url' => $item->share_url, 'title' => $item->title, 'class' => 'ip-share-admin', 'iconOnly' => true])
          @endif
          <a href="{{ route('admin.technical-analysis.edit', $item) }}" class="btn btn-sm btn-outline-primary" style="border-radius:6px;font-size:11px"><i class="bi bi-pencil"></i></a>
          <form action="{{ route('admin.technical-analysis.destroy', $item) }}" method="POST" class="d-inline" onsubmit="return confirm('Delete this analysis?')">
            @csrf @method('DELETE')
            <button class="btn btn-sm btn-outline-danger" style="border-radius:6px;font-size:11px"><i class="bi bi-trash"></i></button>
          </form>
        </td>
      </tr>
      @empty
      <tr><td colspan="7" class="text-center text-muted py-4">No technical analysis posts found. <a href="{{ route('admin.technical-analysis.create') }}">Create one</a></td></tr>
      @endforelse
    </tbody>
  </table>
</div>

<div class="mt-3">{{ $analyses->links() }}</div>
@endsection
