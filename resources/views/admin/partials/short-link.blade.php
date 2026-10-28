{{-- Admin lists: the article's short share link (investmentpoint.in/nifty), when it has one --}}
@if($item->status === 'active' && $item->share_url !== $item->url)
<div class="mt-1"><a href="{{ $item->share_url }}" target="_blank" rel="noopener" data-short-link class="d-inline-flex align-items-center gap-1 text-decoration-none"
   style="font-size:11.5px;font-weight:600;color:#128c7e" title="Short link used when sharing (always opens the newest article with this name)">
  <i class="bi bi-link-45deg"></i>{{ preg_replace('#^https?://#', '', $item->share_url) }}
</a></div>
@endif
