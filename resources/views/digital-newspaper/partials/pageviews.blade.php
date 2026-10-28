{{-- "Total Pageviews" of the weekly newspaper blogspot, like the blog's own widget. $pageviews: {total, daily[]} or null --}}
@if($pageviews)
    @php
        $daily = $pageviews['daily'] ?: [0];
        $max = max(1, max($daily));
        $w = 120; $h = 36; $n = max(1, count($daily) - 1);
        $points = collect($daily)->map(fn ($v, $i) => round($i * $w / $n, 1) . ',' . round($h - 2 - $v / $max * ($h - 4), 1))->implode(' ');
    @endphp
    <div class="dn-stats">
        <span class="dn-stats-label"><i class="bi bi-bar-chart-line-fill"></i> Total Pageviews</span>
        <div class="dn-stats-row">
            <svg class="dn-spark" viewBox="0 0 {{ $w }} {{ $h }}" width="{{ $w }}" height="{{ $h }}" role="img" aria-label="Daily pageviews, last {{ count($daily) }} days">
                <polygon points="0,{{ $h }} {{ $points }} {{ $w }},{{ $h }}" />
                <polyline points="{{ $points }}" />
            </svg>
            <b class="notranslate" translate="no">{{ number_format($pageviews['total']) }}</b>
        </div>
    </div>
@endif
