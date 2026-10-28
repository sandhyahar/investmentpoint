@php
    $cmp = \App\Models\TechnicalAnalysis::formatLevel($analysis->cmp);
    $support = \App\Models\TechnicalAnalysis::formatLevel($analysis->support);
    $resistance = \App\Models\TechnicalAnalysis::formatLevel($analysis->resistance);
@endphp
<article class="card">
    <a href="{{ $analysis->url }}"><img src="{{ $analysis->image_url }}" alt="{{ $analysis->title }}" loading="lazy"></a>
    <div class="body">
        <span class="tag">Technical</span>
        <h3><a href="{{ $analysis->url }}">{{ $analysis->title }}</a></h3>
        @if($analysis->published_on)<div class="meta">{{ $analysis->published_on->format('d M Y') }}</div>@endif
        @if($cmp || $support || $resistance)
            <p class="ta-levels">
                @if($cmp)<span class="level cmp">CMP {{ $cmp }}</span>@endif
                @if($support)<span class="level support">Support {{ $support }}</span>@endif
                @if($resistance)<span class="level resistance">Resistance {{ $resistance }}</span>@endif
            </p>
        @else
            <p class="ta-summary">{{ \App\Support\Html::plain($analysis->description, 110) }}</p>
        @endif
        <a class="ta-read" href="{{ $analysis->url }}">Read Analysis →</a>
    </div>
</article>
