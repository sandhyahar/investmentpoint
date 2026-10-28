{{-- Admin -> News Inbox. $items, $tab (latest|popular), $search, $source, $sources, $aiReady --}}
@extends('admin.layouts.app')

@section('page-title', 'News Inbox')

@section('content')
@unless($aiReady)
<div class="alert alert-warning" style="font-size:13px;border-radius:10px">
  <b><i class="bi bi-stars"></i> AI writing is not set up yet.</b> You can still write articles yourself from these stories.
  To use "Write with AI", paste a Gemini API key in <a href="{{ route('admin.settings.edit') }}#gemini-ai">Site Settings → Gemini AI</a>.
</div>
@endunless
@if($aiReady && config('news.auto.enabled'))
<div class="alert alert-info" style="font-size:13px;border-radius:10px">
  <i class="bi bi-robot me-1"></i> <b>Auto-publish is on:</b> every 30 minutes the best stories are written by the free AI, fact-checked and published
  (up to {{ config('news.auto.business_per_day') }} Business News and {{ config('news.auto.popular_per_day') }} Most Popular a day). You don't need to click anything.
  A draft is <b>not</b> published if it has a number that isn't in the original story, names a newspaper, or fails the fact-check - it stays here for you.
</div>
@endif

<div class="d-flex justify-content-between align-items-center gap-2 flex-wrap mb-3">
  <ul class="nav nav-pills" style="font-size:13px;font-weight:600">
    <li class="nav-item"><a class="nav-link {{ $tab === 'latest' ? 'active' : '' }}" href="{{ route('admin.news-inbox.index', array_filter(['q' => $search ?: null, 'source' => $source ?: null])) }}"><i class="bi bi-clock-history me-1"></i> Latest</a></li>
    <li class="nav-item"><a class="nav-link {{ $tab === 'popular' ? 'active' : '' }}" href="{{ route('admin.news-inbox.index', array_filter(['tab' => 'popular', 'q' => $search ?: null, 'source' => $source ?: null])) }}"><i class="bi bi-fire me-1"></i> Popular</a></li>
  </ul>
  <div class="d-flex gap-2 flex-wrap">
    <form method="GET" action="{{ route('admin.news-inbox.index') }}" class="d-flex gap-2">
      @if($tab === 'popular')<input type="hidden" name="tab" value="popular">@endif
      <select name="source" class="form-select form-select-sm" style="border-radius:8px;width:auto" onchange="this.form.submit()">
        <option value="">All sources</option>
        @foreach($sources as $s)<option value="{{ $s }}" @selected($s === $source)>{{ $s }}</option>@endforeach
      </select>
      <input type="search" name="q" value="{{ $search }}" class="form-control form-control-sm" placeholder="Keyword, e.g. IPO, RBI, HDFC" style="border-radius:8px;min-width:220px">
      <button class="btn btn-sm btn-outline-secondary" style="border-radius:8px"><i class="bi bi-search"></i></button>
    </form>
    <form method="POST" action="{{ route('admin.news-inbox.refresh') }}">
      @csrf
      <button class="btn btn-sm btn-outline-primary" style="border-radius:8px;white-space:nowrap"><i class="bi bi-arrow-repeat me-1"></i> Check for new stories</button>
    </form>
    @if($aiReady)
    <form method="POST" action="{{ route('admin.news-inbox.auto-publish') }}" onsubmit="this.querySelector('button').disabled=true;this.querySelector('button').innerHTML='<span class=&quot;spinner-border spinner-border-sm me-1&quot;></span> Writing… (about 1 minute)'">
      @csrf
      <button class="btn btn-sm btn-primary" style="border-radius:8px;white-space:nowrap"><i class="bi bi-stars me-1"></i> Auto-publish now</button>
    </form>
    @endif
  </div>
</div>

<form id="bulk-dismiss-form" action="{{ route('admin.news-inbox.bulk-dismiss') }}" method="POST" data-bulk-form class="mb-3">
  @csrf
  <button type="submit" class="btn btn-sm btn-outline-danger" data-bulk-submit disabled><i class="bi bi-x-circle me-1"></i> Remove Selected</button>
</form>

<div class="table-card">
  <table class="table">
    <thead>
      <tr>
        <th><input type="checkbox" data-select-all aria-label="Select all stories"></th>
        <th>Story</th>
        <th>Source</th>
        <th>Time</th>
        <th>Write article</th>
      </tr>
    </thead>
    <tbody>
      @forelse($items as $item)
      <tr>
        <td><input type="checkbox" name="ids[]" value="{{ $item->id }}" form="bulk-dismiss-form" data-select-item></td>
        <td style="max-width:520px">
          <strong style="font-size:13px">{{ $item->title }}</strong>
          @if($item->coverage > 1)<span class="badge rounded-pill ms-1" style="background:#fdecec;color:#c42b30;font-size:10px" title="Publishers covering this story"><i class="bi bi-fire"></i> {{ $item->coverage }} sources</span>@endif
          @if($item->summary)<div class="text-muted" style="font-size:12px;margin-top:3px">{{ Str::limit($item->summary, 180) }}</div>@endif
          <a href="{{ $item->url }}" target="_blank" rel="noopener noreferrer" style="font-size:11px;color:#2b3f9e">Read original <i class="bi bi-box-arrow-up-right"></i></a>
        </td>
        <td style="font-size:12px;white-space:nowrap">{{ $item->source }}</td>
        <td style="font-size:12px;color:#66798d;white-space:nowrap" title="{{ $item->published_at->timezone('Asia/Kolkata')->format('d M Y, h:i A') }}">{{ $item->published_at->diffForHumans() }}</td>
        <td style="white-space:nowrap">
          <a href="{{ route('admin.latest-news.create', ['from' => $item->id]) }}" class="btn btn-sm btn-primary" style="border-radius:6px;font-size:11px"><i class="bi bi-lightning-charge-fill"></i> Business News</a>
          <a href="{{ route('admin.popular-news.create', ['from' => $item->id]) }}" class="btn btn-sm btn-outline-primary" style="border-radius:6px;font-size:11px"><i class="bi bi-fire"></i> Popular</a>
          <form action="{{ route('admin.news-inbox.dismiss', $item) }}" method="POST" class="d-inline">
            @csrf
            <button class="btn btn-sm btn-outline-secondary" style="border-radius:6px;font-size:11px" title="Remove from inbox"><i class="bi bi-x-lg"></i></button>
          </form>
        </td>
      </tr>
      @empty
      <tr><td colspan="5" class="text-center text-muted py-4">
        {{ $tab === 'popular' ? 'No story is being covered by several publishers right now.' : 'No stories found.' }}
      </td></tr>
      @endforelse
    </tbody>
  </table>
</div>

<div class="mt-3">{{ $items->onEachSide(1)->links() }}</div>
@endsection
