@extends('layouts.app')

@section('title', $post->title . " | Expert's View | Investment Point")
@section('meta_description', $post->excerpt(160))
@section('og_image', $post->imageUrl() ?? asset(config('seo.default_image')))

@section('content')
@php $intro = $post->authorIntro(); @endphp
@include('partials.article-schema', ['headline' => $post->title, 'description' => $post->excerpt(300), 'image' => $post->imageUrl(), 'published' => $post->published_on, 'modified' => $post->updated_at, 'author' => $post->writerName(), 'section' => "Expert's View"])
<main class="ep-page">
    <section class="ep-hero xv-hero">
        <div class="container">
            <div class="ep-crumbs"><a href="{{ route('home') }}">Home</a><span>›</span><a href="{{ route('experts-view.index') }}">Expert’s View</a><span>›</span>Article</div>
            <h1>{{ $post->title }}</h1>
            <div class="xv-meta">
                <span class="xv-author"><i class="bi bi-person-circle"></i> {{ $post->writerName() }}</span>
                <span><i class="bi bi-calendar3"></i> {{ $post->published_on->format('d M Y') }}</span>
                <span><i class="bi bi-clock"></i> {{ $post->readingMinutes() }} min read</span>
                <span><i class="bi bi-eye"></i> {{ number_format($views) }} {{ Str::plural('view', $views) }}</span>
            </div>
        </div>
    </section>

    <section class="container">
        <div class="xv-article">
            <div>
                <article class="xv-read">
                    @if($intro)
                        {{-- The writer's photo + details from the top of the post, as a profile card; the article starts below --}}
                        @include('partials.author-card', [
                            'photo' => $intro['photo'], 'label' => $intro['label'] ?? 'Written by', 'name' => $intro['name'],
                            'roles' => $intro['roles'], 'contacts' => $intro['contacts'],
                            'date' => $post->published_on, 'views' => $views,
                            'shareUrl' => $post->url, 'shareTitle' => $post->title,
                        ])
                    @else
                        {{-- No writer photo in the post: the same card with the site logo as the picture --}}
                        @include('partials.author-card', [
                            'photo' => \App\Models\Setting::logoIconUrl(), 'isLogo' => true, 'name' => $post->writerName(),
                            'roles' => [['text' => $post->author ? 'Guest expert for Investment Point' : 'Investment Point research desk', 'sebi' => false]],
                            'date' => $post->published_on, 'views' => $views,
                            'shareUrl' => $post->url, 'shareTitle' => $post->title,
                        ])
                        {{-- Uploaded cover (imported posts already show their pictures inside the text) --}}
                        @if($post->isUploadedImage())<img class="xv-cover" src="{{ $post->imageUrl() }}" alt="{{ $post->title }}">@endif
                    @endif
                    @include('partials.article-body', ['text' => $post->articleHtml()])

                    @include('partials.stock-disclaimer')
                    <div class="ip-share-row">
                        <b>Liked this article? Share it with friends</b>
                        @include('partials.share-button', ['url' => $post->url, 'title' => $post->title, 'label' => 'Share article'])
                    </div>
                    @include('partials.share-buttons')
                </article>

                @if($newer || $older)
                    <div class="ep-nav">
                        @if($newer)<a class="newer" href="{{ $newer->url }}"><small><i class="bi bi-chevron-left"></i> Newer article</small><b>{{ Str::limit($newer->title, 90) }}</b></a>@endif
                        @if($older)<a class="older" href="{{ $older->url }}"><small>Older article <i class="bi bi-chevron-right"></i></small><b>{{ Str::limit($older->title, 90) }}</b></a>@endif
                    </div>
                @endif
            </div>

            <aside class="xv-side">
                <div class="ma-card">
                    <h3>Written by</h3>
                    <div class="xv-byline">
                        @if($intro)
                            <img class="xv-avatar" src="{{ $intro['photo'] }}" alt="{{ $intro['name'] }}" loading="lazy">
                        @else
                            <img class="xv-avatar" src="{{ \App\Models\Setting::logoIconUrl() }}" alt="Investment Point" loading="lazy" style="object-fit:contain;background:#fff;padding:5px;border:1px solid var(--line)">
                        @endif
                        <div><b>{{ $post->writerName() }}</b><small>{{ $intro ? $post->writerRole() : ($post->author ? 'Guest expert for Investment Point' : 'Investment Point research desk') }}</small></div>
                    </div>
                </div>

                @if($more->isNotEmpty())
                    <div class="ma-card xv-more">
                        <h3>{{ $post->author ? 'More from ' . $post->author : 'More articles' }}</h3>
                        @foreach($more as $other)
                            <a href="{{ $other->url }}">
                                @if($other->image)<img src="{{ $other->imageUrl() }}" alt="{{ $other->title }}" loading="lazy">@else<div class="xv-noimg"><img src="{{ asset('images/logo-default.png') }}" alt="Investment Point"></div>@endif
                                <div><b>{{ $other->title }}</b><small>{{ $other->published_on->format('d M Y') }}</small></div>
                            </a>
                        @endforeach
                    </div>
                @endif

                <div class="ma-card">@include('experts-view.partials.pageviews')</div>

                <a class="xv-btn" href="{{ route('experts-view.index') }}" style="align-self:stretch;justify-content:center"><i class="bi bi-grid-3x3-gap-fill"></i> All articles</a>
            </aside>
        </div>

        @include('media-articles.partials.about')
    </section>
</main>
@endsection

@section('extra-css')
@include('news.partials.styles')
@include('epaper.partials.styles')
@include('media-articles.partials.styles')
@include('experts-view.partials.styles')
@include('partials.author-card-styles')
@endsection
