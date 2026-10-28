@extends('layouts.app')

@section('title', $analysis->title . ' | Investment Point')
@section('meta_description', \App\Support\Html::metaDescription($analysis->description, $analysis->body))
@section('og_image', $analysis->image_url)

@section('content')
@php
    $cmp = \App\Models\TechnicalAnalysis::formatLevel($analysis->cmp);
    $support = \App\Models\TechnicalAnalysis::formatLevel($analysis->support);
    $resistance = \App\Models\TechnicalAnalysis::formatLevel($analysis->resistance);
@endphp
@include('partials.article-schema', ['headline' => $analysis->title, 'description' => $analysis->description ?: $analysis->body, 'image' => $analysis->image_url, 'published' => $analysis->published_on ?? $analysis->created_at, 'modified' => $analysis->updated_at, 'section' => 'Technical Analysis'])

<main class="np-page">
    <div class="np-wrap">
        <div>
            <div class="np-crumbs" aria-label="Breadcrumb">
                <a href="{{ route('home') }}">Home</a><i>›</i>
                <a href="{{ route('analysis.index') }}">Technical Analysis</a><i>›</i>
                {{ $analysis->title }}
            </div>

            <article class="np-card-box np-article">
                <div class="np-top">
                    <span class="tag">Technical</span>
                    @include('partials.share-button', ['url' => $analysis->share_url, 'title' => $analysis->title])
                </div>
                <h1>{{ $analysis->title }}</h1>

                @include('partials.article-meta', ['date' => $analysis->published_on, 'views' => \App\Models\PageView::viewsOf(['analysis.show'], $analysis->id)])

                @if($cmp || $support || $resistance)
                    <div class="ta-keylevels">
                        @if($cmp)
                            <div class="ta-keylevel cmp">
                                <small>CMP</small>
                                <strong>{{ $cmp }}</strong>
                            </div>
                        @endif
                        @if($support)
                            <div class="ta-keylevel support">
                                <small>Support</small>
                                <strong>{{ $support }}</strong>
                            </div>
                        @endif
                        @if($resistance)
                            <div class="ta-keylevel resistance">
                                <small>Resistance</small>
                                <strong>{{ $resistance }}</strong>
                            </div>
                        @endif
                    </div>
                @endif

                <img class="np-hero" src="{{ $analysis->image_url }}" alt="{{ $analysis->title }}">

                @include('partials.article-body', ['text' => $analysis->body])
                @include('partials.read-more')

                @include('partials.stock-disclaimer')
                <div class="ip-share-row">
                    <b>Liked this analysis? Share it with friends</b>
                    @include('partials.share-button', ['url' => $analysis->share_url, 'title' => $analysis->title, 'label' => 'Share analysis'])
                </div>
                @include('partials.share-buttons')
            </article>

            <a class="btn-custom bl-back" href="{{ route('analysis.index') }}">← Back to all analysis</a>
        </div>

        @include('analysis.partials.sidebar')
    </div>
</main>
@endsection

@section('extra-css')
@include('news.partials.styles')
@include('analysis.partials.styles')
@endsection
