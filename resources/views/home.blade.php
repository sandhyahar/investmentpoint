@extends('layouts.app')

@section('content')
@php
    $imageUrl = fn($image) => $image
        ? (Str::startsWith($image, ['http://', 'https://']) ? $image : asset('storage/' . $image))
        : 'https://images.unsplash.com/photo-1611974789855-9c2a0a7236a3?auto=format&fit=crop&w=1200&q=80';

@endphp

<main class="container">
    <section class="section">
        <div class="hero">
            @php $leadUrl = $featuredNews?->url ?? route('news.index'); @endphp
            <article class="lead" data-live-region="hero">
                {{-- Whole picture opens the full article (not only the button) --}}
                <a class="lead-link" href="{{ $leadUrl }}" aria-label="Read full article: {{ $featuredNews->title ?? 'Latest Market News' }}" tabindex="-1"></a>
                {{-- The biggest picture on the page: ask the browser to load it first --}}
                <img src="{{ $imageUrl($featuredNews->image ?? null) }}" alt="{{ $featuredNews->title ?? 'Featured news' }}" fetchpriority="high" decoding="async">
                <div class="leadtext">
                    {{-- Section box (the whole banner and the headline open the article) --}}
                    <div class="lead-tags">
                        <span class="tag">Market Trend</span>
                        {{-- When the Market Trend was updated (India time), as on its own page (partials.article-meta), and the writer --}}
                        <span class="lead-meta">
                            <span>{{ ($featuredNews?->created_at ?? now())->copy()->timezone('Asia/Kolkata')->format('d F Y, g:i A') }}</span>
                            <span class="notranslate" translate="no"><i class="bi bi-person-fill" aria-hidden="true"></i> {{ config('subscription.plan.contact_name') }}</span>
                        </span>
                    </div>
                    {{-- Headline only: no summary line under it (the owner's choice, 22 Oct 2026) --}}
                    <h1><a href="{{ $leadUrl }}">{{ $featuredNews->title ?? 'Latest Market News' }}</a></h1>
                </div>
            </article>

            <aside class="latest" id="news">
                <h3>Business News <a href="{{ route('news.index') }}" style="float:right;color:#2b3f9e;font-size:10px">View All →</a></h3>
                <div class="latest-news-scroll" id="latestNewsList" data-refresh-url="{{ route('news.latest') }}">
                    {{-- Admin -> Latest News, newest first; refreshed every few minutes (live RSS headlines are disabled) --}}
                    @include('partials.latest-news-items')
                </div>
            </aside>
        </div>
    </section>

    {{-- Technical analysis: 4 compact cards in one row with the Subscription card beside them --}}
    <section class="section">
        <div class="title"><h2>Latest Technical Analysis</h2><a href="{{ route('analysis.index') }}">View All Analysis →</a></div>
        <div class="ta-row">
            <div class="ta-grid" data-live-region="analysis">
                @forelse(($technicalAnalyses ?? collect())->take(4) as $analysis)
                    {{-- Home cards show the CMP only; support and resistance are on the analysis page --}}
                    @php $cmp = \App\Models\TechnicalAnalysis::formatLevel($analysis->cmp); @endphp
                    <article class="ta-card">
                        <a class="ta-img" href="{{ $analysis->url }}" tabindex="-1" aria-hidden="true">
                            <img src="{{ $analysis->image_url }}" alt="{{ $analysis->title }}" loading="lazy" decoding="async">
                            <span class="ta-badge">Technical</span>
                        </a>
                        <div class="ta-body">
                            {{-- The admin's date (Admin -> Technical Analysis -> Date); none entered = none shown --}}
                            @if($analysis->published_on)<span class="ta-date"><i class="bi bi-calendar3"></i> {{ $analysis->published_on->format('d M Y') }}</span>@endif
                            <h3><a href="{{ $analysis->url }}">{{ $analysis->title }}</a></h3>
                            @if($cmp)
                                <div class="ta-lv">
                                    <div class="ta-c"><small>CMP <span>(current market price)</span></small><b>{{ $cmp }}</b></div>
                                </div>
                            @else
                                <p class="ta-sum">{{ \App\Support\Html::plain($analysis->description, 90) }}</p>
                            @endif
                            <a class="ta-more" href="{{ $analysis->url }}">Read Analysis <i class="bi bi-arrow-right"></i></a>
                        </div>
                    </article>
                @empty
                    <p class="muted">No active technical analysis is available yet.</p>
                @endforelse
            </div>

            <div class="premium-column" id="subscription" data-live-region="premium">
                @if($premiumContent)
                    @php $premiumBenefits = is_array($premiumContent->benefits ?? null) ? $premiumContent->benefits : preg_split('/\r?\n/', (string) ($premiumContent->benefits ?? '')); @endphp
                    @php $contact = config('subscription.plan'); @endphp
                    <div class="premium">
                        <div class="premium-head">
                            <span class="premium-brand">Investment Point</span>
                            <h2>{{ $premiumContent->title ?? 'Unlock Premium Market Insights' }}</h2>
                        </div>
                        <p>{{ \App\Support\Html::plain($premiumContent->description) ?: 'Get access to premium market content and exclusive insights.' }}</p>
                        {{-- What members get after subscribing (the member page) --}}
                        <div class="premium-after">
                            <b><i class="bi bi-unlock-fill"></i> After you subscribe</b>
                            Log in with your WhatsApp number (OTP on WhatsApp) and see more technical analysis: date-wise stock prices, support &amp; resistance levels and public appearance for the stock as per SEBI guidelines.
                        </div>
                        {{-- Phones / tablets: the benefits, contact and terms stay folded until "Show more" --}}
                        <button type="button" class="premium-more" aria-expanded="false">
                            <span class="when-closed">Show more <i class="bi bi-chevron-down"></i></span>
                            <span class="when-open">Show less <i class="bi bi-chevron-up"></i></span>
                        </button>
                        {{-- Middle column: the benefits first, then the notes --}}
                        <div class="premium-mid">
                            <ul class="premium-list">
                                @foreach(array_filter($premiumBenefits ?? []) as $benefit)
                                    {{-- Items starting with an emoji (📊 Market Trend) show the emoji instead of a bullet --}}
                                    <li @class(['has-icon' => preg_match('/^[^\p{L}\p{N}\s]/u', trim($benefit))])>{{ $benefit }}</li>
                                @endforeach
                            </ul>
                            <div class="premium-note">
                                <div>🎯 Educational Purposes only</div>
                                <div>❌ No Buy/Sell Recommendations</div>
                            </div>
                        </div>
                        <div class="premium-contact">
                            <img src="{{ asset('images/nikhil-bhatt.png') }}" alt="{{ $contact['contact_name'] }}" loading="lazy" decoding="async">
                            <div>
                                <b>{{ $contact['contact_name'] }}</b>
                                <a class="notranslate" translate="no" href="tel:{{ $contact['contact_phone'] }}"><i class="bi bi-telephone-fill"></i> {{ preg_replace('/^\+91(\d{5})(\d{5})$/', '+91 $1 $2', $contact['contact_phone']) }}</a>
                            </div>
                        </div>
                        <div class="premium-terms">
                            <b>Terms &amp; Conditions:</b>
                            <p>Content is provided strictly subject to the <a href="{{ route('disclaimer') }}">Disclaimer</a> and all related Policies of investmentpoint.in. Use of this information implies acknowledgment of those terms and conditions.</p>
                        </div>
                        {{-- Subscription opens only after the visitor ticks the box --}}
                        <div class="premium-cta">
                            <label class="premium-agree">
                                <input type="checkbox" class="premium-agree-box">
                                <span>I agree to the Terms &amp; Conditions and the Disclaimer</span>
                            </label>
                            <a class="btn-custom gold is-locked" href="{{ route('subscription') }}" aria-disabled="true"><i class="bi bi-star-fill"></i> Subscription →</a>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </section>

    {{-- Market Trends: Top Gainers / Top Losers, with the Experts View (top blog writers) beside them --}}
    <section class="section" id="trends">
        <div class="title"><h2>Market Trends</h2></div>
        <div class="trends trends-two trends-three">
            <div class="card list">
                <h3>Top Gainers</h3>
                <div id="top-gainers-list"></div>
            </div>

            <div class="card list">
                <h3>Top Losers</h3>
                <div id="top-losers-list"></div>
            </div>

            {{-- 10 newest Expert's View items (approved blogs + articles, mixed); scrolls inside the card --}}
            <div class="card list ex-card">
                <div class="ex-head">
                    <h3>Experts View</h3>
                    <a class="ex-create-btn" href="{{ route('blog.create') }}"><i class="bi bi-pencil-square"></i> Create Blog</a>
                    @if($topExperts->count() > 4)<span class="ex-hint">Scroll <i class="bi bi-arrow-down"></i></span>@endif
                </div>
                <div class="ex-body"><div class="ex-scroll">
                    @foreach($topExperts as $item)
                        <a class="ex-item" href="{{ $item->url }}" title="{{ $item->title }}">
                            <img src="{{ $item->photo }}" alt="{{ $item->name }}" @class(['is-logo' => $item->is_logo]) loading="lazy" decoding="async">
                            <span class="ex-text">
                                <b>{{ $item->name }}</b>
                                <small class="ex-role">{{ $item->role }}</small>
                                <small class="ex-title">{{ $item->title }}</small>
                            </span>
                        </a>
                    @endforeach
                    @if($topExperts->isEmpty())<p class="muted">No articles yet. Be the first to write one!</p>@endif
                </div></div>
            </div>
        </div>
    </section>

    <section class="section" id="media" data-live-region="youtube">
        <div class="title"><h2>Latest from YouTube</h2><a href="https://www.youtube.com/c/NikhilBhattResearchAnalystSEBI" target="_blank" rel="noopener noreferrer">Visit YouTube Channel →</a></div>
        @if(($youtubeVideos ?? collect())->isNotEmpty())
            {{-- Auto-sliding loop; click a video to play it here (sliding pauses while it plays) --}}
            <div class="yt-slider" id="ytSlider" aria-roledescription="carousel" aria-label="Latest YouTube videos">
                <div class="yt-viewport">
                    <div class="yt-track">
                        @foreach($youtubeVideos as $video)
                            <div class="yt-slide" aria-roledescription="slide" aria-label="{{ $loop->iteration }} of {{ $loop->count }}">
                                <div class="video">
                                    @if($video->embed_url)
                                        {{-- Plays muted automatically; click turns on sound + controls --}}
                                        <div class="yt-frame yt-play" role="button" tabindex="0" data-muted="{{ $video->embed_url }}" data-player="{{ $video->player_url }}" data-thumb="{{ $video->thumbnail_url }}" aria-label="Play with sound: {{ $video->title }}">
                                            {{-- Thumbnail first; the player loads only once this slide is on screen (see loadVisible) --}}
                                            <img src="{{ $video->thumbnail_url }}" alt="{{ $video->title }} - Investment Point video" loading="lazy">
                                            <iframe title="{{ $video->title }}" tabindex="-1" allow="autoplay; encrypted-media; picture-in-picture; fullscreen"></iframe>
                                            <span class="yt-sound">🔊 Click for sound</span>
                                        </div>
                                    @else
                                        <a class="yt-frame" href="{{ $video->watch_url }}" target="_blank" rel="noopener noreferrer" aria-label="Watch on YouTube: {{ $video->title }}">
                                            <img src="{{ $video->thumbnail_url }}" alt="{{ $video->title }}" loading="lazy">
                                            <span class="play">▶</span>
                                        </a>
                                    @endif
                                    <div class="body">
                                        <a href="{{ $video->watch_url }}" target="_blank" rel="noopener noreferrer"><b>{{ $video->title }}</b></a><br>
                                        <small class="meta">@if($date = $video->published_date?->format('d M') ?? $video->publish_date?->format('d M')){{ $date }} · @endif<a class="yt-open" href="{{ $video->watch_url }}" target="_blank" rel="noopener noreferrer">Watch on YouTube ↗</a></small>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
                <button type="button" class="yt-nav yt-prev" aria-label="Previous video">‹</button>
                <button type="button" class="yt-nav yt-next" aria-label="Next video">›</button>
                <div class="yt-dots" role="tablist"></div>
            </div>
        @else
            <p class="muted">No YouTube videos have been added yet.</p>
        @endif
    </section>

    {{-- TRUSTED BY: the newspapers that publish Investment Point columns (Admin -> Media Articles). Each logo is
         the masthead of that paper's newest clipping; a click opens all of that paper's articles. Two rows slide
         in opposite directions; new papers appear by themselves. --}}
    @if(!empty($trustedBy))
    @php
        // Alternate the papers over two rows (both rows keep the "most articles first" order)
        $trustedRows = array_values(array_filter([
            array_values(array_filter($trustedBy, fn ($i) => $i % 2 === 0, ARRAY_FILTER_USE_KEY)),
            array_values(array_filter($trustedBy, fn ($i) => $i % 2 === 1, ARRAY_FILTER_USE_KEY)),
        ]));
    @endphp
    <section class="section trusted" id="trusted-by" aria-label="Trusted by">
        <div class="trusted-band">
            <div class="trusted-head">
                <span class="trusted-kicker"><i class="bi bi-patch-check-fill" aria-hidden="true"></i> Trusted by</span>
            </div>
            @foreach($trustedRows as $r => $papers)
                <div @class(['trusted-marquee', 'reverse' => $r === 1]) style="--count:{{ count($papers) }}">
                    <div class="trusted-track">
                        @foreach([false, true] as $copy)
                            @foreach($papers as $paper)
                                <a class="trusted-logo" href="{{ $paper['url'] }}" title="{{ !empty($paper['link']) ? $paper['label'] : 'All ' . $paper['label'] . ' articles' }}" @if(!empty($paper['link'])) target="_blank" rel="noopener noreferrer" @endif @if($copy) aria-hidden="true" tabindex="-1" @endif>
                                    <span @class(['trusted-mast', 'is-official' => $paper['official'] ?? false])>
                                        {{-- Not lazy: a lazy logo only starts loading as it slides in, so on a phone the cards went by empty --}}
                                        @if($paper['logo'])<img src="{{ $paper['logo'] }}" alt="{{ $copy ? '' : $paper['label'] . ' newspaper' }}" fetchpriority="low" decoding="async">@else<b>{{ $paper['label'] }}</b>@endif
                                    </span>
                                    <span class="trusted-name">{{ $paper['label'] }}</span>
                                </a>
                            @endforeach
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    </section>
    @endif
</main>
@endsection

@section('extra-css')
{{-- Raw CSS: layouts.app yields extra-css inside its own <style> tag --}}
    /* ---- Latest Technical Analysis ----
       Desktop: 4 compact cards in one row, the Subscription card as a wide banner right under them
       (side by side, the taller Subscription card would leave empty space in the cards).
       Tablet: 2 cards per row. Phone: compact list cards (picture left, details right).
       Pictures keep a landscape 16:10 shape. */
    .ta-row{display:grid;grid-template-columns:1fr;gap:18px}
    .ta-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:16px;align-items:stretch}
    .ta-grid>.muted{grid-column:1/-1}
    .ta-card{display:flex;flex-direction:column;background:#fff;border:1px solid #e3e8f2;border-radius:14px;overflow:hidden;box-shadow:0 4px 14px rgba(20,31,85,.06);transition:transform .2s ease,box-shadow .2s ease}
    .ta-card:hover{transform:translateY(-3px);box-shadow:0 14px 30px rgba(20,31,85,.13)}
    /* The picture keeps its own shape (wide banner, 16:9, ...) so nothing is cut off at any screen size;
       only a very tall picture is limited, and then shown whole inside the box */
    .ta-img{position:relative;display:block;overflow:hidden;background:#e9edf6}
    .ta-img img{width:100%;height:auto;max-height:300px;object-fit:contain;display:block;transition:transform .35s ease}
    .ta-card:hover .ta-img img{transform:scale(1.04)}
    .ta-badge{position:absolute;left:10px;top:10px;background:rgba(20,31,85,.88);color:#fff;font-size:9.5px;font-weight:800;letter-spacing:.7px;text-transform:uppercase;padding:4px 8px;border-radius:6px;backdrop-filter:blur(2px)}
    .ta-body{display:flex;flex-direction:column;flex:1;padding:12px 14px 14px}
    .ta-date{font-size:11.5px;font-weight:600;color:#7a849c}
    .ta-date i{font-size:10.5px;margin-right:3px}
    .ta-body h3{font-size:14.5px;line-height:1.38;font-weight:800;margin:5px 0 10px;min-height:2.76em;color:#141f55;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}
    .ta-body h3 a{color:inherit}
    .ta-body h3 a:hover{color:var(--blue)}
    /* Support / Resistance: two small value boxes side by side */
    .ta-lv{display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:12px}
    .ta-lv>div{border-radius:9px;padding:6px 9px;border:1px solid}
    .ta-lv small{display:block;font-size:9.5px;font-weight:800;letter-spacing:.6px;text-transform:uppercase}
    .ta-lv b{display:block;font-size:14.5px;font-weight:800;color:#141f55;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
    .ta-s{background:#fdf1f1;border-color:#f6d5d5!important}
    .ta-s small{color:#c42b30}
    .ta-r{background:#eef2fc;border-color:#d6def5!important}
    .ta-r small{color:#2b3f9e}
    .ta-lv>div:only-child{grid-column:1/-1}
    /* CMP: neutral, full width above support / resistance */
    .ta-c{grid-column:1/-1;background:#f6f7fb;border-color:#e3e7f2!important}
    .ta-c small{color:#4b5675}
    .ta-c small span{font-weight:600;letter-spacing:0;text-transform:none}
    .ta-c+div:last-child{grid-column:1/-1}
    .ta-sum{font-size:12.5px;color:#5b6478;line-height:1.5;margin:0 0 12px}
    .ta-more{margin-top:auto;font-size:12.5px;font-weight:800;color:var(--blue);display:inline-flex;align-items:center;gap:5px}
    .ta-more i{transition:transform .2s ease}
    .ta-card:hover .ta-more i{transform:translateX(3px)}

    /* Subscription banner: about | benefits | notes + button */
    .ta-row .premium-column{display:flex}
    .ta-row .premium{width:100%;border-radius:16px;padding:22px 26px;display:grid;grid-template-columns:1.15fr 1fr 1.25fr;column-gap:30px;align-content:center;position:relative;overflow:hidden}
    .ta-row .premium::after{content:"";position:absolute;right:-60px;top:-60px;width:220px;height:220px;border-radius:50%;background:radial-gradient(circle,rgba(255,211,106,.16),transparent 70%);pointer-events:none}
    .ta-row .premium>.premium-head{grid-column:1;grid-row:1;align-self:end;margin-bottom:6px}
    .premium-brand{display:block;color:#6ee7b7;font-size:13px;font-weight:800;letter-spacing:1.2px;text-transform:uppercase;margin-bottom:2px}
    .ta-row .premium-head h2{font-size:26px;margin:0}
    .ta-row .premium>p{grid-column:1;grid-row:2;font-size:15px;margin:0 0 12px}
    .ta-row .premium>.premium-after{grid-column:1;grid-row:3 / span 2;align-self:start}
    .premium-after{background:rgba(255,255,255,.08);border:1px solid rgba(255,211,106,.35);border-radius:10px;padding:11px 14px;font-size:14px;line-height:1.55;color:#e6ecf7}
    .premium-after b{display:block;color:#ffd36a;font-size:15px;margin-bottom:3px}
    /* Middle column: Educational / No Buy-Sell notes above the benefits */
    /* Middle column: a box with a golden border; benefits and notes start at the same left edge */
    .ta-row .premium>.premium-mid{grid-column:2;grid-row:1 / span 4;align-self:center;border:1px solid var(--gold);border-radius:12px;padding:12px 20px;background:rgba(255,255,255,.04)}
    .premium-mid .premium-list{margin:0 0 6px;padding-left:0}
    .premium-mid .premium-note{border-top:1px solid var(--gold);padding-top:12px;margin:0;font-size:15px;gap:7px}
    .ta-row .premium-mid .premium-list li{font-size:15.5px;margin:9px 0}
    /* Right column: photo + contact, terms, agree box + button */
    .ta-row .premium>.premium-contact{grid-column:3;grid-row:1}
    .premium-contact{display:flex;align-items:center;gap:14px;margin-bottom:12px}
    .premium-contact img{width:74px;height:74px;flex-shrink:0;border-radius:50%;object-fit:cover;object-position:center 20%;background:#fff;border:3px solid #ffd36a;box-shadow:0 6px 16px rgba(0,0,0,.25)}
    .premium-contact b{display:block;font-size:18px;color:#fff;font-weight:800}
    .premium-contact a{display:inline-flex;align-items:center;gap:6px;margin-top:3px;font-size:15px;font-weight:700;color:#ffd36a}
    .premium-contact a:hover{color:#fff}
    .ta-row .premium>.premium-terms{grid-column:3;grid-row:2 / span 2;margin:0 0 10px;align-self:start}
    .ta-row .premium .premium-terms b{font-size:14px}
    .ta-row .premium .premium-terms p{font-size:13px}
    .ta-row .premium>.premium-cta{grid-column:3;grid-row:4;align-self:start}
    .premium-agree{display:flex;align-items:flex-start;gap:8px;margin:0 0 10px;font-size:13.5px;font-weight:600;color:#e6ecf7;cursor:pointer;line-height:1.4}
    .premium-agree input{width:17px;height:17px;margin:1px 0 0;flex-shrink:0;accent-color:#ffd36a;cursor:pointer}
    .premium-cta .btn-custom{display:block;width:100%;margin:0;text-align:center;font-size:15px;padding:11px 13px}
    .premium-cta .btn-custom.is-locked{opacity:.55;cursor:not-allowed;filter:grayscale(.3)}
    .premium-agree.shake{animation:agreeShake .35s}
    @keyframes agreeShake{25%{transform:translateX(-5px)}75%{transform:translateX(5px)}}

    @media(max-width:1100px){
        .ta-grid{grid-template-columns:repeat(2,minmax(0,1fr))}
    }
    @media(max-width:900px){
        .ta-row .premium{display:flex;flex-direction:column;padding:20px}
        .ta-row .premium>.premium-mid{margin:14px 0;align-self:stretch}
        .ta-row .premium>.premium-head{align-self:stretch}
        .ta-row .premium-head h2{font-size:23px}
        .ta-row .premium>.premium-after{align-self:stretch}
        .ta-row .premium>.premium-cta{margin-top:6px}
        /* Short banner: benefits, contact and terms open with "Show more" (the open state is kept on <html>,
           so it stays open when the banner refreshes live) */
        .ta-row .premium-more{display:inline-flex;align-self:flex-start;align-items:center;gap:6px;margin:12px 0 4px;padding:7px 14px;border:1px solid rgba(255,211,106,.55);border-radius:20px;background:rgba(255,255,255,.06);color:#ffd36a;font-size:14px;font-weight:700;cursor:pointer}
        .ta-row .premium-more:hover{background:rgba(255,211,106,.14)}
        html:not(.premium-open) .ta-row .premium>.premium-mid,
        html:not(.premium-open) .ta-row .premium>.premium-contact,
        html:not(.premium-open) .ta-row .premium>.premium-terms{display:none}
        html:not(.premium-open) .ta-row .premium>.premium-cta{margin-top:10px}
        html.premium-open .ta-row .premium-more{order:1;align-self:center;margin:14px 0 0}
        html.premium-open .ta-row .premium-more .when-closed,
        html:not(.premium-open) .ta-row .premium-more .when-open{display:none}
    }
    .premium-more{display:none}
    @media(max-width:560px){
        /* Phones: the same card as on a computer, one under the other, with the whole picture on top */
        .ta-grid{grid-template-columns:1fr;gap:14px}
        .ta-card:hover{transform:none}
        .ta-badge{left:8px;top:8px;font-size:10px;padding:3px 7px}
        .ta-body{padding:10px 12px}
        .ta-body h3{font-size:13.5px;margin:3px 0 8px}
        .ta-lv{gap:6px;margin-bottom:8px}
        .ta-lv>div{padding:4px 7px}
        .ta-lv b{font-size:13px}
    }

    .trends-two{grid-template-columns:1fr 1fr; gap:18px;}
    .trends-two .card.list{
        background:#f7f8fb;
        border:1px solid #dfe5ef;
        border-radius:16px;
        min-height:190px;
        box-shadow:none;
        padding:18px 20px;
    }
    .trends-two .card.list h3{
        margin:0 0 14px;
        font-size:26px;
        line-height:1.2;
        font-weight:800;
        color:#111827;
    }
    .trends-two .card.list .stock,
    .trends-two .card.list .muted{
        font-size:15px;
        line-height:1.6;
    }
    .trends-two .card.list .stock{padding:9px 0;border-bottom:1px solid #e8edf5;display:flex;justify-content:space-between;align-items:center;gap:12px;}
    .trends-two .card.list .stock:last-child{border-bottom:0;}
    /* Name + price: one block that wraps cleanly (price moves under the name when there's no room) */
    .trends-two .card.list .stock b{display:flex;flex-wrap:wrap;align-items:center;gap:4px 14px;min-width:0;font-size:15px;font-weight:800;color:#111827;}
    /* Price: larger, bold, in a light chip so it stands out next to the name */
    .trends-two .card.list .stock b small{display:inline-block;font-weight:800;font-size:14px;color:#1f2a44;background:#eef1f8;border-radius:6px;padding:2px 8px;margin:0;letter-spacing:.2px;}
    /* % change as a coloured pill with an arrow, so gains / losses stand out */
    .trends-two .card.list .stock .chg{flex-shrink:0;display:inline-flex;align-items:center;gap:4px;font-size:14px;font-weight:800;padding:4px 10px;border-radius:20px;min-width:88px;justify-content:center}
    .trends-two .card.list .stock .chg.up{background:#e3f7ee;color:#08794c}
    .trends-two .card.list .stock .chg.down{background:#fdecec;color:#c42b30}
    .trends-two .card.list .stock .chg i{font-size:10px}
    /* Experts View: third card, same height as the gainers / losers cards; its list scrolls inside */
    .trends-three{grid-template-columns:1fr 1fr minmax(0,.9fr)}
    .trends-two .ex-card{display:flex;flex-direction:column;min-width:0}
    .ex-head{display:flex;align-items:center;flex-wrap:wrap;gap:8px 12px;margin-bottom:14px}
    .trends-two .ex-card .ex-head h3{margin:0}
    /* Create Blog: right after the title, on the left */
    .ex-create-btn{display:inline-flex;align-items:center;gap:6px;font-size:13px;font-weight:800;color:#fff;background:var(--blue);border-radius:8px;padding:6px 12px;white-space:nowrap;transition:background .15s ease}
    .ex-create-btn:hover{background:#22327f;color:#fff}
    .ex-hint{margin-left:auto;font-size:12px;font-weight:800;color:var(--blue);white-space:nowrap}
    /* The list fills the height the gainers / losers cards give the row (never stretches it) and scrolls */
    .ex-body{position:relative;flex:1;min-height:210px}
    .ex-scroll{position:absolute;inset:0;overflow-y:auto;margin-right:-10px;padding-right:6px;overscroll-behavior:contain;scrollbar-width:thin}
    .ex-item{display:flex;align-items:center;gap:12px;padding:10px 0;border-bottom:1px solid #e8edf5;color:inherit}
    .ex-item:last-child{border-bottom:0}
    .ex-item img{width:46px;height:46px;flex-shrink:0;border-radius:50%;object-fit:cover;background:#fff;border:1px solid #e3e8f2}
    .ex-item img.is-logo{object-fit:contain;padding:5px}
    .ex-text{display:flex;flex-direction:column;min-width:0}
    .ex-text b{font-size:15px;font-weight:800;color:#111827;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
    .ex-text small{font-size:12.5px;line-height:1.45;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
    .ex-role{color:#4a6fa5;font-weight:600}
    .ex-title{color:#5b6478}
    .ex-item:hover b{color:var(--blue)}
    @media(max-width:1100px){
        .trends-three{grid-template-columns:1fr 1fr}
        .trends-three .ex-card{grid-column:1/-1}
        /* Full-width row of its own: a fixed-height scrolling list */
        .ex-body{min-height:0}
        .ex-scroll{position:static;max-height:300px}
    }
    @media(max-width:720px){
        .trends-two{grid-template-columns:1fr;}
        .trends-three .ex-card{grid-column:auto}
        /* Phones: name on top, price chip under it, % pill on the right - same shape for every row */
        .trends-two .card.list{min-height:0;padding:16px;}
        .trends-two .card.list h3{font-size:21px;margin-bottom:8px;}
        .trends-two .card.list .stock{padding:10px 0;}
        .trends-two .card.list .stock b{flex-direction:column;align-items:flex-start;gap:5px;font-size:14px;}
        .trends-two .card.list .stock b small{font-size:13px;}
        .trends-two .card.list .stock .chg{min-width:78px;font-size:13px;padding:4px 8px;}
    }

    .news-source{display:inline-block;background:#eef1fb;color:#2b3f9e;font-weight:700;font-size:10px;padding:1px 6px;border-radius:4px}

    /* YouTube auto slider */
    .yt-slider{position:relative;--per:3;--gap:16px}
    .yt-viewport{overflow:hidden;border-radius:12px}
    .yt-track{display:flex;gap:var(--gap);transition:transform .6s ease;will-change:transform}
    .yt-slide{flex:0 0 calc((100% - (var(--per) - 1) * var(--gap)) / var(--per));min-width:0}
    .yt-slide .video{margin:0;height:100%;border:1px solid #dfe5ef;border-radius:12px;box-shadow:var(--shadow)}
    .yt-slide .yt-frame{border-radius:12px 12px 0 0}
    .yt-slide .yt-frame iframe{pointer-events:none}
    .yt-slide .yt-frame.is-playing iframe{pointer-events:auto}
    .yt-sound{position:absolute;right:10px;bottom:10px;z-index:2;background:rgba(0,0,0,.65);color:#fff;font-size:12px;font-weight:700;padding:6px 10px;border-radius:6px;pointer-events:none}
    .yt-frame.is-playing .yt-sound{display:none}
    .yt-slide .body{padding:10px 14px 12px}
    .yt-slide .body b{font-size:14px}
    .yt-nav{position:absolute;top:calc(50% - 30px);transform:translateY(-50%);z-index:3;width:38px;height:38px;border-radius:50%;border:0;background:rgba(20,31,85,.85);color:#fff;font-size:24px;line-height:1;cursor:pointer;box-shadow:var(--shadow)}
    .yt-nav:hover{background:#24378f}
    .yt-prev{left:-12px}.yt-next{right:-12px}
    /* Dots look 9px but each button is a 24px finger-sized target */
    .yt-dots{display:flex;justify-content:center;gap:0;margin-top:6px}
    .yt-dots button{position:relative;width:24px;height:24px;border:0;padding:0;background:none;cursor:pointer}
    .yt-dots button::before{content:"";position:absolute;left:50%;top:50%;width:9px;height:9px;transform:translate(-50%,-50%);border-radius:50%;background:#c7cfe2;transition:width .2s}
    .yt-dots button[aria-selected="true"]{width:32px}
    .yt-dots button[aria-selected="true"]::before{width:22px;border-radius:5px;background:#2b3f9e}
    .yt-slider.static .yt-nav,.yt-slider.static .yt-dots{display:none}
    @media(max-width:600px){.yt-prev{left:4px}.yt-next{right:4px}}

    /* ---- Trusted by: newspaper logos ----
       A framed band with two endless rows sliding in opposite directions (each list is in its row twice; the
       row moves by exactly one copy and repeats). A row stops while the mouse is on it or a logo has keyboard
       focus. Each logo = the masthead (top strip) of that paper's newest clipping, on a clean white card.
       With "reduce motion" the logos simply wrap onto several rows. */
    .trusted-band{position:relative;overflow:hidden;text-align:center;border-radius:24px;padding:34px 0 26px;
        background:radial-gradient(circle at 12% 0%,rgba(244,189,50,.14),transparent 38%),radial-gradient(circle at 88% 100%,rgba(74,99,216,.12),transparent 42%),linear-gradient(180deg,#fff,#f6f8fd);
        border:1px solid #e3e8f2;box-shadow:0 18px 44px rgba(20,31,85,.08)}
    .trusted-band::before{content:"";position:absolute;left:50%;top:0;width:min(420px,60%);height:3px;transform:translateX(-50%);border-radius:0 0 4px 4px;background:linear-gradient(90deg,transparent,var(--gold),transparent)}
    .trusted-head{max-width:760px;margin:0 auto 22px;padding:0 16px}
    .trusted-kicker{display:inline-flex;align-items:center;gap:6px;font-size:11.5px;font-weight:800;letter-spacing:1.6px;text-transform:uppercase;color:var(--blue);background:var(--blue2);padding:6px 14px;border-radius:30px}
    .trusted-kicker i{color:var(--gold)}
    .trusted-marquee{position:relative;overflow:hidden;padding:8px 0 10px;
        -webkit-mask-image:linear-gradient(90deg,transparent,#000 7%,#000 93%,transparent);mask-image:linear-gradient(90deg,transparent,#000 7%,#000 93%,transparent)}
    .trusted-track{--gap:18px;display:flex;gap:var(--gap);width:max-content;animation:trusted-slide calc(var(--count) * 3.6s) linear infinite}
    .trusted-marquee.reverse .trusted-track{animation-direction:reverse}
    /* Only with a mouse: on a phone a tap leaves the row "hovered" and it would stay stopped */
    @media(hover:hover){.trusted-marquee:hover .trusted-track{animation-play-state:paused}}
    .trusted-marquee:has(:focus-visible) .trusted-track{animation-play-state:paused}
    @keyframes trusted-slide{to{transform:translateX(calc(-50% - var(--gap) / 2))}}
    .trusted-logo{flex:0 0 236px;display:flex;flex-direction:column;align-items:center;gap:9px;background:#fff;border:1px solid #e6eaf3;border-radius:16px;padding:10px 10px 11px;
        box-shadow:0 6px 18px rgba(20,31,85,.07);color:var(--navy);text-decoration:none;transition:transform .25s ease,box-shadow .25s ease,border-color .25s ease}
    .trusted-logo:hover,.trusted-logo:focus-visible{transform:translateY(-5px);border-color:var(--gold);box-shadow:0 0 0 3px rgba(244,189,50,.25),0 18px 34px rgba(20,31,85,.16);color:var(--blue)}
    .trusted-mast{display:flex;align-items:center;justify-content:center;width:100%;aspect-ratio:3/1;overflow:hidden;border-radius:10px;background:#fff}
    .trusted-mast img{width:100%;height:100%;object-fit:cover;object-position:top center;display:block;transition:transform .35s ease}
    .trusted-logo:hover .trusted-mast img{transform:scale(1.04)}
    .trusted-mast b{font-size:15px;font-weight:800;padding:0 8px}
    /* An official logo (Admin -> Newspaper Logos) is shown whole, centred, with breathing room */
    .trusted-mast.is-official{padding:10px 16px}
    .trusted-mast.is-official img{object-fit:contain;object-position:center}
    .trusted-name{font-size:11.5px;font-weight:800;letter-spacing:.9px;line-height:1.25;text-transform:uppercase;color:#5f6b85}
    .trusted-logo:hover .trusted-name{color:var(--blue)}
    @media(max-width:720px){
        .trusted-band{border-radius:18px;padding:26px 0 18px}
        .trusted-track{--gap:12px}
        .trusted-logo{flex-basis:168px;padding:8px 8px 9px;border-radius:13px;gap:7px}
        .trusted-name{font-size:10.5px;letter-spacing:.6px}
    }
    @media(prefers-reduced-motion:reduce){
        .trusted-marquee{-webkit-mask-image:none;mask-image:none}
        .trusted-track{animation:none;width:auto;flex-wrap:wrap;justify-content:center;padding:0 12px}
        .trusted-logo[aria-hidden="true"]{display:none}
    }
@endsection

@section('extra-js')
<script>
(function () {
    const esc = s => String(s).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    const price = n => '₹' + Number(n).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    // The day's change in rupees (the pill shows this; the percentage is its tooltip)
    const rupees = s => s.change != null ? Number(s.change) : Number(s.price) - Number(s.previous_close != null ? s.previous_close : s.price);

    function rows(list, cls, sign, empty) {
        return list.length
            ? list.map(s => '<div class="stock"><b>' + esc(s.name) + ' <small class="muted notranslate" translate="no">' + price(s.price) + '</small></b><span class="chg ' + cls + ' notranslate" translate="no" title="' + sign + Math.abs(s.change_percent).toFixed(2) + '%"><i class="bi bi-caret-' + (cls === 'up' ? 'up' : 'down') + '-fill"></i>' + sign + price(Math.abs(rupees(s))) + '</span></div>').join('')
            : '<p class="muted">' + empty + '</p>';
    }

    function renderMovers(snapshot) {
        if (!snapshot) return;
        document.getElementById('top-gainers-list').innerHTML = rows(snapshot.gainers || [], 'up', '+', 'No gainers right now.');
        document.getElementById('top-losers-list').innerHTML = rows(snapshot.losers || [], 'down', '-', 'No losers right now.');
    }

    renderMovers(window.MARKET_SNAPSHOT);
    document.addEventListener('market:update', e => renderMovers(e.detail));
})();
</script>
<script>
// Subscription banner: the button works only after "I agree" is ticked.
// Delegated on the document, so it keeps working after the premium region refreshes live.
(function () {
    document.addEventListener('change', function (e) {
        if (!e.target.matches('.premium-agree-box')) return;
        const btn = e.target.closest('.premium-cta').querySelector('.btn-custom');
        btn.classList.toggle('is-locked', !e.target.checked);
        btn.setAttribute('aria-disabled', e.target.checked ? 'false' : 'true');
    });
    // Phones / tablets: "Show more" opens the rest of the banner, "Show less" folds it again
    document.addEventListener('click', function (e) {
        const more = e.target.closest('.premium-more');
        if (!more) return;
        const open = document.documentElement.classList.toggle('premium-open');
        document.querySelectorAll('.premium-more').forEach(b => b.setAttribute('aria-expanded', open ? 'true' : 'false'));
        if (!open) more.closest('.premium').scrollIntoView({ block: 'nearest', behavior: 'smooth' });
    });
    document.addEventListener('click', function (e) {
        const btn = e.target.closest('.premium-cta .btn-custom.is-locked');
        if (!btn) return;
        e.preventDefault();
        const agree = btn.closest('.premium-cta').querySelector('.premium-agree');
        agree.classList.remove('shake');
        agree.offsetWidth; // restart the animation
        agree.classList.add('shake');
        agree.querySelector('input').focus();
    });
})();
</script>
<script>
// YouTube slider: moves automatically in an endless loop. Clicking a thumbnail plays the video
// in place and pauses the sliding; moving to another slide stops the video and resumes sliding.
// Restartable: runs again when the admin changes the videos (live update, no page reload).
function initYtSlider() {
    if (window.__ytSliderStop) window.__ytSliderStop.abort(); // stop the previous instance
    const stop = new AbortController();
    window.__ytSliderStop = stop;
    const slider = document.getElementById('ytSlider');
    if (!slider) return;
    const track = slider.querySelector('.yt-track');
    const dotsBox = slider.querySelector('.yt-dots');
    const originals = Array.from(track.children);
    const count = originals.length;
    const DELAY = 4000;
    let per = 1, index = 0, timer = null, playing = false, hovered = false;

    function perView() {
        const want = window.innerWidth >= 1000 ? 3 : (window.innerWidth >= 700 ? 2 : 1);
        return Math.max(1, Math.min(want, count - 1)); // always leave something to slide to
    }

    // Back to the muted auto-playing preview
    function stopVideos() {
        track.querySelectorAll('.yt-frame.is-playing').forEach(function (frame) {
            frame.classList.remove('is-playing');
            frame.querySelector('iframe').src = frame.dataset.muted;
        });
        playing = false;
    }

    function go(i, animate) {
        track.style.transition = animate === false ? 'none' : '';
        index = i;
        const slide = track.children[0];
        const step = slide.getBoundingClientRect().width + parseFloat(getComputedStyle(track).columnGap || 0);
        track.style.transform = 'translateX(' + (-index * step) + 'px)';
        const active = ((index % count) + count) % count;
        dotsBox.querySelectorAll('button').forEach((d, n) => d.setAttribute('aria-selected', n === active ? 'true' : 'false'));
        loadVisible();
        playVisible();
    }

    // YouTube players are heavy (~1 MB each), so a slide's player is only created when the
    // slider is near the screen and that slide is showing; until then its thumbnail is shown.
    let inView = false;
    function loadVisible() {
        if (!inView) return;
        Array.from(track.children).slice(index, index + per).forEach(function (slide) {
            const frame = slide.querySelector('.yt-play');
            const iframe = frame && frame.querySelector('iframe');
            if (iframe && !iframe.getAttribute('src')) iframe.src = frame.dataset.muted;
        });
    }
    if ('IntersectionObserver' in window) {
        const io = new IntersectionObserver(function (entries) {
            inView = entries[0].isIntersecting;
            loadVisible();
        }, { rootMargin: '200px 0px' });
        io.observe(slider);
        stop.signal.addEventListener('abort', () => io.disconnect());
    } else {
        inView = true;
    }

    // Nudge every visible muted preview to play (browsers can hold back players that loaded off-screen)
    function playVisible() {
        Array.from(track.children).slice(index, index + per).forEach(function (slide) {
            const frame = slide.querySelector('.yt-play:not(.is-playing) iframe');
            if (frame && frame.contentWindow) {
                frame.contentWindow.postMessage(JSON.stringify({ event: 'command', func: 'playVideo', args: [] }), '*');
            }
        });
    }
    track.addEventListener('load', function (e) {
        const iframe = e.target;
        if (iframe.tagName !== 'IFRAME' || !iframe.getAttribute('src')) return;
        // Subscribe to player events so we hear about errors, then make sure visible ones play
        iframe.contentWindow.postMessage(JSON.stringify({ event: 'listening', id: 1, channel: 'widget' }), '*');
        playVisible();
        // A working player answers within a few seconds. No answer = YouTube couldn't be reached
        // (network / DNS / ad-blocker / firewall): show the thumbnail instead of a browser error box.
        iframe._alive = false;
        clearTimeout(iframe._watchdog);
        iframe._watchdog = setTimeout(function () {
            if (!iframe._alive && iframe.isConnected) showFallback(iframe.closest('.yt-play'));
        }, 8000);
    }, true); // capture: covers the loop clones too

    // If YouTube refuses a video (removed, private, blocked in this region/site) or can't be
    // reached, show its thumbnail with a "Watch on YouTube" button instead of an error box
    window.addEventListener('message', function (e) {
        let host;
        try { host = new URL(e.origin).hostname; } catch (err) { return; }
        if (!/(^|\.)youtube(-nocookie)?\.com$/.test(host)) return;
        const iframe = Array.from(track.querySelectorAll('.yt-play iframe')).find(f => f.contentWindow === e.source);
        if (!iframe) return;
        iframe._alive = true; // the player is talking to us, so it loaded
        let data;
        try { data = JSON.parse(e.data); } catch (err) { return; }
        if (data.event === 'onError') showFallback(iframe.closest('.yt-play'));
    }, { signal: stop.signal });

    function showFallback(frame) {
        if (!frame) return;
        const link = frame.closest('.video').querySelector('.yt-open');
        const fallback = document.createElement('a');
        fallback.className = 'yt-frame';
        fallback.href = link ? link.href : 'https://www.youtube.com/';
        fallback.target = '_blank';
        fallback.rel = 'noopener noreferrer';
        const videoTitle = (frame.closest('.video').querySelector('b') || {}).textContent || 'Investment Point video';
        fallback.setAttribute('aria-label', 'Watch on YouTube: ' + videoTitle);
        fallback.innerHTML = '<img src="' + frame.dataset.thumb + '" alt=""><span class="play">▶</span>';
        fallback.querySelector('img').alt = videoTitle; // set as a property, so quotes in titles are safe
        if (frame.classList.contains('is-playing')) playing = false;
        frame.replaceWith(fallback);
    }

    function next() { stopVideos(); go(index + 1); }
    function prev() {
        stopVideos();
        if (index === 0) {
            go(count, false);   // jump invisibly onto the clones (same picture as slide 0)...
            track.offsetWidth;  // ...force the browser to apply it...
        }
        go(index - 1);          // ...then slide back to the last real video
    }

    // After sliding onto the clones at the end, jump back to the real first slide invisibly
    track.addEventListener('transitionend', function () {
        if (index >= count) go(index - count, false);
    });

    function schedule() {
        clearInterval(timer);
        if (count > per) timer = setInterval(function () { if (!playing && !hovered && !document.hidden) next(); }, DELAY);
    }

    function build() {
        per = perView();
        slider.style.setProperty('--per', per);
        track.querySelectorAll('.yt-clone').forEach(c => c.remove());
        slider.classList.toggle('static', count <= per);
        if (count > per) {
            originals.slice(0, per).forEach(function (s) {
                const clone = s.cloneNode(true);
                clone.classList.add('yt-clone');
                clone.setAttribute('aria-hidden', 'true');
                track.appendChild(clone);
            });
        }
        dotsBox.innerHTML = originals.map((_, n) => '<button type="button" role="tab" aria-label="Go to video ' + (n + 1) + '"></button>').join('');
        dotsBox.querySelectorAll('button').forEach((d, n) => d.addEventListener('click', () => { stopVideos(); go(n); schedule(); }));
        go(Math.min(index % count, count - 1), false);
        schedule();
    }

    // Click (or Enter) on a preview: reload it with sound + controls and pause the sliding
    function playWithSound(frame) {
        if (!frame || frame.classList.contains('is-playing')) return;
        stopVideos();
        frame.querySelector('iframe').src = frame.dataset.player;
        frame.classList.add('is-playing');
        playing = true;
    }
    track.addEventListener('click', e => playWithSound(e.target.closest('.yt-play')));
    track.addEventListener('keydown', e => { if (e.key === 'Enter') playWithSound(e.target.closest('.yt-play')); });

    slider.querySelector('.yt-next').addEventListener('click', () => { next(); schedule(); });
    slider.querySelector('.yt-prev').addEventListener('click', () => { prev(); schedule(); });
    slider.addEventListener('mouseenter', () => { hovered = true; });
    slider.addEventListener('mouseleave', () => { hovered = false; });

    // Swipe on touch screens
    let startX = null;
    slider.addEventListener('touchstart', e => { startX = e.touches[0].clientX; }, { passive: true });
    slider.addEventListener('touchend', function (e) {
        if (startX === null) return;
        const dx = e.changedTouches[0].clientX - startX;
        if (Math.abs(dx) > 40) { dx < 0 ? next() : prev(); schedule(); }
        startX = null;
    });

    let resizeTimer;
    window.addEventListener('resize', function () {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(function () { if (perView() !== per) { stopVideos(); build(); } else go(index, false); }, 150);
    }, { signal: stop.signal });
    stop.signal.addEventListener('abort', function () { clearInterval(timer); clearTimeout(resizeTimer); });

    build();
}
initYtSlider();
document.addEventListener('live:region-updated', function (e) { if (e.detail.key === 'youtube') initYtSlider(); });
</script>
<script>
// Whole news card is clickable (and keyboard-accessible), not just the "Read full article" link
// Live headlines (data-external) open the publisher's page in a new tab.
// Delegated on the list, so it keeps working after the list refreshes.
(function () {
    const list = document.getElementById('latestNewsList');
    if (!list) return;

    // Live headlines leave the site, so count the click here (admin news count on their own page)
    function countClick(item) {
        if (!item.dataset.external || item.dataset.counted) return;
        item.dataset.counted = '1';
        const data = new FormData();
        data.append('_token', @json(csrf_token()));
        data.append('url', item.dataset.href);
        navigator.sendBeacon(@json(route('news.click')), data);
        const badge = item.querySelector('.view-count');
        if (badge) {
            const n = (parseInt(badge.textContent.replace(/\D/g, ''), 10) || 0) + 1;
            badge.innerHTML = '<i class="bi bi-eye"></i> ' + n.toLocaleString('en-IN') + (n === 1 ? ' view' : ' views');
        }
    }
    function open(item) {
        countClick(item);
        if (item.dataset.external) window.open(item.dataset.href, '_blank', 'noopener');
        else window.location.href = item.dataset.href;
    }
    list.addEventListener('click', function (e) {
        const item = e.target.closest('.news-item[data-href]');
        if (!item) return;
        if (e.target.closest('a')) countClick(item);
        else open(item);
    });
    list.addEventListener('keydown', function (e) {
        const item = e.target.closest('.news-item[data-href]');
        if (item && e.key === 'Enter') open(item);
    });

    // Refresh the headlines every 5 minutes (matches the server's feed cache) while the tab is open
    async function refresh() {
        if (document.hidden) return;
        try {
            const res = await fetch(list.dataset.refreshUrl, { cache: 'no-store', headers: { Accept: 'text/html' } });
            if (!res.ok) return;
            const html = (await res.text()).trim();
            if (html && html !== list.dataset.lastHtml) {
                const scroll = list.scrollTop;
                list.innerHTML = html;
                list.scrollTop = scroll;
                list.dataset.lastHtml = html;
            }
        } catch (e) { /* keep the current headlines */ }
    }
    setInterval(refresh, {{ (int) config('news.cache_minutes', 5) * 60 * 1000 }});
    document.addEventListener('visibilitychange', function () { if (!document.hidden) refresh(); });
    document.addEventListener('live:content-changed', refresh); // admin changed news / settings
})();
</script>
@endsection
