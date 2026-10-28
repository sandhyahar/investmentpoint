<aside class="np-sidebar">

    @if($latest->isNotEmpty())
        <div class="np-card-box np-widget">
            <h3 class="np-title">{{ isset($analysis) ? 'More Analysis' : 'Latest Analysis' }}</h3>
            @foreach($latest as $item)
                <a class="np-popular" href="{{ $item->url }}">
                    <span class="np-rank">{{ $loop->iteration }}</span>
                    <img src="{{ $item->image_url }}" alt="{{ $item->title }}" loading="lazy">
                    <div>
                        <h4>{{ $item->title }}</h4>
                        @if($item->published_on)<small>{{ $item->published_on->format('d M Y') }}</small>@endif
                    </div>
                </a>
            @endforeach
        </div>
    @endif

    @include('partials.premium-box')
</aside>
