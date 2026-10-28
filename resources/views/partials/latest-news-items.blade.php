{{-- Homepage "Business News" list (Admin -> Latest News): thumbnail, title and date, like the WordPress site.
     Also served by /latest-news so the box refreshes without a page reload. --}}
@php
    $newsImage = fn ($image) => $image
        ? (Str::startsWith($image, ['http://', 'https://']) ? $image : asset('storage/' . $image))
        : 'https://images.unsplash.com/photo-1611974789855-9c2a0a7236a3?auto=format&fit=crop&w=1200&q=80';
@endphp
@forelse($latestNews as $story)
    <article class="news-item" tabindex="0" role="link" aria-label="Open article: {{ $story['title'] }}" data-href="{{ $story['url'] }}">
        <img class="thumb" src="{{ $newsImage($story['image']) }}" alt="{{ $story['title'] }}" loading="lazy">
        <div>
            <h4><a href="{{ $story['url'] }}">{{ $story['title'] }}</a></h4>
            <span class="article-date"><span class="article-author notranslate" translate="no"><i class="bi bi-person-fill"></i> Nikhil Bhatt</span>{{-- Date and the time it was published (India time) --}}{{ $story['date']?->copy()->timezone('Asia/Kolkata')->format('d M Y, g:i A') ?? '' }}</span>
        </div>
    </article>
@empty
    <p class="muted">Business news will appear here soon.</p>
@endforelse
