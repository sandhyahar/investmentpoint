@extends('layouts.app')

@section('title', 'Media Articles | Investment Point')
@section('meta_description', 'Investment Point columns by Nikhil Bhatt published in newspapers across Gujarat and India, searchable by newspaper and date.')

@section('content')
<main class="ep-page">
    <section class="ep-hero">
        <div class="container">
            <div class="ep-crumbs"><a href="{{ route('home') }}">Home</a><span>›</span>Media<span>›</span>Media Article</div>
            <div class="ep-hero-row">
                <h1>Media Articles<small>Investment Point columns published in newspapers across Gujarat &amp; India</small></h1>
                <form class="ep-search" action="{{ route('media-articles.index') }}" method="GET" role="search">
                    @if($publication !== '')<input type="hidden" name="publication" value="{{ $publication }}">@endif
                    <input type="search" name="q" value="{{ $search }}" placeholder="Search title or date, e.g. 20.02.2026" aria-label="Search media articles">
                    <button type="submit"><i class="bi bi-search"></i></button>
                </form>
            </div>

            {{-- Newspaper filter --}}
            {{-- Every newspaper in one row: scroll bar, ‹ › buttons and the mouse wheel move it sideways --}}
            @if($publications)
                @php $current = \App\Models\MediaArticle::normalizePublication($publication); @endphp
                <div class="ma-filter-wrap">
                    <button type="button" class="ma-filter-arrow prev" aria-label="Previous newspapers" hidden><i class="bi bi-chevron-left"></i></button>
                    <div class="ma-filter ma-filter-scroll" id="maFilter">
                        <a href="{{ route('media-articles.index', array_filter(['q' => $search ?: null])) }}" @class(['active' => $publication === ''])>All newspapers</a>
                        @foreach($publications as $name => $count)
                            <a href="{{ route('media-articles.index', array_filter(['publication' => $name, 'q' => $search ?: null])) }}" @class(['active' => $current === $name])>{{ $name }} <small>{{ number_format($count) }}</small></a>
                        @endforeach
                    </div>
                    <button type="button" class="ma-filter-arrow next" aria-label="More newspapers" hidden><i class="bi bi-chevron-right"></i></button>
                    {{-- Slim scroll bar in the site's colours (drag the gold handle or click the track) --}}
                    <div class="ma-filter-bar" aria-hidden="true" hidden><span></span></div>
                </div>
                <script>
                (function () {
                    const row = document.getElementById('maFilter');
                    const prev = row.parentNode.querySelector('.prev'), next = row.parentNode.querySelector('.next');
                    // Fade + ‹ › only on a side where more newspapers are hidden
                    function arrows() {
                        const max = row.scrollWidth - row.clientWidth;
                        prev.hidden = row.scrollLeft <= 4;
                        next.hidden = row.scrollLeft >= max - 4;
                        row.parentNode.classList.toggle('more-left', !prev.hidden);
                        row.parentNode.classList.toggle('more-right', !next.hidden);
                        // Scroll bar: handle size = the part in view, position = how far the row is scrolled
                        bar.hidden = max <= 4;
                        const part = row.clientWidth / row.scrollWidth;
                        thumb.style.width = Math.max(part * 100, 8) + '%';
                        thumb.style.left = (max > 0 ? row.scrollLeft / max : 0) * (100 - Math.max(part * 100, 8)) + '%';
                    }
                    const bar = row.parentNode.querySelector('.ma-filter-bar'), thumb = bar.querySelector('span');
                    // Click the track: jump there; drag the handle: scroll along
                    bar.addEventListener('pointerdown', e => {
                        const rect = bar.getBoundingClientRect(), max = row.scrollWidth - row.clientWidth;
                        const free = rect.width - thumb.offsetWidth;
                        const startX = e.clientX, onThumb = e.target === thumb;
                        const startLeft = onThumb ? row.scrollLeft : Math.min(max, Math.max(0, (e.clientX - rect.left - thumb.offsetWidth / 2) / free * max));
                        row.style.scrollBehavior = 'auto';
                        row.scrollLeft = startLeft;
                        bar.classList.add('dragging');
                        bar.setPointerCapture(e.pointerId);
                        const move = ev => { row.scrollLeft = startLeft + (ev.clientX - startX) / free * max; };
                        const up = () => { bar.classList.remove('dragging'); row.style.scrollBehavior = ''; bar.removeEventListener('pointermove', move); bar.removeEventListener('pointerup', up); };
                        bar.addEventListener('pointermove', move);
                        bar.addEventListener('pointerup', up);
                        e.preventDefault();
                    });
                    // Drag the row with the mouse (a real drag never opens a newspaper by accident)
                    let dragX = null, startLeft = 0, moved = false;
                    row.addEventListener('pointerdown', e => { if (e.pointerType !== 'mouse' || e.button !== 0) return; dragX = e.clientX; startLeft = row.scrollLeft; moved = false; });
                    window.addEventListener('pointermove', e => {
                        if (dragX === null) return;
                        if (!moved && Math.abs(e.clientX - dragX) > 6) { moved = true; row.classList.add('dragging'); }
                        if (moved) row.scrollLeft = startLeft - (e.clientX - dragX);
                    });
                    window.addEventListener('pointerup', () => { dragX = null; setTimeout(() => row.classList.remove('dragging'), 0); });
                    row.addEventListener('click', e => { if (moved) { e.preventDefault(); moved = false; } }, true);
                    row.addEventListener('dragstart', e => e.preventDefault());
                    prev.addEventListener('click', () => row.scrollBy({ left: -row.clientWidth * 0.8, behavior: 'smooth' }));
                    next.addEventListener('click', () => row.scrollBy({ left: row.clientWidth * 0.8, behavior: 'smooth' }));
                    // Mouse wheel scrolls the row sideways (only while there is more to see that way)
                    row.addEventListener('wheel', e => {
                        if (Math.abs(e.deltaY) <= Math.abs(e.deltaX)) return;
                        const max = row.scrollWidth - row.clientWidth;
                        if ((e.deltaY < 0 && row.scrollLeft <= 0) || (e.deltaY > 0 && row.scrollLeft >= max)) return;
                        e.preventDefault();
                        row.scrollLeft += e.deltaY;
                    }, { passive: false });
                    row.addEventListener('scroll', arrows, { passive: true });
                    window.addEventListener('resize', arrows);
                    // The chosen newspaper is shown, not hidden off to the right
                    const active = row.querySelector('a.active');
                    if (active && active.offsetLeft + active.offsetWidth > row.clientWidth) row.scrollLeft = active.offsetLeft - 60;
                    arrows();
                })();
                </script>
            @endif
        </div>
    </section>

    <section class="container">
        <div class="ep-bar">
            <h2>
                @if($publication !== '') {{ $publication }} @else Latest Media Articles @endif
                @if($search !== '') <small style="font-weight:600;color:var(--muted);font-size:14px">· “{{ $search }}”</small>@endif
            </h2>
            <span>
                @if($search !== '' || $publication !== '')
                    <a href="{{ route('media-articles.index') }}"><i class="bi bi-x-circle"></i> Clear filters</a> ·
                @endif
                {{ number_format($articles->total()) }} articles
                @if($articles->lastPage() > 1) · Page {{ $articles->currentPage() }} of {{ number_format($articles->lastPage()) }}@endif
            </span>
        </div>

        @if($articles->isEmpty())
            <div class="ep-empty">
                <i class="bi bi-search"></i>
                <h3>No media articles found</h3>
                <p>Try another newspaper or date, for example 20.02.2026.</p>
            </div>
        @else
            <div class="ep-grid">
                @foreach($articles as $article)
                    <a class="ep-card" href="{{ $article->url }}">
                        <div class="ep-cover ma-cover">
                            @if($article->image)<img src="{{ $article->imageUrl('w400') }}" alt="{{ $article->title }}" loading="lazy">@endif
                        </div>
                        <div class="ep-card-body">
                            <b>{{ $article->publication ?: $article->title }}</b>
                            @if($article->publication && $article->column_name)<span class="ma-col">{{ $article->column_name }}</span>@endif
                            @if($article->published_on)<small><i class="bi bi-calendar3"></i> {{ $article->published_on->format('d M Y') }}</small>@endif
                        </div>
                    </a>
                @endforeach
            </div>

            {{-- Pagination --}}
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
                    @if($to < $last)@if($to < $last - 1)<span class="dots">…</span>@endif<a href="{{ $articles->url($last) }}">{{ number_format($last) }}</a>@endif
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
@endsection
