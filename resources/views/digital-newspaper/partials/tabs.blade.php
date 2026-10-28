{{-- E-Paper tabs: the language editions + Digital News Paper. $active: language key or 'digital' --}}
<div class="ep-tabs">
    @foreach(config('epaper.editions') as $key => $lang)
        <a href="{{ route('epaper.index', $key) }}" @class(['active' => $key === $active])>{{ $lang['native'] }}@if($lang['native'] !== $lang['label']) <small>· {{ $lang['label'] }}</small>@endif</a>
    @endforeach
    <a href="{{ route('digital-newspaper.index') }}" @class(['active' => $active === 'digital'])><i class="bi bi-newspaper"></i> Weekly News Paper</a>
</div>
