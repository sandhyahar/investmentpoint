@extends('admin.layouts.app')

@section('page-title', 'Legal Pages')

@section('content')

<div class="table-card">
  <table class="table">
    <thead>
      <tr>
        <th>Page</th>
        <th>Web address</th>
        <th>Text</th>
        <th>Last edited</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
      @foreach($pages as $page)
      <tr>
        <td><strong style="font-size:13px">{{ $page->info()['menu'] }}</strong></td>
        <td style="font-size:12px"><a href="{{ route($page->info()['route']) }}" target="_blank" rel="noopener">{{ parse_url(route($page->info()['route']), PHP_URL_PATH) }}</a></td>
        <td>
          @if($page->exists)
          <span class="badge-active">Edited</span>
          @else
          <span class="badge-inactive">Original</span>
          @endif
        </td>
        <td style="font-size:12px;color:#66798d">{{ $page->updated_at?->format('d M Y, h:i A') ?? '—' }}</td>
        <td>
          <a href="{{ route($page->info()['route']) }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-secondary" style="border-radius:6px;font-size:11px" title="View on site"><i class="bi bi-eye"></i></a>
          <a href="{{ route('admin.legal-pages.edit', $page->slug) }}" class="btn btn-sm btn-outline-primary" style="border-radius:6px;font-size:11px" title="Edit"><i class="bi bi-pencil"></i></a>
        </td>
      </tr>
      @endforeach
    </tbody>
  </table>
</div>
@endsection
