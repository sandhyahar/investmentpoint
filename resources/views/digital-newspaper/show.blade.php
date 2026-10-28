@extends('layouts.app')

@section('title', $paper->title . ' | Weekly News Paper | Investment Point')
@section('meta_description', 'Read ' . $paper->title . ', the Investment Point Weekly newspaper edition for ' . $paper->date_label . '.')
@section('og_image', $paper->coverUrl() ?? asset(config('seo.default_image')))

@php
    $sheets = $paper->pageImages();
    $pageCount = count($sheets);
@endphp

@section('content')
<main class="ep-page">
    <section class="ep-hero">
        <div class="container">
            <div class="ep-crumbs">
                <a href="{{ route('home') }}">Home</a><span>›</span><a href="{{ route('digital-newspaper.index') }}">Weekly News Paper</a><span>›</span>{{ $paper->title }}
            </div>
            <div class="ep-hero-row">
                <h1>{{ $paper->title }}
                    <small>
                        Investment Point Weekly · {{ $paper->date_label }}
                        @if($pageCount) · {{ $pageCount }} {{ $pageCount === 1 ? 'page' : 'pages' }}@endif
                    </small>
                </h1>
                <div class="ep-actions">
                    <a class="ep-btn" href="{{ route('digital-newspaper.index') }}"><i class="bi bi-grid-3x3-gap-fill"></i> All editions</a>
                </div>
            </div>
        </div>
    </section>

    <section class="container">
        @if($pageCount)
            <div class="ep-reader">
                {{-- Page thumbnails --}}
                <aside class="ep-thumbs" aria-label="Pages">
                    @foreach($sheets as $n => $sheet)
                        <a href="#page-{{ $n + 1 }}"><img src="{{ $sheet['thumb'] }}" alt="Page {{ $n + 1 }}" loading="lazy"><span>{{ $n + 1 }}</span></a>
                    @endforeach
                </aside>

                {{-- Full pages --}}
                <div class="ep-sheets">
                    @foreach($sheets as $n => $sheet)
                        <figure class="ep-sheet" id="page-{{ $n + 1 }}" style="margin:0">
                            <div class="ep-sheet-head">
                                <span>Page {{ $n + 1 }} of {{ $pageCount }}</span>
                                <a href="{{ $sheet['full'] }}" target="_blank" rel="noopener noreferrer"><i class="bi bi-zoom-in"></i> Full size</a>
                            </div>
                            <a href="{{ $sheet['full'] }}" target="_blank" rel="noopener noreferrer">
                                <img src="{{ $sheet['view'] }}" alt="{{ $paper->title }} – page {{ $n + 1 }}" @if($n > 0) loading="lazy" @endif>
                            </a>
                        </figure>
                    @endforeach
                </div>
            </div>
        @endif

        @if($newer || $older)
            <div class="ep-nav">
                @if($newer)<a class="newer" href="{{ $newer->url }}"><small><i class="bi bi-chevron-left"></i> Newer edition</small><b>{{ $newer->title }}</b></a>@endif
                @if($older)<a class="older" href="{{ $older->url }}"><small>Older edition <i class="bi bi-chevron-right"></i></small><b>{{ $older->title }}</b></a>@endif
            </div>
        @endif

        @include('digital-newspaper.partials.pageviews')
        @include('media-articles.partials.about', ['registration' => 'RNI / SEBI'])
    </section>
</main>
@endsection

@section('extra-css')
@include('epaper.partials.styles')
@include('media-articles.partials.styles')
@include('digital-newspaper.partials.styles')
@endsection
