@extends('layouts.app')

@section('title', 'Market News | Investment Point')
@section('meta_description', 'Latest Indian stock market and business news: Sensex, Nifty, IPOs and company updates, refreshed every few minutes on Investment Point.')

@section('content')
<main class="np-page">
    <div class="np-wrap">
        <section>
            <div class="np-crumbs" aria-label="Breadcrumb">
                <a href="{{ route('home') }}">Home</a><i>›</i>
                <a href="{{ route('news.index') }}">News</a>
            </div>

            <div class="np-card-box np-listhead">
                <h1>
                    @if($search !== '')
                        Search results for “{{ $search }}”
                    @else
                        Latest Business News
                    @endif
                </h1>
                <p class="np-muted" style="margin:4px 0 0;font-size:13px">Indian stock market &amp; business updates from Investment Point</p>
            </div>

            @if($news->isEmpty())
                <div class="np-card-box np-empty">
                    <p class="np-muted">{{ $search !== '' ? 'No news matches your search.' : 'No news yet. Please check back soon.' }}</p>
                    <a class="btn-custom primary" href="{{ route('news.index') }}">View all news</a>
                </div>
            @else
                <div class="np-grid">
                    @foreach($news as $item)
                        @php $published = $item->display_date; @endphp
                        <article class="np-card">
                            <a class="np-card-img" href="{{ $item->url }}">
                                <img src="{{ $item->image_url }}" alt="{{ $item->title }}" loading="lazy">
                            </a>
                            <div class="np-card-body">
                                <h3><a href="{{ $item->url }}">{{ $item->title }}</a></h3>
                                <small><span title="{{ $published?->format('d M Y, h:i A') }}">{{ $published?->diffForHumans() }}</span></small>
                                <p>{{ Str::limit(\App\Support\Html::plain($item->description), 110) }}</p>
                                <a class="np-readon" href="{{ $item->url }}">Read full article →</a>
                            </div>
                        </article>
                    @endforeach
                </div>
                <div class="np-pagination">{{ $news->links('pagination::bootstrap-5') }}</div>
            @endif
        </section>

        @include('news.partials.sidebar')
    </div>
</main>
@endsection

@section('extra-css')
@include('news.partials.styles')
@endsection
