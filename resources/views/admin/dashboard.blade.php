@extends('admin.layouts.app')

@section('page-title', 'Dashboard')

@php
  $maxDay = max(1, $daily->max('views'));
  $scoreColor = $seo['score'] >= 75 ? '#0a9f63' : ($seo['score'] >= 50 ? '#d97706' : '#dc2626');
  $statusIcon = ['pass' => ['bi-check-circle-fill', '#0a9f63'], 'warn' => ['bi-exclamation-circle-fill', '#d97706'], 'fail' => ['bi-x-circle-fill', '#dc2626']];
  $checks = collect($seo['checks']);
  $toFix = $checks->where('status', '!=', 'pass');
  $passed = $checks->where('status', 'pass');
  $hour = (int) now()->timezone('Asia/Kolkata')->format('G');
  $greeting = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');
  $firstName = Str::before(trim(Auth::user()->name), ' ') ?: Auth::user()->name;
  $trafficIcons = ['today' => 'bi-sun', 'week' => 'bi-calendar-week', 'month' => 'bi-calendar3', 'all' => 'bi-infinity'];
  $deviceTotal = max(1, ($devices['mobile'] ?? 0) + ($devices['desktop'] ?? 0));
  $mobilePct = round(($devices['mobile'] ?? 0) / $deviceTotal * 100);
  // Only what this user's role allows (the Super Admin sees everything)
  $can = fn ($route) => Auth::user()->canOpenAdminRoute($route);
@endphp

@section('content')
{{-- WELCOME + QUICK ACTIONS --}}
<section class="db-hero">
  <div>
    <h2>{{ $greeting }}, {{ $firstName }} 👋</h2>
    <p>Here is what is happening on Investment Point today.</p>
  </div>
  <div class="db-hero-actions">
    @if($can('admin.news.create'))
    <a href="{{ route('admin.news.create') }}" class="db-qa gold"><i class="bi bi-plus-lg"></i> Add News</a>
    @endif
    @if($can('admin.editor-articles.create'))
    <a href="{{ route('admin.editor-articles.create') }}" class="db-qa"><i class="bi bi-pen"></i> Write Article</a>
    @endif
    @if($can('admin.technical-analysis.create'))
    <a href="{{ route('admin.technical-analysis.create') }}" class="db-qa"><i class="bi bi-bar-chart-line"></i> Add Analysis</a>
    @endif
    @if($can('admin.member-picks.create'))
    <a href="{{ route('admin.member-picks.create') }}" class="db-qa"><i class="bi bi-bullseye"></i> Add Member Picks</a>
    @endif
    @if($can('admin.youtube.create'))
    <a href="{{ route('admin.youtube.create') }}" class="db-qa"><i class="bi bi-youtube"></i> Add Video</a>
    @endif
    @if($can('admin.media.index'))
    <a href="{{ route('admin.media.index') }}" class="db-qa"><i class="bi bi-images"></i> Media Library</a>
    @endif
  </div>
</section>

{{-- NEEDS ATTENTION --}}
<div class="db-grid db-grid-4 mb-4">
  @if($can('admin.subscriptions.index'))
  <a class="db-card db-att" href="{{ route('admin.subscriptions.index', ['status' => 'new']) }}">
    <span class="db-ico" style="--c:#2b3f9e;--s:#eef1fb"><i class="bi bi-star-fill"></i></span>
    <div><b>{{ number_format($attention['subscriptions']) }}</b><span>New subscriptions</span></div>
    <i class="bi bi-arrow-right db-go"></i>
  </a>
  @endif
  @if($can('admin.subscriptions.index'))
  <a class="db-card db-att" href="{{ route('admin.subscriptions.index', ['payment' => 'active']) }}">
    <span class="db-ico" style="--c:#0a9f63;--s:#e6f7ef"><i class="bi bi-person-check-fill"></i></span>
    <div><b>{{ number_format($attention['activeSubscriptions']) }}</b><span>Active subscribers</span></div>
    <i class="bi bi-arrow-right db-go"></i>
  </a>
  @endif
  @if($can('admin.blogs.index'))
  <a class="db-card db-att" href="{{ route('admin.blogs.index') }}">
    <span class="db-ico" style="--c:#b45309;--s:#fff6e0"><i class="bi bi-journal-check"></i></span>
    <div><b>{{ number_format($attention['blogs']) }}</b><span>Blogs to approve</span></div>
    <i class="bi bi-arrow-right db-go"></i>
  </a>
  @endif
  @if($can('admin.queries.index'))
  <a class="db-card db-att" href="{{ route('admin.queries.index') }}">
    <span class="db-ico" style="--c:#c02f6b;--s:#fdeef4"><i class="bi bi-chat-dots-fill"></i></span>
    <div><b>{{ number_format($attention['queries']) }}</b><span>Unread questions</span></div>
    <i class="bi bi-arrow-right db-go"></i>
  </a>
  @endif
</div>

{{-- VISITORS --}}
<h3 class="db-section"><i class="bi bi-people"></i> Website visitors</h3>
<div class="db-grid db-grid-4 mb-4">
  @foreach($traffic as $key => $t)
    <div class="db-card db-stat @if($key === 'today') main @endif">
      <div class="db-stat-top"><span>{{ $t['label'] }}</span><i class="bi {{ $trafficIcons[$key] ?? 'bi-graph-up' }}"></i></div>
      <div class="db-stat-num">{{ number_format($t['views']) }}</div>
      <div class="db-stat-foot"><span>page views</span><b><i class="bi bi-person-fill"></i> {{ number_format($t['visitors']) }} {{ Str::plural('visitor', $t['visitors']) }}</b></div>
    </div>
  @endforeach
</div>

<div class="row g-4 mb-4">
  {{-- 14-day chart --}}
  <div class="col-xl-8">
    <div class="db-card h-100">
      <div class="db-card-head">
        <h3>Page views, last 14 days</h3>
        <div class="db-devices">
          <span><i class="bi bi-phone"></i> Mobile <b>{{ $mobilePct }}%</b></span>
          <span><i class="bi bi-laptop"></i> Desktop <b>{{ 100 - $mobilePct }}%</b></span>
        </div>
      </div>
      <div class="db-chart" role="img" aria-label="Page views per day for the last 14 days">
        @foreach($daily as $d)
          <div class="db-bar-col" title="{{ $d['date']->format('D, d M') }}: {{ $d['views'] }} views, {{ $d['visitors'] }} visitors">
            <span class="db-bar-val">{{ $d['views'] ?: '' }}</span>
            <div class="db-bar @if($d['date']->isToday()) today @endif" style="height:{{ max(2, round($d['views'] / $maxDay * 100)) }}%"></div>
            <span class="db-bar-day @if($d['date']->isToday()) today @endif">{{ $d['date']->format('d') }}<br>{{ $d['date']->format('M') }}</span>
          </div>
        @endforeach
      </div>
    </div>
  </div>

  {{-- SEO score --}}
  <div class="col-xl-4">
    <div class="db-card h-100 d-flex flex-column">
      <div class="db-card-head"><h3><i class="bi bi-google"></i> SEO health</h3></div>
      <div class="db-gauge" style="--p:{{ $seo['score'] }};--c:{{ $scoreColor }}">
        <div><b>{{ $seo['score'] }}</b><span>/ 100</span></div>
      </div>
      <p class="text-center mb-3" style="font-weight:700;color:{{ $scoreColor }}">{{ $seo['grade'] }}</p>
      <div class="db-seo-sum">
        <span><i class="bi bi-check-circle-fill" style="color:#0a9f63"></i> {{ $passed->count() }} passed</span>
        <span><i class="bi bi-exclamation-circle-fill" style="color:#d97706"></i> {{ $toFix->count() }} to improve</span>
      </div>
      <a href="#seo-checklist" class="btn btn-outline-primary btn-sm mt-auto">See the checklist</a>
    </div>
  </div>
</div>

<div class="row g-4 mb-4">
  {{-- Most visited pages --}}
  <div class="col-lg-6">
    <div class="db-card h-100">
      <div class="db-card-head"><h3><i class="bi bi-bar-chart-fill"></i> Most visited pages</h3></div>
      @forelse($pages as $p)
        <div class="db-row">
          <span>{{ \App\Models\PageView::ROUTE_LABELS[$p->route] ?? Str::of($p->route)->replace(['.', '-', '_'], ' ')->ucfirst() }}</span>
          <b>{{ number_format($p->views) }}</b>
          <div class="db-row-bar"><div style="width:{{ round($p->views / max(1, $pages->max('views')) * 100) }}%"></div></div>
        </div>
      @empty
        <p class="db-empty">No visits yet.</p>
      @endforelse
    </div>
  </div>
  <div class="col-lg-6 d-flex flex-column gap-4">
    {{-- Content library --}}
    <div class="db-card">
      <div class="db-card-head"><h3><i class="bi bi-collection"></i> Your content</h3></div>
      <div class="db-lib-grid">
        @if($can('admin.news.index'))
        <a class="db-lib" href="{{ route('admin.news.index') }}"><span class="db-ico sm" style="--c:#2b3f9e;--s:#eef1fb"><i class="bi bi-newspaper"></i></span><span><b>{{ number_format($totalNews) }}</b> News articles</span></a>
        @endif
        @if($can('admin.technical-analysis.index'))
        <a class="db-lib" href="{{ route('admin.technical-analysis.index') }}"><span class="db-ico sm" style="--c:#0a9f63;--s:#e6f7ef"><i class="bi bi-bar-chart-line-fill"></i></span><span><b>{{ number_format($totalTechnicalAnalysis) }}</b> Analysis posts</span></a>
        @endif
        @if($can('admin.youtube.index'))
        <a class="db-lib" href="{{ route('admin.youtube.index') }}"><span class="db-ico sm" style="--c:#dc3545;--s:#fdecee"><i class="bi bi-youtube"></i></span><span><b>{{ number_format($totalYoutubeVideos) }}</b> YouTube videos</span></a>
        @endif
      </div>
    </div>
    {{-- Referrers --}}
    <div class="db-card flex-grow-1">
      <div class="db-card-head"><h3><i class="bi bi-box-arrow-in-right"></i> Visitors came from</h3></div>
      @forelse($referrers as $r)
        <div class="db-row"><span class="db-host"><i class="bi bi-globe2"></i> {{ $r->referrer_host }}</span><b>{{ number_format($r->views) }}</b></div>
      @empty
        <p class="db-empty">No visits from other websites yet (Google, Facebook, WhatsApp…).</p>
      @endforelse
    </div>
  </div>
</div>

{{-- MOST READ --}}
<h3 class="db-section"><i class="bi bi-fire"></i> Most read</h3>
<div class="row g-4 mb-4">
  <div class="col-lg-4">
    <div class="db-card h-100">
      <div class="db-card-head"><h3>Expert's View</h3></div>
      @include('admin.partials.views-table', ['rows' => $topExperts, 'empty' => 'No Expert\'s View articles read yet.'])
    </div>
  </div>
  <div class="col-lg-4">
    <div class="db-card h-100">
      <div class="db-card-head"><h3>Media Articles</h3></div>
      @include('admin.partials.views-table', ['rows' => $topMedia, 'empty' => 'No media articles read yet.'])
    </div>
  </div>
  <div class="col-lg-4">
    <div class="db-card h-100">
      <div class="db-card-head"><h3>Blogs</h3></div>
      @include('admin.partials.views-table', ['rows' => $topBlogs, 'empty' => 'No blogs read yet.'])
    </div>
  </div>
</div>

{{-- SEO checklist: what to improve first, passed checks folded away --}}
<div class="db-card" id="seo-checklist">
  <div class="db-card-head">
    <h3><i class="bi bi-list-check"></i> SEO checklist</h3>
    <small class="text-muted">Checks of your own website. Google's ranking data will show in Google Search Console once the site is live.</small>
  </div>
  @if($toFix->isNotEmpty())
    <div class="db-checks">
      @foreach($toFix as $c)
        <div class="db-check">
          <i class="bi {{ $statusIcon[$c['status']][0] }}" style="color:{{ $statusIcon[$c['status']][1] }}"></i>
          <div>
            <b>{{ $c['label'] }}</b>
            <small>{{ $c['detail'] }}</small>
            @if($c['tip'])<small class="db-tip"><i class="bi bi-lightbulb"></i> {{ $c['tip'] }}</small>@endif
          </div>
        </div>
      @endforeach
    </div>
  @else
    <p class="db-empty"><i class="bi bi-trophy-fill text-warning"></i> Everything passed. Great work!</p>
  @endif
  @if($passed->isNotEmpty())
    <details class="db-passed">
      <summary><i class="bi bi-check-circle-fill"></i> {{ $passed->count() }} checks passed <i class="bi bi-chevron-down"></i></summary>
      <div class="db-checks">
        @foreach($passed as $c)
          <div class="db-check">
            <i class="bi {{ $statusIcon['pass'][0] }}" style="color:{{ $statusIcon['pass'][1] }}"></i>
            <div><b>{{ $c['label'] }}</b><small>{{ $c['detail'] }}</small></div>
          </div>
        @endforeach
      </div>
    </details>
  @endif
</div>
@endsection

@section('extra-css')
<style>
/* Grid of equal cards: 4 across on a computer, 2 on tablets / phones */
.db-grid{display:grid;gap:16px}
.db-grid-4{grid-template-columns:repeat(4,minmax(0,1fr))}
.db-card{background:var(--card);border:1px solid var(--line);border-radius:var(--radius);box-shadow:var(--shadow);padding:20px}
.db-card-head{display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:6px 14px;margin-bottom:16px}
.db-card-head h3,.db-section{font-size:15px;font-weight:700;color:var(--ink);margin:0;display:flex;align-items:center;gap:8px}
.db-card-head h3 i,.db-section i{color:var(--blue)}
.db-card-head small{font-size:12px;flex-basis:100%}
.db-section{margin:4px 0 14px}
.db-empty{color:var(--muted);font-size:13px;margin:0}

/* Welcome */
.db-hero{position:relative;overflow:hidden;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:18px;padding:26px 28px;margin-bottom:24px;border-radius:18px;color:#fff;
  background:radial-gradient(circle at 92% -20%,rgba(244,189,50,.35),transparent 42%),radial-gradient(circle at 0% 120%,rgba(74,99,216,.55),transparent 50%),linear-gradient(135deg,#0d1640,#22358f);box-shadow:0 14px 34px rgba(13,22,64,.22)}
.db-hero h2{font-size:23px;font-weight:800;margin:0 0 4px;letter-spacing:-.3px}
.db-hero p{margin:0;color:#c3cbec;font-size:14px}
.db-hero-actions{display:flex;flex-wrap:wrap;gap:8px}
.db-qa{display:inline-flex;align-items:center;gap:7px;height:38px;padding:0 14px;border-radius:10px;background:rgba(255,255,255,.1);border:1px solid rgba(255,255,255,.18);color:#fff;font-size:13px;font-weight:600;text-decoration:none;transition:background .15s,transform .15s}
.db-qa:hover{background:rgba(255,255,255,.2);color:#fff;transform:translateY(-1px)}
.db-qa.gold{background:var(--gold);border-color:var(--gold);color:var(--navy)}
.db-qa.gold:hover{background:#ffcc4d;color:var(--navy)}

/* Icon bubble */
.db-ico{width:46px;height:46px;border-radius:12px;background:var(--s);color:var(--c);display:inline-flex;align-items:center;justify-content:center;font-size:20px;flex-shrink:0}
.db-ico.sm{width:34px;height:34px;border-radius:10px;font-size:15px}

/* Needs-attention cards */
.db-att{display:flex;align-items:center;gap:14px;text-decoration:none;color:inherit;transition:transform .15s,box-shadow .15s,border-color .15s}
.db-att:hover{transform:translateY(-2px);box-shadow:var(--shadow-lg);border-color:#d5dcef;color:inherit}
.db-att b{display:block;font-size:24px;font-weight:800;color:var(--ink);line-height:1.1}
.db-att span:not(.db-ico){font-size:13px;color:var(--muted);font-weight:500}
.db-go{margin-left:auto;color:#b4bcd3;transition:transform .15s,color .15s}
.db-att:hover .db-go{color:var(--blue);transform:translateX(3px)}

/* Visitor stats */
.db-stat{padding:18px 20px}
.db-stat-top{display:flex;justify-content:space-between;align-items:center;font-size:12px;font-weight:600;color:var(--muted);text-transform:uppercase;letter-spacing:.6px}
.db-stat-top i{font-size:16px;color:#b4bcd3}
.db-stat-num{font-size:30px;font-weight:800;color:var(--ink);line-height:1.1;margin:10px 0 8px;letter-spacing:-.5px}
.db-stat-foot{display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:4px;font-size:12.5px;color:var(--muted)}
.db-stat-foot b{color:var(--blue);font-weight:600}
.db-stat.main{background:linear-gradient(135deg,#2b3f9e,#4a63d8);border-color:transparent;color:#fff;box-shadow:0 12px 26px rgba(43,63,158,.3)}
.db-stat.main .db-stat-top,.db-stat.main .db-stat-foot{color:#d6dcf7}
.db-stat.main .db-stat-top i{color:var(--gold)}
.db-stat.main .db-stat-num{color:#fff}
.db-stat.main .db-stat-foot b{color:var(--gold)}

/* 14-day bar chart */
.db-devices{display:flex;gap:14px;font-size:12.5px;color:var(--muted)}
.db-devices b{color:var(--ink)}
.db-chart{display:flex;align-items:flex-end;gap:8px;height:220px;padding-top:20px;border-bottom:1px solid var(--line);margin-bottom:38px;background:repeating-linear-gradient(to top,transparent 0,transparent 54px,var(--line-2) 54px,var(--line-2) 55px)}
.db-bar-col{flex:1;height:100%;display:flex;flex-direction:column;justify-content:flex-end;align-items:center;position:relative;min-width:0}
.db-bar{width:100%;max-width:30px;background:linear-gradient(180deg,#8fa0ec,#4a63d8);border-radius:7px 7px 2px 2px;transition:opacity .15s,transform .15s;transform-origin:bottom}
.db-bar.today{background:linear-gradient(180deg,#ffd76b,#f4bd32)}
.db-bar-col:hover .db-bar{opacity:.85;transform:scaleY(1.02)}
.db-bar-val{font-size:11px;font-weight:700;color:var(--ink);margin-bottom:4px}
.db-bar-day{position:absolute;bottom:-34px;font-size:10.5px;line-height:1.2;color:var(--muted);text-align:center}
.db-bar-day.today{color:var(--blue);font-weight:800}

/* SEO gauge */
.db-gauge{width:150px;height:150px;margin:2px auto 12px;border-radius:50%;background:conic-gradient(var(--c) calc(var(--p) * 1%),#eef1f7 0);display:flex;align-items:center;justify-content:center}
.db-gauge>div{width:120px;height:120px;border-radius:50%;background:#fff;display:flex;align-items:baseline;justify-content:center;padding-top:38px}
.db-gauge b{font-size:38px;font-weight:800;color:var(--ink);line-height:1}
.db-gauge span{font-size:13px;color:var(--muted);margin-left:3px}
.db-seo-sum{display:flex;justify-content:center;gap:16px;font-size:13px;color:var(--text);margin-bottom:16px}

/* Lists */
.db-row{display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;font-size:13.5px;padding:9px 0;border-bottom:1px solid var(--line-2)}
.db-row:last-child{border:0}
.db-row b{color:var(--ink);font-weight:700}
.db-row-bar{flex-basis:100%;height:6px;background:#eef1f7;border-radius:4px;overflow:hidden}
.db-row-bar div{height:100%;background:linear-gradient(90deg,#4a63d8,#2b3f9e);border-radius:4px}
.db-host{display:inline-flex;align-items:center;gap:8px;min-width:0;overflow-wrap:anywhere}
.db-host i{color:#9aa4bd;flex-shrink:0}
.db-lib-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px}
.db-lib{display:flex;align-items:center;gap:12px;padding:12px;border:1px solid var(--line-2);border-radius:12px;color:var(--muted);text-decoration:none;font-size:12.5px;font-weight:500;line-height:1.3;transition:border-color .15s,background .15s}
.db-lib b{display:block;color:var(--ink);font-size:20px;font-weight:800}
.db-lib:hover{border-color:#d5dcef;background:#fafbfe;color:var(--blue)}
@media(max-width:575px){.db-lib-grid{grid-template-columns:1fr}}
.db-views{width:100%;font-size:13px}
.db-views td{padding:9px 4px;border-bottom:1px solid var(--line-2);vertical-align:top}
.db-views tr:last-child td{border:0}
.db-views a{color:var(--ink);font-weight:600;text-decoration:none}
.db-views a:hover{color:var(--blue)}
.db-views small{color:var(--muted)}
.db-views .v{text-align:right;white-space:nowrap;font-weight:800;color:var(--blue)}

/* SEO checklist */
.db-checks{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:4px 28px}
.db-check{display:flex;gap:10px;align-items:flex-start;padding:11px 0;border-bottom:1px solid var(--line-2)}
.db-check>i{font-size:17px;line-height:1.2}
.db-check>div{min-width:0;overflow-wrap:anywhere}
.db-check b{display:block;font-size:13.5px;color:var(--ink);font-weight:600}
.db-check small{display:block;font-size:12.5px;color:var(--muted);margin-top:2px}
.db-tip{color:#8a5a00!important;background:var(--amber-soft);border-radius:8px;padding:6px 9px;margin-top:7px!important}
.db-passed{margin-top:14px}
.db-passed summary{display:inline-flex;align-items:center;gap:8px;cursor:pointer;list-style:none;font-size:13px;font-weight:600;color:var(--green);padding:6px 12px;border-radius:20px;background:var(--green-soft)}
.db-passed summary::-webkit-details-marker{display:none}
.db-passed summary .bi-chevron-down{font-size:11px;transition:transform .2s}
.db-passed[open] summary .bi-chevron-down{transform:rotate(180deg)}
.db-passed .db-checks{margin-top:10px}

@media(max-width:1199px){.db-grid-4{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media(max-width:768px){
  .db-checks{grid-template-columns:1fr}
  .db-chart{gap:3px;height:180px}
  .db-bar-val{font-size:9px}
  .db-bar-day{font-size:9px}
  .db-hero{padding:20px;border-radius:16px}
  .db-hero h2{font-size:19px}
}
@media(max-width:480px){
  .db-grid{gap:12px}
  .db-card{padding:16px}
  .db-att{flex-direction:column;align-items:flex-start;gap:10px}
  .db-att .db-go{display:none}
  .db-att b{font-size:22px}
  .db-stat-num{font-size:24px}
  .db-qa{flex:1 1 calc(50% - 8px);justify-content:center}
  .db-qa.gold{flex-basis:100%}
  .db-bar-val{display:none}
}
</style>
@endsection
