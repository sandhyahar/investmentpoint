{{-- Article page for Admin -> Latest News / Most Popular News. $item, $section, $related, $popular --}}
@extends('layouts.app')

@section('title', $item->title . ' | Investment Point')
@section('meta_description', \App\Support\Html::metaDescription($item->description, $item->body))
@section('og_image', $item->image_url)
@if($item->keywords)@section('meta_keywords', $item->keywords)@endif

@section('content')
@include('partials.article-schema', ['headline' => $item->title, 'description' => $item->description ?: $item->body, 'image' => $item->image_url, 'published' => $item->display_date, 'modified' => $item->updated_at, 'section' => $section, 'keywords' => $item->keywords])
<main class="np-page">
    <div class="np-wrap">
        <div>
            <div class="np-crumbs" aria-label="Breadcrumb">
                <a href="{{ route('home') }}">Home</a><i>›</i>
                <a href="{{ route('news.index') }}">News</a><i>›</i>
                {{ $item->title }}
            </div>

            <article class="np-card-box np-article">
                <span class="tag">{{ $section }}</span>
                <h1>{{ $item->title }}</h1>

                @include('partials.article-meta', ['date' => $item->display_date, 'views' => (int) $item->views, 'withTime' => true])

                <img class="np-hero" src="{{ $item->image_url }}" alt="{{ $item->title }}">
                @if($item->image_credit)
                    {{-- Real stock photo: the photographer's credit, as Pixabay / Pexels ask --}}
                    <p class="np-photo-credit">Photo: <a href="{{ $item->image_credit_url ?: 'https://pixabay.com' }}" target="_blank" rel="noopener nofollow">{{ $item->image_credit }}</a> / {{ str_contains((string) $item->image_credit_url, 'pexels.com') ? 'Pexels' : 'Pixabay' }}</p>
                @endif

                @include('partials.article-body', ['text' => $item->body])

                @if($item->keywords)
                    <div class="np-tags" aria-label="Topics">
                        @foreach(array_filter(array_map('trim', explode(',', $item->keywords))) as $tag)<span>{{ $tag }}</span>@endforeach
                    </div>
                @endif

                @include('partials.stock-disclaimer')
                @include('partials.share-buttons')
            </article>

            @if($related->isNotEmpty())
                <section class="np-related">
                    <h2 class="np-title">Related Articles</h2>
                    <div class="np-grid">
                        @foreach($related as $other)
                            <article class="np-card">
                                <a class="np-card-img" href="{{ $other->url }}">
                                    <img src="{{ $other->image_url }}" alt="{{ $other->title }}" loading="lazy">
                                </a>
                                <div class="np-card-body">
                                    <h3><a href="{{ $other->url }}">{{ $other->title }}</a></h3>
                                    <small>{{ $other->display_date?->copy()->timezone('Asia/Kolkata')->format('d M Y, g:i A') }}</small>
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
.np-tags{display:flex;flex-wrap:wrap;gap:6px;margin:18px 0 0}
.np-tags span{background:#f1f4fb;border:1px solid #dfe5f3;color:var(--navy);font-size:12px;font-weight:600;padding:4px 10px;border-radius:20px}
@endsection
