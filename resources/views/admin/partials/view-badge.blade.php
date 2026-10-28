{{-- Reader count under an article title in the admin lists. $views: int --}}
@php($views = (int) $views)
<br><span class="view-badge {{ $views ? '' : 'zero' }}" title="Readers"><i class="bi bi-eye-fill"></i> {{ number_format($views) }} {{ Str::plural('view', $views) }}</span>
