{{-- Buttons under articles that open Investment Point's own social media pages.
     Links come from Admin -> Site Settings -> Social Media Links; a network without a link is hidden. --}}
@php
    $followOrder = ['facebook', 'instagram', 'whatsapp', 'linkedin', 'telegram'];
    $follow = collect(\App\Models\Setting::socialLinks())
        ->whereIn('key', $followOrder)
        ->sortBy(fn ($s) => array_search($s['key'], $followOrder))
        ->values();
@endphp
@if($follow->isNotEmpty())
<div class="np-share np-share-foot">
    <b>{{ $label ?? 'Follow Investment Point:' }}</b>
    @foreach($follow as $s)
        <a class="{{ $s['key'] }}" href="{{ $s['url'] }}" target="_blank" rel="noopener noreferrer" aria-label="{{ $s['label'] }}"><i class="bi {{ $s['icon'] }}"></i> <span>{{ $s['label'] }}</span></a>
    @endforeach
</div>
@endif
