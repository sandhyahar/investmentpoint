@extends('layouts.app')

@section('title', "Expert's View | Investment Point")
@section('meta_description', 'Expert\'s View on Investment Point: stock market articles by SEBI-registered analysts and guest market experts.')

@php
    $featured = ($posts->onFirstPage() && !$search) ? $posts->first() : null;
@endphp

@section('content')
<main class="ep-page">
    <section class="ep-hero">
        <div class="container">
            <div class="ep-crumbs"><a href="{{ route('home') }}">Home</a><span>›</span>Blog<span>›</span>Expert’s View</div>
            <div class="ep-hero-row">
                <h1>Expert’s View<small>Articles written for Investment Point by our clients, guest experts &amp; research team</small></h1>
                <div class="ep-actions">
                    <form class="ep-search" action="{{ route('experts-view.index') }}" method="GET" role="search">
                        <input type="search" name="q" value="{{ $search }}" placeholder="Search articles or authors" aria-label="Search articles">
                        <button type="submit"><i class="bi bi-search"></i></button>
                    </form>
                    @include('experts-view.partials.pageviews', ['class' => 'on-dark'])
                </div>
            </div>
            {{-- One list of all articles (no category tabs), and the form readers use to send a blog --}}
            <div class="ma-filter">
                <a href="{{ route('experts-view.index') }}" class="active">All articles <small>{{ $counts['all'] }}</small></a>
                <a class="ep-create-blog" href="{{ route('blog.create') }}"><i class="bi bi-pencil-square"></i> Create Blog</a>
            </div>
        </div>
    </section>

    <section class="container">
        {{-- Latest reader blogs (Blog -> Create Blog, approved by admin), newest first; older ones fold away --}}
        @if($blogs->isNotEmpty())
            <div class="xb" id="latest-blogs">
                <div class="ep-bar">
                    <h2>Latest Blogs</h2>
                    <span>{{ $blogs->count() }} {{ Str::plural('blog', $blogs->count()) }}</span>
                </div>
                <div class="xb-grid">
                    @foreach($blogs as $blog)
                        @if($loop->index === 6)
                            </div>
                            <details class="xb-all">
                                <summary>Show all {{ $blogs->count() }} blogs <i class="bi bi-chevron-down"></i></summary>
                                <div class="xb-grid">
                        @endif
                        <div class="xv-share-wrap">
                        <a class="xb-card" href="{{ route('blog.show', $blog->slug) }}">
                            <div class="xb-writer">
                                <img src="{{ $blog->writer_photo_url }}" alt="{{ $blog->writer_name }}" @class(['is-logo' => !$blog->has_writer_photo]) loading="lazy">
                                <div>
                                    <b>{{ $blog->writer_name }}</b>
                                    @include('blogs.partials.writer-credentials')
                                </div>
                            </div>
                            <h3>{{ $blog->title }}</h3>
                            <p>{{ \App\Support\Html::plain($blog->content, 160) }}</p>
                            <div class="xb-foot">
                                <span><i class="bi bi-calendar3"></i> {{ $blog->approved_at?->format('d M Y') }}</span>
                                <span class="xv-btn sm">Read Blog <i class="bi bi-arrow-right"></i></span>
                            </div>
                        </a>
                        @include('partials.share-button', ['url' => route('blog.show', $blog->slug), 'title' => $blog->title, 'class' => 'on-card', 'iconOnly' => true])
                        </div>
                    @endforeach
                </div>
                @if($blogs->count() > 6)</details>@endif
            </div>
        @endif

        @if($posts->isEmpty())
            <div class="ep-empty" style="margin-top:26px">
                <i class="bi bi-search"></i>
                <h3>No articles found</h3>
                <p>Try another word or <a href="{{ route('experts-view.index') }}">see all articles</a>.</p>
            </div>
        @else
            <div class="ep-bar">
                <h2>Latest Articles
                    @if($search !== '')<small style="font-weight:600;color:var(--muted);font-size:14px">· “{{ $search }}”</small>@endif
                </h2>
                <span>
                    @if($search !== '')<a href="{{ route('experts-view.index') }}"><i class="bi bi-x-circle"></i> Clear filters</a> · @endif
                    {{ number_format($posts->total()) }} articles
                    @if($posts->lastPage() > 1) · Page {{ $posts->currentPage() }} of {{ $posts->lastPage() }}@endif
                </span>
            </div>

            <div class="xv-grid">
                @foreach($posts as $post)
                    @continue($featured && $post->is($featured))
                    <div class="xv-share-wrap">
                    {{-- Same writer-first card as Latest Blogs: round picture, name, role, then the article --}}
                    <a class="xb-card is-article" href="{{ $post->url }}">
                        <div class="xb-writer">
                            @if($post->image)<img src="{{ $post->imageUrl() }}" alt="{{ $post->title }}" loading="lazy">@else<img class="is-logo" src="{{ asset('images/logo-default.png') }}" alt="Investment Point" loading="lazy">@endif
                            <div>
                                <b>{{ $post->writerName() }}</b>
                                <small>{{ $post->writerRole() }}</small>
                            </div>
                        </div>
                        <h3>{{ $post->title }}</h3>
                        <p>{{ $post->excerpt(160) }}</p>
                        <div class="xb-foot">
                            <span><i class="bi bi-calendar3"></i> {{ $post->published_on->format('d M Y') }}</span>
                            <span class="xv-btn sm">Read Blog <i class="bi bi-arrow-right"></i></span>
                        </div>
                    </a>
                    @include('partials.share-button', ['url' => $post->url, 'title' => $post->title, 'class' => 'on-card', 'iconOnly' => true])
                    </div>
                @endforeach
            </div>

            @if($posts->lastPage() > 1)
                @php
                    $page = $posts->currentPage();
                    $last = $posts->lastPage();
                    $from = max(1, $page - 2);
                    $to = min($last, $page + 2);
                @endphp
                <div class="ep-pager">
                    @if($posts->onFirstPage())<span class="disabled"><i class="bi bi-chevron-left"></i> Newer</span>@else<a href="{{ $posts->previousPageUrl() }}"><i class="bi bi-chevron-left"></i> Newer</a>@endif
                    @if($from > 1)<a href="{{ $posts->url(1) }}">1</a>@if($from > 2)<span class="dots">…</span>@endif @endif
                    @for($p = $from; $p <= $to; $p++)
                        @if($p === $page)<span class="current">{{ $p }}</span>@else<a href="{{ $posts->url($p) }}">{{ $p }}</a>@endif
                    @endfor
                    @if($to < $last)@if($to < $last - 1)<span class="dots">…</span>@endif<a href="{{ $posts->url($last) }}">{{ $last }}</a>@endif
                    @if($posts->hasMorePages())<a href="{{ $posts->nextPageUrl() }}">Older <i class="bi bi-chevron-right"></i></a>@else<span class="disabled">Older <i class="bi bi-chevron-right"></i></span>@endif
                </div>
            @endif

            {{-- Newest article, large, at the end of the page --}}
            @if($featured)
                <div class="xv-share-wrap xv-feature-wrap">
                <a class="xv-feature is-person" href="{{ $featured->url }}">
                    <div class="xv-feature-body">
                        <span class="xv-tag">Featured article</span>
                        <div class="xb-writer">
                            @if($featured->image)<img src="{{ $featured->imageUrl() }}" alt="{{ $featured->title }}" loading="lazy">@else<img class="is-logo" src="{{ asset('images/logo-default.png') }}" alt="Investment Point" loading="lazy">@endif
                            <div>
                                <b>{{ $featured->writerName() }}</b>
                                <small>{{ $featured->writerRole() }}</small>
                            </div>
                        </div>
                        <h2>{{ $featured->title }}</h2>
                        <p>{{ $featured->excerpt(260) }}</p>
                        <div class="xv-meta">
                            <span><i class="bi bi-calendar3"></i> {{ $featured->published_on->format('d M Y') }}</span>
                            <span><i class="bi bi-clock"></i> {{ $featured->readingMinutes() }} min read</span>
                        </div>
                        <span class="xv-btn">Read Blog <i class="bi bi-arrow-right"></i></span>
                    </div>
                </a>
                @include('partials.share-button', ['url' => $featured->url, 'title' => $featured->title, 'class' => 'on-card', 'iconOnly' => true])
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
@include('experts-view.partials.styles')
@include('experts-view.partials.card-styles')
@include('blogs.partials.styles')
/* Latest Blogs section at the top */
.xb{margin-top:26px}
.xb .ep-bar{margin-top:0}
.xb-all{margin-top:16px}
.xb-all summary{list-style:none;cursor:pointer;display:inline-flex;align-items:center;gap:6px;font-size:14px;font-weight:800;color:var(--blue);padding:9px 16px;border:1px solid var(--blue);border-radius:10px;background:#fff;margin-bottom:16px}
.xb-all summary::-webkit-details-marker{display:none}
.xb-all[open] summary i{transform:rotate(180deg)}
/* Total Pageviews under the search box on the banner */
.ep-hero .ep-actions{flex-direction:column;align-items:stretch}
/* Create Blog button next to "All articles" */
.ma-filter a.ep-create-blog{background:#fff;border-color:#fff;color:var(--blue)}
.ma-filter a.ep-create-blog:hover{background:var(--gold);border-color:var(--gold);color:#141f55}
@endsection
