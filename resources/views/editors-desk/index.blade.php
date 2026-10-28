@extends('layouts.app')

@section('title', "Editor's View | Investment Point")
@section('meta_description', "Editor's View: daily stock market and investing articles written by Nikhil Bhatt, Business Editor, for Investment Point.")

@section('content')
<main class="np-page">
    <div class="np-wrap">
        <section>
            <div class="np-crumbs" aria-label="Breadcrumb">
                <a href="{{ route('home') }}">Home</a><i>›</i>
                <a href="{{ route('editors-desk.index') }}">Editor's View</a>
            </div>

            <div class="np-card-box np-listhead ed-head">
                <img src="{{ asset('images/nikhil-bhatt.png') }}" alt="Nikhil Bhatt" class="ed-head-photo">
                <div>
                    <h1>
                        @if($search !== '') Search results for “{{ $search }}” @else Editor's View @endif
                    </h1>
                    <p class="np-muted" style="margin:4px 0 0;font-size:13px">Daily articles by <b class="notranslate" translate="no">Nikhil Bhatt</b>, Business Editor</p>
                </div>
            </div>

            @if($articles->isEmpty())
                <div class="np-card-box np-empty">
                    <p class="np-muted">{{ $search !== '' ? 'No article matches your search.' : 'No articles yet. Please check back soon.' }}</p>
                    @if($search !== '')<a class="btn-custom primary" href="{{ route('editors-desk.index') }}">View all articles</a>@endif
                </div>
            @else
                <div class="np-grid">
                    @foreach($articles as $item)
                        <article class="np-card">
                            <a class="np-card-img" href="{{ $item->url }}">
                                @if($item->image_url)<img src="{{ $item->image_url }}" alt="{{ $item->title }}" loading="lazy">@else<img class="ed-noimg" src="{{ asset('images/logo-default.png') }}" alt="Investment Point" loading="lazy">@endif
                            </a>
                            <div class="np-card-body">
                                <h3><a href="{{ $item->url }}">{{ $item->title }}</a></h3>
                                <small><span class="notranslate" translate="no">✍️ Nikhil Bhatt</span> · {{ $item->published_on->format('d M Y') }}</small>
                                <p>{{ $item->excerpt(110) }}</p>
                                <a class="np-readon" href="{{ $item->url }}">Read full article →</a>
                            </div>
                        </article>
                    @endforeach
                </div>
                <div class="np-pagination">{{ $articles->links('pagination::bootstrap-5') }}</div>
            @endif
        </section>

        @include('editors-desk.partials.sidebar')
    </div>
</main>
@endsection

@section('extra-css')
@include('news.partials.styles')
@include('editors-desk.partials.styles')
@endsection
