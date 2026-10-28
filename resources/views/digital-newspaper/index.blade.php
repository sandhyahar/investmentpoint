@extends('layouts.app')

@section('title', 'Weekly News Paper' . ($year ? ' ' . $year : '') . ' | Investment Point Weekly')
@section('meta_description', 'Read every Investment Point Weekly edition online: the Gujarati weekly business newspaper by Nikhil Bhatt, all pages free to read.')

@php
    $pageUrl = fn ($p) => route('digital-newspaper.index', array_filter(['year' => $year ?: null, 'q' => $search ?: null, 'page' => $p > 1 ? $p : null]));
    $page = $papers->currentPage();
    $lastPage = $papers->lastPage();
@endphp

@section('content')
<main class="ep-page">
    <section class="ep-hero">
        <div class="container">
            <div class="ep-crumbs"><a href="{{ route('home') }}">Home</a><span>›</span>E-Paper<span>›</span>Weekly News Paper</div>
            <div class="ep-hero-row">
                <h1>Weekly News Paper<small>Investment Point Weekly · Gujarati weekly business newspaper</small></h1>
                <div class="dn-hero-side">
                    <form class="ep-search" action="{{ route('digital-newspaper.index') }}" method="GET" role="search">
                        @if($year)<input type="hidden" name="year" value="{{ $year }}">@endif
                        <input type="search" name="q" value="{{ $search }}" placeholder="Search by date, e.g. 18.09.2026" aria-label="Search editions">
                        <button type="submit"><i class="bi bi-search"></i></button>
                    </form>
                    @include('digital-newspaper.partials.pageviews')
                </div>
            </div>
            @include('digital-newspaper.partials.tabs', ['active' => 'digital'])
        </div>
    </section>

    <section class="container">
        {{-- Archive: one chip per year --}}
        @if($years->isNotEmpty())
            <div class="dn-years" aria-label="Archive by year">
                <a href="{{ route('digital-newspaper.index', array_filter(['q' => $search ?: null])) }}" @class(['active' => !$year])>All <small>{{ number_format($years->sum()) }}</small></a>
                @foreach($years as $y => $count)
                    <a href="{{ route('digital-newspaper.index', array_filter(['year' => $y, 'q' => $search ?: null])) }}" @class(['active' => $year === (int) $y])>{{ $y }} <small>{{ $count }}</small></a>
                @endforeach
            </div>
        @endif

        <div class="ep-bar">
            <h2>
                @if($search !== '') Results for “{{ $search }}” @elseif($year) {{ $year }} Editions @else Latest Editions @endif
            </h2>
            <span>
                @if($search !== '' || $year)
                    <a href="{{ route('digital-newspaper.index') }}"><i class="bi bi-x-circle"></i> Show all</a> ·
                @endif
                {{ number_format($papers->total()) }} {{ Str::plural('edition', $papers->total()) }}@if($lastPage > 1) · Page {{ $page }} of {{ number_format($lastPage) }}@endif
            </span>
        </div>

        @if($papers->isEmpty())
            <div class="ep-empty">
                <i class="bi bi-search"></i>
                <h3>No editions found</h3>
                <p>Try another date, for example 18.09.2026.</p>
            </div>
        @else
            <div class="ep-grid">
                @foreach($papers as $paper)
                    <a class="ep-card" href="{{ $paper->url }}">
                        <div class="ep-cover">
                            @if($cover = $paper->coverUrl())<img src="{{ $cover }}" alt="{{ $paper->title }}" loading="lazy">@endif
                            @if(count($paper->pages) > 1)<span class="ep-pages">{{ count($paper->pages) }} pages</span>@endif
                        </div>
                        <div class="ep-card-body">
                            <b>{{ $paper->title }}</b>
                            <small><i class="bi bi-calendar3"></i> {{ $paper->date_label }}</small>
                        </div>
                    </a>
                @endforeach
            </div>

            @if($lastPage > 1)
                <div class="ep-pager">
                    @if($page > 1)<a href="{{ $pageUrl($page - 1) }}"><i class="bi bi-chevron-left"></i> Newer</a>@else<span class="disabled"><i class="bi bi-chevron-left"></i> Newer</span>@endif
                    @php $from = max(1, $page - 2); $to = min($lastPage, $page + 2); @endphp
                    @if($from > 1)<a href="{{ $pageUrl(1) }}">1</a>@if($from > 2)<span class="dots">…</span>@endif @endif
                    @for($p = $from; $p <= $to; $p++)
                        @if($p === $page)<span class="current">{{ $p }}</span>@else<a href="{{ $pageUrl($p) }}">{{ $p }}</a>@endif
                    @endfor
                    @if($to < $lastPage)@if($to < $lastPage - 1)<span class="dots">…</span>@endif<a href="{{ $pageUrl($lastPage) }}">{{ $lastPage }}</a>@endif
                    @if($page < $lastPage)<a href="{{ $pageUrl($page + 1) }}">Older <i class="bi bi-chevron-right"></i></a>@else<span class="disabled">Older <i class="bi bi-chevron-right"></i></span>@endif
                </div>
            @endif
        @endif

        @include('media-articles.partials.about', ['registration' => 'RNI / SEBI'])
    </section>
</main>
@endsection

@section('extra-css')
@include('epaper.partials.styles')
@include('media-articles.partials.styles')
@include('digital-newspaper.partials.styles')
@endsection
