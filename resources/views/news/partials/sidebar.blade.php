<aside class="np-sidebar">
    <div class="np-card-box np-widget">
        <h3 class="np-title">Search</h3>
        <form class="np-search" action="{{ route('news.index') }}" method="GET">
            <input type="search" name="q" value="{{ $search ?? '' }}" placeholder="Search news…" aria-label="Search news">
            <button type="submit">Search</button>
        </form>
    </div>

    @isset($popular) {{-- Admin -> Most Popular News --}}
    <div class="np-card-box np-widget">
        <h3 class="np-title">Most Popular</h3>
        @forelse($popular as $item)
            <a class="np-popular" href="{{ $item->url }}">
                <span class="np-rank">{{ $loop->iteration }}</span>
                <img src="{{ $item->image_url }}" alt="{{ $item->title }}" loading="lazy">
                <div>
                    <h4>{{ $item->title }}</h4>
                    <small><span class="np-author notranslate" translate="no"><i class="bi bi-person-fill"></i> Nikhil Bhatt</span>{{ $item->display_date?->copy()->timezone('Asia/Kolkata')->format('d M Y, g:i A') }}</small>
                </div>
            </a>
        @empty
            <p class="np-muted">No articles yet.</p>
        @endforelse
    </div>
    @endisset

    @include('partials.premium-box')
</aside>
