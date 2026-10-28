@extends('layouts.app')

@section('title', 'Divya Bhaskar Columns | Investment Point')
@section('meta_description', 'Nikhil Bhatt\'s stock market columns in the online edition of Divya Bhaskar, with every column listed by date and year.')

@section('content')
<main class="ep-page">
    <section class="ep-hero db-hero">
        <div class="container">
            <div class="ep-crumbs"><a href="{{ route('home') }}">Home</a><span>›</span>Media<span>›</span>Divya Bhaskar</div>
            <div class="ep-hero-row">
                <h1><span class="db-badge">દિવ્ય ભાસ્કર</span> Divya Bhaskar Columns
                    <small>Nikhil Bhatt's stock market columns in the online edition of Divya Bhaskar</small>
                </h1>
                <form class="ep-search" action="{{ route('divya-bhaskar.index') }}" method="GET" role="search">
                    @if($year)<input type="hidden" name="year" value="{{ $year }}">@endif
                    <input type="search" name="q" value="{{ $search }}" placeholder="Search headline or date, e.g. 21.02.2026" aria-label="Search columns">
                    <button type="submit"><i class="bi bi-search"></i></button>
                </form>
            </div>
            @if($years)
                <div class="ma-filter">
                    <a href="{{ route('divya-bhaskar.index', array_filter(['q' => $search ?: null])) }}" @class(['active' => !$year])>All years</a>
                    @foreach($years as $y => $count)
                        <a href="{{ route('divya-bhaskar.index', array_filter(['year' => $y, 'q' => $search ?: null])) }}" @class(['active' => $year === (int) $y])>{{ $y }} <small>{{ $count }}</small></a>
                    @endforeach
                </div>
            @endif
        </div>
    </section>

    <section class="container">
        <div class="ep-bar">
            <h2>
                @if($year) Columns from {{ $year }} @else Latest Columns @endif
                @if($search !== '') <small style="font-weight:600;color:var(--muted);font-size:14px">· “{{ $search }}”</small>@endif
            </h2>
            <span>
                @if($search !== '' || $year)<a href="{{ route('divya-bhaskar.index') }}"><i class="bi bi-x-circle"></i> Clear filters</a> · @endif
                {{ number_format($articles->total()) }} columns
                @if($articles->lastPage() > 1) · Page {{ $articles->currentPage() }} of {{ $articles->lastPage() }}@endif
            </span>
        </div>

        @if($articles->isEmpty())
            <div class="ep-empty">
                <i class="bi bi-search"></i>
                <h3>No columns found</h3>
                <p>Try another word, year or date, for example 21.02.2026.</p>
            </div>
        @else
            <div class="db-list">
                @foreach($articles as $article)
                    @php
                        [$series, $title] = $article->headlineParts();
                    @endphp
                    <a class="db-item" href="{{ $article->link }}" target="_blank" rel="noopener noreferrer">
                        <div class="db-date">
                            <b>{{ $article->published_on->format('d') }}</b>
                            <span>{{ $article->published_on->format('M') }}</span>
                            <small>{{ $article->published_on->format('Y') }}</small>
                        </div>
                        <div class="db-text">
                            @if($series)<span class="db-series">{{ $series }}</span>@endif
                            <h3 lang="gu">{{ $title }}</h3>
                            <span class="db-read">Read on Divya Bhaskar <i class="bi bi-box-arrow-up-right"></i></span>
                        </div>
                    </a>
                @endforeach
            </div>

            @if($articles->lastPage() > 1)
                @php
                    $page = $articles->currentPage();
                    $last = $articles->lastPage();
                    $from = max(1, $page - 2);
                    $to = min($last, $page + 2);
                @endphp
                <div class="ep-pager">
                    @if($articles->onFirstPage())<span class="disabled"><i class="bi bi-chevron-left"></i> Newer</span>@else<a href="{{ $articles->previousPageUrl() }}"><i class="bi bi-chevron-left"></i> Newer</a>@endif
                    @if($from > 1)<a href="{{ $articles->url(1) }}">1</a>@if($from > 2)<span class="dots">…</span>@endif @endif
                    @for($p = $from; $p <= $to; $p++)
                        @if($p === $page)<span class="current">{{ $p }}</span>@else<a href="{{ $articles->url($p) }}">{{ $p }}</a>@endif
                    @endfor
                    @if($to < $last)@if($to < $last - 1)<span class="dots">…</span>@endif<a href="{{ $articles->url($last) }}">{{ $last }}</a>@endif
                    @if($articles->hasMorePages())<a href="{{ $articles->nextPageUrl() }}">Older <i class="bi bi-chevron-right"></i></a>@else<span class="disabled">Older <i class="bi bi-chevron-right"></i></span>@endif
                </div>
            @endif
        @endif

        @include('media-articles.partials.about')
    </section>
</main>
@endsection

@section('extra-css')
@include('epaper.partials.styles')
@include('media-articles.partials.styles')
.db-hero h1{display:flex;flex-direction:column;align-items:flex-start;gap:8px}
.db-hero h1 small{margin-top:0}
.db-badge{display:inline-block;background:#e8431c;color:#fff;font-size:15px;font-weight:800;padding:5px 12px;border-radius:8px;letter-spacing:.3px}
.db-list{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px}
.db-item{display:flex;gap:16px;align-items:stretch;background:#fff;border:1px solid var(--line);border-left:4px solid #e8431c;border-radius:12px;box-shadow:var(--shadow);padding:16px 18px;color:var(--text);transition:transform .18s ease,box-shadow .18s ease}
.db-item:hover{transform:translateY(-3px);box-shadow:0 14px 30px rgba(27,42,107,.14);color:var(--text)}
.db-date{flex-shrink:0;width:62px;border-radius:10px;background:var(--blue2);display:flex;flex-direction:column;align-items:center;justify-content:center;padding:8px 0;align-self:flex-start}
.db-date b{font-size:24px;line-height:1;color:var(--navy)}
.db-date span{font-size:12px;font-weight:800;text-transform:uppercase;color:var(--blue);margin-top:2px}
.db-date small{font-size:11px;color:var(--muted)}
.db-text{display:flex;flex-direction:column;gap:6px;min-width:0}
.db-series{align-self:flex-start;font-size:11px;font-weight:800;color:#e8431c;background:#fdeee9;padding:3px 9px;border-radius:20px}
.db-text h3{font-size:16px;line-height:1.55;font-weight:700;color:var(--navy);margin:0}
.db-read{margin-top:auto;display:inline-flex;align-items:center;gap:6px;font-size:13px;font-weight:700;color:var(--blue)}
.db-item:hover .db-read{color:#e8431c}
@media(max-width:900px){.db-list{grid-template-columns:1fr}}
@media(max-width:600px){
  .db-item{padding:14px;gap:12px}
  .db-date{width:54px}
  .db-date b{font-size:20px}
  .db-text h3{font-size:15px}
}
@endsection
