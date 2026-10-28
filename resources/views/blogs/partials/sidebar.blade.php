<aside class="np-sidebar">
    <div class="np-card-box np-widget bl-cta">
        <h3 class="np-title">Write for Us</h3>
        <p>Share your market views and ideas with Investment Point readers.</p>
        <a class="btn-custom primary" href="{{ route('blog.create') }}">Create Blog →</a>
    </div>

    @if(!empty($moreBlogs) && $moreBlogs->isNotEmpty())
        <div class="np-card-box np-widget">
            <h3 class="np-title">More Blogs</h3>
            @foreach($moreBlogs as $item)
                <a class="bl-side-item" href="{{ route('blog.show', $item->slug) }}">
                    <span class="np-rank">{{ $loop->iteration }}</span>
                    <div>
                        <h4>{{ $item->title }}</h4>
                        <small>{{ $item->approved_at?->format('d M Y') }}</small>
                    </div>
                </a>
            @endforeach
        </div>
    @endif

    @include('partials.premium-box')
</aside>
