{{-- Editor's View sidebar: search, the writer, more articles ($more on the article page), the subscription box --}}
<aside class="np-sidebar">
    <div class="np-card-box np-widget">
        <h3 class="np-title">Search</h3>
        <form class="np-search" action="{{ route('editors-desk.index') }}" method="GET">
            <input type="search" name="q" value="{{ $search ?? '' }}" placeholder="Search articles…" aria-label="Search Editor's View">
            <button type="submit">Search</button>
        </form>
    </div>

    <div class="np-card-box np-widget">
        <h3 class="np-title">Written by</h3>
        <div class="ed-writer">
            <img src="{{ asset('images/nikhil-bhatt.png') }}" alt="Nikhil Bhatt" loading="lazy">
            <div>
                <b class="notranslate" translate="no">{{ config('subscription.plan.contact_name') }}</b>
                <small><i class="bi bi-briefcase-fill"></i> Business Editor</small>
            </div>
        </div>
    </div>

    @if(!empty($more) && $more->isNotEmpty())
    <div class="np-card-box np-widget">
        <h3 class="np-title">More from Editor's View</h3>
        @foreach($more as $item)
            <a class="np-popular" href="{{ $item->url }}">
                <span class="np-rank">{{ $loop->iteration }}</span>
                @if($item->image_url)<img src="{{ $item->image_url }}" alt="{{ $item->title }}" loading="lazy">@else<img class="ed-noimg" src="{{ asset('images/logo-default.png') }}" alt="Investment Point" loading="lazy">@endif
                <div>
                    <h4>{{ $item->title }}</h4>
                    <small>{{ $item->published_on->format('d M Y') }}</small>
                </div>
            </a>
        @endforeach
    </div>
    @endif

    @include('partials.premium-box')
</aside>
