@extends('layouts.app')

@section('title', $article->title . ' | Media Articles | Investment Point')
@section('meta_description', trim(($article->column_name ?: 'Investment Point') . ' column by Nikhil Bhatt' . ($article->publication ? ' in ' . $article->publication : '') . ($article->published_on ? ', ' . $article->published_on->format('d M Y') : '') . '.'))
@section('og_image', $article->imageUrl('w1200') ?? asset(config('seo.default_image')))

@section('content')
@include('partials.article-schema', ['headline' => $article->title, 'description' => $__env->yieldContent('meta_description'), 'image' => $article->imageUrl('w1200'), 'published' => $article->published_on, 'modified' => $article->updated_at, 'author' => 'Nikhil Bhatt', 'section' => 'Media Articles'])
<main class="ep-page">
    <section class="ep-hero">
        <div class="container">
            <div class="ep-crumbs">
                <a href="{{ route('home') }}">Home</a><span>›</span><a href="{{ route('media-articles.index') }}">Media Articles</a>
                @if($article->publication)<span>›</span><a href="{{ route('media-articles.index', ['publication' => $article->publication]) }}">{{ $article->publication }}</a>@endif
            </div>
            <div class="ep-hero-row">
                <h1>{{ $article->publication ?: $article->title }}
                    <small>
                        @if($article->publication && $article->column_name){{ $article->column_name }}@endif
                        @if($article->publication && $article->column_name && $article->published_on) · @endif
                        @if($article->published_on){{ $article->published_on->format('d M Y') }}@endif
                    </small>
                </h1>
                <div class="ep-actions">
                    <a class="ep-btn" href="{{ route('media-articles.index') }}"><i class="bi bi-grid-3x3-gap-fill"></i> All articles</a>
                    @if($article->image)<a class="ep-btn gold" href="{{ $article->imageUrl('s0') }}" target="_blank" rel="noopener noreferrer"><i class="bi bi-zoom-in"></i> Full size</a>@endif
                </div>
            </div>
        </div>
    </section>

    <section class="container">
        <div class="ma-view">
            <div>
                {{-- Every page of the article, in order (older articles: their one clipping) --}}
                @foreach($article->pageImages() as $n => $page)
                    <a class="ma-clip" style="display:block{{ $n ? ';margin-top:18px' : '' }}" href="{{ $page['full'] }}" target="_blank" rel="noopener noreferrer">
                        <img src="{{ $page['view'] }}" alt="{{ $article->title }}{{ count($article->pages ?? []) > 1 ? ' - page ' . ($n + 1) : '' }}" @if($n) loading="lazy" @endif>
                    </a>
                @endforeach
                @if($article->description)
                    <div class="ep-note" style="margin-top:18px;white-space:pre-line">{{ $article->description }}</div>
                @endif

                @if($newer || $older)
                    <div class="ep-nav">
                        @if($newer)<a class="newer" href="{{ $newer->url }}"><small><i class="bi bi-chevron-left"></i> Newer article</small><b>{{ $newer->title }}</b></a>@endif
                        @if($older)<a class="older" href="{{ $older->url }}"><small>Older article <i class="bi bi-chevron-right"></i></small><b>{{ $older->title }}</b></a>@endif
                    </div>
                @endif
            </div>

            <aside class="ma-side">
                <div class="ma-card">
                    <h3>Article details</h3>
                    <div class="ma-facts">
                        @if($article->column_name)<div><span>Column</span><b>{{ $article->column_name }}</b></div>@endif
                        @if($article->publication)<div><span>Newspaper</span><b>{{ $article->publication }}</b></div>@endif
                        @if($article->published_on)<div><span>Published</span><b>{{ $article->published_on->format('l, d M Y') }}</b></div>@endif
                        <div><span>Title</span><b style="font-size:13px">{{ $article->title }}</b></div>
                    </div>
                </div>

                @if($sameDay->isNotEmpty())
                    <div class="ma-card ma-same">
                        <h3>Same day in other papers</h3>
                        @foreach($sameDay as $other)
                            <a href="{{ $other->url }}">
                                @if($other->image)<img src="{{ $other->imageUrl('w120') }}" alt="{{ $other->title }}" loading="lazy">@endif
                                <div><b>{{ $other->publication ?: $other->title }}</b><small>{{ $other->column_name }}</small></div>
                            </a>
                        @endforeach
                    </div>
                @endif
            </aside>
        </div>

        @include('media-articles.partials.about')
    </section>
</main>
@endsection

@section('extra-css')
@include('epaper.partials.styles')
@include('media-articles.partials.styles')
@endsection
