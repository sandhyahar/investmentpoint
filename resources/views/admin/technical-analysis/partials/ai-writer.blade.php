{{-- "Write with AI" for the Technical Analysis form: fills "Full Analysis" with a public appearance
     report (about 250 words) of the company in the Title, in the owner's fixed format (NewsWriter::analysis). --}}
<div class="mb-3 p-3" style="background:#f4f6fd;border:1px solid #dfe5f5;border-radius:12px" id="taAiWriter" data-url="{{ route('admin.technical-analysis.write') }}">
  <div class="d-flex align-items-center gap-2 flex-wrap">
    <button type="button" class="btn btn-sm btn-primary" id="taAiBtn" style="border-radius:8px;font-weight:600"><i class="bi bi-stars me-1"></i> Write Full Analysis with AI (free)</button>
    <small class="text-muted" id="taAiStatus">Writes a public appearance report (about 250 words) of the company in the Title: Report Date, NSE / BSE, Sector, Company Overview, Market Trend, Risk Factor and the Overall Assessment in red. SEBI guidelines, no buy / sell / hold advice or targets. Check it before saving.</small>
  </div>
</div>

@section('scripts')
<script>
(function () {
  const box = document.getElementById('taAiWriter');
  const btn = document.getElementById('taAiBtn');
  const status = document.getElementById('taAiStatus');
  const $ = id => document.getElementById(id);
  const editor = id => window.tinymce && tinymce.get(id);
  const text = id => editor(id) ? editor(id).getContent({ format: 'text' }).trim() : $(id).value.replace(/<[^>]*>/g, '').trim();
  const put = (id, html) => { if (editor(id)) editor(id).setContent(html); else $(id).value = html; };
  const esc = s => String(s).replace(/[&<>"]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));

  async function write(askFirst) {
    const title = $('title').value.trim();
    if (!title) { status.textContent = 'Type the title first (e.g. "SBI Cards: Support, Resistance & Key Levels").'; $('title').focus(); return; }
    if (askFirst && text('content') && !confirm('Replace the current Full Analysis with a new AI draft?')) return;

    btn.disabled = true;
    const label = btn.innerHTML;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Writing… (up to a minute)';
    status.textContent = '';
    try {
      const res = await fetch(box.dataset.url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': @json(csrf_token()) },
        body: JSON.stringify({ title, support: $('support').value.trim(), resistance: $('resistance').value.trim(),
          cmp: $('cmp') ? $('cmp').value.trim() : '', published_on: $('published_on') ? $('published_on').value : '' }),
      });
      const data = await res.json().catch(() => ({}));
      if (!res.ok) throw new Error(data.message || 'The AI could not write the analysis. Please try again.');

      put('content', data.content);
      status.innerHTML = '<span class="text-success fw-semibold"><i class="bi bi-check-circle"></i> Draft ready: ' + data.words + ' words.</span> Read it, check the levels, then save.'
        + (data.warnings || []).map(w => '<br><span class="text-danger"><i class="bi bi-exclamation-triangle"></i> ' + esc(w) + '</span>').join('');
    } catch (e) {
      status.innerHTML = '<span class="text-danger">' + esc(e.message) + '</span>';
    } finally {
      btn.disabled = false;
      btn.innerHTML = label;
    }
  }
  btn.addEventListener('click', () => write(true));

  // A picture picked from the Media Library: no title yet -> the title from the picture's name
  // ("ZYDUSLIFE - Zydus Life" -> "Zydus Life Technical Analysis : Trend, Support & Resistance"), then the
  // Full Analysis is written at once. A Full Analysis already there is kept (a new picture on the edit page).
  document.addEventListener('media:picked', function (e) {
    if (e.target.id !== 'image' || btn.disabled) return;
    const name = String(e.detail && e.detail.name || '').trim();
    if (!$('title').value.trim() && name) {
      const company = (name.split(/\s+[-–]\s+/).slice(1).join(' - ') || name).replace(/[_]+/g, ' ').replace(/\s+/g, ' ').trim();
      $('title').value = company + ' Technical Analysis : Trend, Support & Resistance';
      ['input', 'change'].forEach(t => $('title').dispatchEvent(new Event(t, { bubbles: true })));
    }
    if (text('content')) {
      status.innerHTML = '<span class="text-muted"><i class="bi bi-info-circle"></i> New picture chosen. The Full Analysis was kept; click "Write Full Analysis with AI" for a new draft.</span>';
      return;
    }
    write(false);
    box.scrollIntoView({ behavior: 'smooth', block: 'center' });
  });
})();
</script>
@endsection
