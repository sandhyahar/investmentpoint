@extends('layouts.app')

@section('title', $article->title . " | Editor's View | Investment Point")
@section('meta_description', $article->excerpt(160))
@section('og_image', $article->image_url ?? asset(config('seo.default_image')))

@section('content')
@include('partials.article-schema', ['headline' => $article->title, 'description' => $article->excerpt(300), 'image' => $article->image_url, 'published' => $article->published_on, 'modified' => $article->updated_at, 'author' => config('subscription.plan.contact_name'), 'section' => "Editor's View"])
<main class="np-page">
    <div class="np-wrap">
        <div>
            <div class="np-crumbs" aria-label="Breadcrumb">
                <a href="{{ route('home') }}">Home</a><i>›</i>
                <a href="{{ route('editors-desk.index') }}">Editor's View</a><i>›</i>
                {{ $article->title }}
            </div>

            <article class="np-card-box np-article">
                <div class="ed-top">
                    <span class="tag">Editor's View</span>
                    @include('partials.share-button', ['url' => $article->url, 'title' => $article->title])
                </div>
                <h1>{{ $article->title }}</h1>

                {{-- Date · Nikhil Bhatt · views --}}
                @include('partials.article-meta', ['date' => $article->published_on, 'views' => $views])

                @if($article->image_url)<img class="np-hero" src="{{ $article->image_url }}" alt="{{ $article->title }}">@endif

                @include('partials.article-body', ['text' => $article->content])

                @include('partials.stock-disclaimer')
                <div class="ip-share-row">
                    <b>Liked this article? Share it with friends</b>
                    @include('partials.share-button', ['url' => $article->url, 'title' => $article->title, 'label' => 'Share article'])
                </div>
                @include('partials.share-buttons')
            </article>

            @if($newer || $older)
                <div class="ed-nav">
                    @if($newer)<a class="newer" href="{{ $newer->url }}"><small>‹ Newer article</small><b>{{ Str::limit($newer->title, 90) }}</b></a>@endif
                    @if($older)<a class="older" href="{{ $older->url }}"><small>Older article ›</small><b>{{ Str::limit($older->title, 90) }}</b></a>@endif
                </div>
            @endif

            <a class="btn-custom bl-back" href="{{ route('editors-desk.index') }}">← All Editor's View articles</a>
        </div>

        @include('editors-desk.partials.sidebar')
    </div>
</main>
@endsection

@section('extra-css')
@include('news.partials.styles')
@include('editors-desk.partials.styles')
@endsection
