{{-- "Write with AI" for the Market Trend news form: fills "Full Article" with a 200-250 word draft
     from the Title. The title is kept as typed. A "Nifty Derivative Market Trend Analysis" title is
     written in the fixed Nifty format (NewsWriter::niftyTrend); a bare title gets the AI's ending. --}}
<div class="mb-3 p-3" style="background:#f4f6fd;border:1px solid #dfe5f5;border-radius:12px" id="newsAiWriter" data-url="{{ route('admin.news-writer') }}">
  <div class="d-flex align-items-center gap-2 flex-wrap">
    <button type="button" class="btn btn-sm btn-primary" id="newsAiBtn" style="border-radius:8px;font-weight:600"><i class="bi bi-stars me-1"></i> Write Full Article with AI (free)</button>
    <small class="text-muted" id="newsAiStatus">Writes a 200–250 word article from the Title, following SEBI guidelines (a Nifty Derivative Market Trend Analysis title gets the Nifty report format). Always check names and numbers before saving.</small>
  </div>
</div>

@section('scripts')
<script>
(function () {
  const box = document.getElementById('newsAiWriter');
  const btn = document.getElementById('newsAiBtn');
  const status = document.getElementById('newsAiStatus');
  const $ = id => document.getElementById(id);
  const editor = id => window.tinymce && tinymce.get(id);
  const text = id => editor(id) ? editor(id).getContent({ format: 'text' }).trim() : $(id).value.replace(/<[^>]*>/g, '').trim();
  const put = (id, html) => { if (editor(id)) editor(id).setContent(html); else $(id).value = html; };
  const esc = s => String(s).replace(/[&<>"]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));

  btn.addEventListener('click', async function () {
    const headline = $('title').value.trim();
    if (!headline) { status.textContent = 'Type the title first.'; $('title').focus(); return; }
    if (text('content') && !confirm('Replace the current Full Article with a new AI draft?')) return;

    btn.disabled = true;
    const label = btn.innerHTML;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Writing… (up to a minute)';
    status.textContent = '';
    try {
      const res = await fetch(box.dataset.url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': @json(csrf_token()) },
        body: JSON.stringify({ headline, form: 'market-trend' }),
      });
      const data = await res.json().catch(() => ({}));
      if (!res.ok) throw new Error(data.message || 'The AI could not write the article. Please try again.');

      put('content', data.content);
      // Nifty format: "Nifty Derivative Market Trend Analysis" typed with nothing after it gets the AI's ending
      if (data.title && !headline.includes(':')) $('title').value = data.title;
      const notes = (data.warnings || []).map(w => '<br><span class="text-warning-emphasis"><i class="bi bi-exclamation-triangle"></i> ' + esc(w) + '</span>').join('');
      status.innerHTML = '<span class="text-success fw-semibold"><i class="bi bi-check-circle"></i> Draft ready.</span> The AI only knows your title: check every name and number, edit if needed, add an image, then save.' + notes;
    } catch (e) {
      status.innerHTML = '<span class="text-danger">' + esc(e.message) + '</span>';
    } finally {
      btn.disabled = false;
      btn.innerHTML = label;
    }
  });
})();
</script>
@endsection
