@extends('admin.layouts.app')

@php
  $daily = $edition !== 'weekly';
  $label = \App\Models\DigitalNewspaper::EDITIONS[$edition] . ($daily ? ' E-Paper' : '');
@endphp
@section('page-title', $label)

@section('content')
{{-- The four papers: switch between them here too --}}
<div class="ep-admin-tabs mb-3">
  @foreach(\App\Models\DigitalNewspaper::EDITIONS as $key => $name)
    <a href="{{ route('admin.digital-newspaper.index', ['edition' => $key]) }}" @class(['active' => $key === $edition])>{{ $name }}</a>
  @endforeach
</div>
{{-- Daily editions on their way to Blogger, one at a time (App\Services\EpaperByEmail) --}}
@php $outbox = collect($edition === 'weekly' ? [] : \App\Services\EpaperByEmail::waiting($edition))->sortBy('queued_at'); @endphp
@if($outbox->isNotEmpty())
  @php
    $time = fn ($at) => \Illuminate\Support\Carbon::parse($at)->timezone('Asia/Kolkata')->format('h:i A');
    $limit = \App\Services\EpaperByEmail::DAILY_LIMIT;
    $full = \App\Services\EpaperByEmail::dailyLimitReached();
  @endphp
  <div class="alert alert-light border py-2" style="font-size:13px;border-radius:10px">
    <b><i class="bi bi-envelope-paper me-1"></i> Going to Blogger ({{ $outbox->where('state', '!=', 'failed')->count() }} left)</b>
    <span class="text-muted">· sent one by one, about a minute apart, at most {{ $limit }} a day ({{ \App\Services\EpaperByEmail::sentToday() }} of {{ $limit }} sent today, all papers); you can close this page</span>
    <ul class="mb-0 mt-1 ps-3">
      @foreach($outbox as $entry)
        <li>{{ $entry['title'] }} ·
          @if($entry['state'] === 'failed')<span class="text-danger">{{ $entry['error'] }}</span>
          @elseif($entry['state'] === 'sent' && !empty($entry['blocked_at']))<span class="text-warning">sent {{ $time($entry['sent_at']) }}, Google blocked the email; the next one goes now. If it is not on the blog in a few minutes, post it in Blogger and click "Get all editions from Blogger"</span>
          @elseif($entry['state'] === 'sent')<span class="text-primary">sent {{ $time($entry['sent_at']) }}, waiting for Blogger's post</span>
          @elseif($entry['retry_at'])<span class="text-warning">{{ $entry['error'] }}</span>
          @elseif($full)<span class="text-muted">in the queue · today's {{ $limit }} emails are sent, it goes tomorrow by itself</span>
          @else<span class="text-muted">in the queue</span>@endif
        </li>
      @endforeach
    </ul>
  </div>
@endif
<div class="d-flex justify-content-between align-items-center gap-2 flex-wrap mb-3">
  <p class="text-muted mb-0" style="font-size:13px">Total: {{ number_format($papers->total()) }}</p>
  <div class="d-flex gap-2 flex-wrap justify-content-end">
    <form action="{{ route('admin.digital-newspaper.index') }}" method="GET" class="d-flex gap-2">
      <input type="hidden" name="edition" value="{{ $edition }}">
      <input type="search" name="q" value="{{ $search }}" class="form-control form-control-sm" placeholder="Search title or date (18.08.2026)" style="border-radius:8px;min-width:240px">
      <button class="btn btn-sm btn-outline-secondary" style="border-radius:8px"><i class="bi bi-search"></i></button>
    </form>
    {{-- Two ways in: upload the PDF here (daily: sent to Blogger by email), or post it in Blogger and bring it in --}}
    <a href="{{ route('admin.digital-newspaper.create', ['edition' => $edition]) }}" class="btn btn-primary btn-sm" style="border-radius:8px;font-weight:600;white-space:nowrap"><i class="bi bi-plus-lg me-1"></i> Add {{ \App\Models\DigitalNewspaper::EDITIONS[$edition] }} Edition</a>
    {{-- Every post of this paper's blog that the website does not have yet, no copies (Admin\DigitalNewspaperController::fromBlogger) --}}
    <form action="{{ route('admin.digital-newspaper.from-blogger') }}" method="POST" class="m-0" onsubmit="const b = this.querySelector('button'); b.disabled = true; b.innerHTML = '<span class=\'spinner-border spinner-border-sm me-1\'></span> Getting editions from Blogger…';">
      @csrf <input type="hidden" name="edition" value="{{ $edition }}">
      <button class="btn btn-outline-primary btn-sm" style="border-radius:8px;font-weight:600;white-space:nowrap" title="Posted the edition in Blogger yourself? This copies every edition of the blog that is not here yet, without copies"><i class="bi bi-cloud-download me-1"></i> Get all editions from Blogger</button>
    </form>
    @if($edition !== 'weekly')
    @php $blogId = \App\Services\BloggerPublisher::blogs()[$edition]['id'] ?? null; @endphp
    <a href="{{ $blogId ? 'https://www.blogger.com/blog/posts/' . $blogId : config('epaper.editions.' . $edition . '.blog') }}" target="_blank" rel="noopener" class="btn btn-outline-secondary btn-sm" style="border-radius:8px;font-weight:600;white-space:nowrap"><i class="bi bi-box-arrow-up-right me-1"></i> Open Blogger</a>
    @endif
  </div>
</div>
<form id="bulk-delete-form" action="{{ route('admin.digital-newspaper.bulk-delete') }}" method="POST" data-bulk-form class="mb-3">
  @csrf
  <input type="hidden" name="edition" value="{{ $edition }}">
  <button type="submit" class="btn btn-sm btn-outline-danger" data-bulk-submit disabled><i class="bi bi-trash me-1"></i> Delete Selected</button>
</form>

<div class="table-card">
  <table class="table">
    <thead>
      <tr>
        <th><input type="checkbox" data-select-all aria-label="Select all editions"></th>
        <th>Cover</th>
        <th>Edition</th>
        <th>{{ $daily ? 'Date' : 'Week' }}</th>
        <th>Pages</th>
        <th>Status</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
      @forelse($papers as $paper)
      <tr>
        <td><input type="checkbox" name="ids[]" value="{{ $paper->id }}" form="bulk-delete-form" data-select-item></td>
        <td>@if($cover = $paper->coverUrl())<img src="{{ $cover }}" class="table-thumb" style="width:44px;height:60px;object-fit:cover;object-position:top" alt="" loading="lazy">@endif</td>
        <td style="max-width:380px"><strong style="font-size:13px">{{ $paper->title }}</strong>@include('admin.partials.view-badge', ['views' => $views[$paper->id] ?? 0])@include('admin.partials.blogger-badge', ['item' => $paper])</td>
        <td style="font-size:12px;color:#66798d;white-space:nowrap">{{ $paper->date_label }}</td>
        <td style="font-size:13px">{{ count($paper->pages) }}</td>
        <td>
          @if($paper->isScheduled())
            {{-- Waiting for its publish date and time (Weekly News Paper schedule) --}}
            <span class="badge-inactive" style="background:#fff6e0;color:#8a5a00" title="Goes live automatically"><i class="bi bi-clock-history me-1"></i>Scheduled</span>
            <div style="font-size:11px;color:#8a5a00;white-space:nowrap;margin-top:2px">{{ $paper->publish_at->copy()->timezone('Asia/Kolkata')->format('d M Y, h:i A') }}</div>
          @else
            <span class="{{ $paper->status === 'active' ? 'badge-active' : 'badge-inactive' }}">{{ ucfirst($paper->status) }}</span>
          @endif
        </td>
        <td style="white-space:nowrap">
          @if($paper->status === 'active' && !$paper->isScheduled())
          <a href="{{ $paper->url }}" target="_blank" class="btn btn-sm btn-outline-secondary" style="border-radius:6px;font-size:11px" title="View on site"><i class="bi bi-eye"></i></a>
          @endif
          <a href="{{ route('admin.digital-newspaper.edit', $paper) }}" class="btn btn-sm btn-outline-primary" style="border-radius:6px;font-size:11px" title="Edit"><i class="bi bi-pencil"></i></a>
          <form action="{{ route('admin.digital-newspaper.destroy', $paper) }}" method="POST" class="d-inline" onsubmit="return confirm('Delete this edition?')">
            @csrf @method('DELETE')
            <button class="btn btn-sm btn-outline-danger" style="border-radius:6px;font-size:11px" title="Delete"><i class="bi bi-trash"></i></button>
          </form>
        </td>
      </tr>
      @empty
      <tr><td colspan="7" class="text-center text-muted py-4">No editions found. <a href="{{ route('admin.digital-newspaper.create', ['edition' => $edition]) }}">Add one</a></td></tr>
      @endforelse
    </tbody>
  </table>
</div>

<div class="mt-3">{{ $papers->onEachSide(1)->links() }}</div>
@endsection

@section('extra-css')
<style>
.ep-admin-tabs{display:flex;flex-wrap:wrap;gap:6px}
.ep-admin-tabs a{font-size:13px;font-weight:600;color:#43546a;background:#fff;border:1px solid #e2e9f0;border-radius:20px;padding:6px 14px;text-decoration:none}
.ep-admin-tabs a:hover{border-color:#2b3f9e;color:#2b3f9e}
.ep-admin-tabs a.active{background:#2b3f9e;border-color:#2b3f9e;color:#fff}
</style>
@endsection
