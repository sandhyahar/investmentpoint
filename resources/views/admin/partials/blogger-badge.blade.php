{{-- Blogger auto-post status of an E-Paper edition / Media Article (App\Services\BloggerPublisher) --}}
@if($item->blogger_url)
  <a href="{{ $item->blogger_url }}" target="_blank" rel="noopener" title="Posted to Blogger" style="display:inline-block;margin-left:6px;font-size:11px;font-weight:700;color:#c25e00;background:#fff3e6;border-radius:20px;padding:1px 8px;text-decoration:none;white-space:nowrap"><i class="bi bi-rss-fill me-1"></i>On Blogger</a>
@elseif($item->blogger_error)
  <span title="{{ $item->blogger_error }}" style="display:inline-block;margin-left:6px;font-size:11px;font-weight:700;color:#b42318;background:#fdecea;border-radius:20px;padding:1px 8px;white-space:nowrap;cursor:help"><i class="bi bi-exclamation-triangle-fill me-1"></i>Blogger: {{ $item->blogger_tries >= config('blogger.max_tries', 5) ? 'not posted' : 'retrying' }}</span>
@endif
