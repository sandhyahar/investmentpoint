{{-- Share URL: the article's own short link (investmentpoint.in/{name}). Empty = made from the title, as
     before; the grey placeholder shows that link and follows the Title box. Pass $type ('news' | 'analysis')
     and $item (the article, null on Add). --}}
@php($auto = isset($item) ? \App\Support\ShortLink::nameFor($item) : null)
<div class="mb-3">
  <label class="form-label" for="share_name">Share URL</label>
  <div class="input-group">
    <span class="input-group-text" style="font-size:13px">investmentpoint.in/</span>
    <input type="text" class="form-control @error('share_name') is-invalid @enderror" id="share_name" name="share_name" maxlength="100"
           value="{{ old('share_name', $item->share_name ?? '') }}" placeholder="{{ $auto ?? 'made from the title' }}" data-share-type="{{ $type }}" autocomplete="off">
    @error('share_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
  </div>
  <div class="form-text">The link sent by the Share button. Leave it empty to use the link made from the title (shown in grey).</div>
</div>
<script>
(function () {
  const box = document.getElementById('share_name'), title = document.getElementById('title');
  if (!box || !title) return;
  const slug = s => s.normalize('NFKD').replace(/[̀-ͯ]/g, '').toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '');
  // Same names as App\Support\ShortLink::nameFor: Market Trend = the index, Technical Analysis = the company
  const indices = @json(\App\Support\ShortLink::INDICES);
  function auto() {
    const t = title.value.trim();
    if (box.dataset.shareType === 'news') {
      const lower = t.toLowerCase();
      const hit = Object.keys(indices).find(w => new RegExp('\\b' + w + '\\b').test(lower));
      return hit ? indices[hit] : '';
    }
    const m = t.match(/^(.+?)\s+technical\s+(?:analysis|view)\b/i);
    return m ? slug(m[1]) : '';
  }
  function show() { box.placeholder = auto() || 'made from the title'; }
  title.addEventListener('input', show);
  if (title.value.trim()) show();
})();
</script>
