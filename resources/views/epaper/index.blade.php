@extends('layouts.app')

@section('title', $edition['label'] . ' E-Paper | Investment Point')
@section('meta_description', 'Read the ' . $edition['label'] . ' e-paper of Investment Point: daily and weekly market trend editions by Nikhil Bhatt, free to read online.')

@section('content')
<main class="ep-page">
    <section class="ep-hero">
        <div class="container">
            <div class="ep-crumbs"><a href="{{ route('home') }}">Home</a><span>›</span>E-Paper<span>›</span>{{ $edition['label'] }}</div>
            <div class="ep-hero-row">
                <h1>{{ $edition['title'] }}<small>Investment Point · {{ $edition['label'] }} E-Paper</small></h1>
                <form class="ep-search" action="{{ route('epaper.index', $language) }}" method="GET" role="search">
                    <input type="search" name="q" value="{{ $search }}" placeholder="Search by date, e.g. 18.08.2026" aria-label="Search editions">
                    <button type="submit"><i class="bi bi-search"></i></button>
                </form>
            </div>
            @include('digital-newspaper.partials.tabs', ['active' => $language])
        </div>
    </section>

    <section class="container">
        @if($papers->total() === 0 && $search === '')
            <div class="ep-empty" style="margin-top:26px">
                <i class="bi bi-newspaper"></i>
                <h3>{{ $edition['label'] }} E-Paper coming soon</h3>
                <p>The {{ $edition['label'] }} edition will be available here shortly. Meanwhile, read the <a href="{{ route('digital-newspaper.index') }}">Weekly News Paper</a>.</p>
            </div>
        @else
            <div class="ep-bar">
                <h2>
                    @if($search !== '') Results for “{{ $search }}” @else Latest Editions @endif
                </h2>
                <span>
                    @if($search !== '')<a href="{{ route('epaper.index', $language) }}"><i class="bi bi-x-circle"></i> Clear search</a> · @endif
                    {{ number_format($papers->total()) }} editions
                    @if($papers->lastPage() > 1) · Page {{ $papers->currentPage() }} of {{ number_format($papers->lastPage()) }}@endif
                </span>
            </div>

            @if($papers->isEmpty())
                <div class="ep-empty">
                    <i class="bi bi-search"></i>
                    <h3>No editions found</h3>
                    <p>Try another date, for example 18.08.2026.</p>
                </div>
            @else
                <div class="ep-grid">
                    @foreach($papers as $paper)
                        @php $pageCount = count($paper->pages ?? []); @endphp
                        <a class="ep-card" href="{{ $paper->url }}">
                            <div class="ep-cover">
                                @if($cover = $paper->coverUrl())<img src="{{ $cover }}" alt="{{ $paper->title }}" loading="lazy">@endif
                                @if($pageCount > 1)<span class="ep-pages">{{ $pageCount }} pages</span>@endif
                            </div>
                            <div class="ep-card-body">
                                <b>{{ $paper->title }}</b>
                                <small><i class="bi bi-calendar3"></i> {{ $paper->published_on->format('d M Y') }}</small>
                            </div>
                        </a>
                    @endforeach
                </div>

                {{-- Pagination --}}
                @if($papers->lastPage() > 1)
                    @php
                        $page = $papers->currentPage();
                        $last = $papers->lastPage();
                        $from = max(1, $page - 2);
                        $to = min($last, $page + 2);
                    @endphp
                    <div class="ep-pager">
                        @if($papers->onFirstPage())<span class="disabled"><i class="bi bi-chevron-left"></i> Newer</span>@else<a href="{{ $papers->previousPageUrl() }}"><i class="bi bi-chevron-left"></i> Newer</a>@endif
                        @if($from > 1)<a href="{{ $papers->url(1) }}">1</a>@if($from > 2)<span class="dots">…</span>@endif @endif
                        @for($p = $from; $p <= $to; $p++)
                            @if($p === $page)<span class="current">{{ $p }}</span>@else<a href="{{ $papers->url($p) }}">{{ $p }}</a>@endif
                        @endfor
                        @if($to < $last)@if($to < $last - 1)<span class="dots">…</span>@endif<a href="{{ $papers->url($last) }}">{{ $last }}</a>@endif
                        @if($papers->hasMorePages())<a href="{{ $papers->nextPageUrl() }}">Older <i class="bi bi-chevron-right"></i></a>@else<span class="disabled">Older <i class="bi bi-chevron-right"></i></span>@endif
                    </div>
                @endif
            @endif
        @endif
    </section>
</main>
@endsection

@section('extra-css')
@include('epaper.partials.styles')
@endsection
