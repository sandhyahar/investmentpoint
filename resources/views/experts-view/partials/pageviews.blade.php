{{-- "Total Pageviews" box like the old Blogger site: views per day (last 30 days) + the total in a blue box. $stats from ExpertViewController::sectionStats --}}
@php
    $peak = max(1, max($stats['days']));
    $points = collect($stats['days'])->map(fn ($v, $i) => round($i * 100 / 29, 1) . ',' . round(30 - $v * 27 / $peak, 1))->implode(' ');
@endphp
<div class="xv-pv {{ $class ?? '' }}">
    <span class="xv-pv-label">Total Pageviews</span>
    <div class="xv-pv-row">
        <svg viewBox="0 0 100 32" preserveAspectRatio="none" role="img" aria-label="Page views per day, last 30 days">
            <polygon points="0,32 {{ $points }} 100,32" class="xv-pv-fill"/>
            <polyline points="{{ $points }}" class="xv-pv-line"/>
        </svg>
        <b class="notranslate" translate="no">{{ number_format($stats['total']) }}</b>
    </div>
</div>
