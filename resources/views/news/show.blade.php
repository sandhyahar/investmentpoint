@extends('layouts.app')

@section('title', $news->title . ' | Investment Point')
@section('meta_description', \App\Support\Html::metaDescription($news->description, $news->body))
@section('og_image', $news->image_url)

@section('content')
@include('partials.article-schema', ['headline' => $news->title, 'description' => $news->description ?: $news->body, 'image' => $news->image_url, 'published' => $news->created_at, 'modified' => $news->updated_at, 'section' => 'Market News'])
<main class="np-page">
    <div class="np-wrap">
        <div>
            <div class="np-crumbs" aria-label="Breadcrumb">
                <a href="{{ route('home') }}">Home</a><i>›</i>
                <a href="{{ route('news.index') }}">News</a><i>›</i>
                {{ $news->title }}
            </div>

            <article class="np-card-box np-article">
                <div class="np-top">
                    <span class="tag">Market Trend</span>
                    @include('partials.share-button', ['url' => $news->share_url, 'title' => $news->title])
                </div>
                <h1>{{ $news->title }}</h1>

                @include('partials.article-meta', ['date' => $news->created_at, 'views' => (int) $news->views, 'withTime' => true])

                <img class="np-hero" src="{{ $news->image_url }}" alt="{{ $news->title }}">

                @include('partials.article-body', ['text' => $news->body])

                @include('partials.stock-disclaimer')
                <div class="ip-share-row">
                    <b>Liked this article? Share it with friends</b>
                    @include('partials.share-button', ['url' => $news->share_url, 'title' => $news->title, 'label' => 'Share article'])
                </div>
                @include('partials.share-buttons')
            </article>

            @if($related->isNotEmpty())
                <section class="np-related">
                    <h2 class="np-title">Related Articles</h2>
                    <div class="np-grid">
                        @foreach($related as $item)
                            <article class="np-card">
                                <a class="np-card-img" href="{{ $item->url }}">
                                    <img src="{{ $item->image_url }}" alt="{{ $item->title }}" loading="lazy">
                                </a>
                                <div class="np-card-body">
                                    <h3><a href="{{ $item->url }}">{{ $item->title }}</a></h3>
                                    <small>{{ $item->created_at?->format('d M Y') }}</small>
                                </div>
                            </article>
                        @endforeach
                    </div>
                </section>
            @endif
        </div>

        @include('news.partials.sidebar')
    </div>
</main>
@endsection

@section('extra-css')
@include('news.partials.styles')
@endsection
