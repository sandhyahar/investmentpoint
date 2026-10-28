{{-- Line under the title of Investment Point's own articles (news, technical analysis): date · writer · views.
     $withTime: the time too (India time), for articles saved with one, e.g. the Nifty Market Trend at its update.
     Styles: .np-meta (news.partials.styles) --}}
<div class="np-meta">
    @if($date)<span>{{ ($withTime ?? false) ? $date->copy()->timezone('Asia/Kolkata')->format('d F Y, g:i A') : $date->format('d F Y') }}</span>@endif
    <span class="np-writer notranslate" translate="no"><i class="bi bi-person-fill" aria-hidden="true"></i> {{ config('subscription.plan.contact_name') }}</span>
    <span><i class="bi bi-eye-fill" style="color:var(--blue);margin-right:2px" aria-hidden="true"></i> {{ number_format($views) }} {{ Str::plural('view', $views) }}</span>
</div>
