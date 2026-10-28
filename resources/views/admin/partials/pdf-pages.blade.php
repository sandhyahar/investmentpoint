{{-- PDF upload used by E-Paper and Media Articles: the PDF is turned into one image per page here in the
     browser and only those images are uploaded (as page_files[]; the website shows the pages as images, never
     the PDF). The PDF input has no name, so it is never sent.
     Variables: $pdfLabel, $pdfRequired (bool), $pdfHint (text under the box) --}}
<div class="mb-3">
  <label class="form-label" for="np_pdf">{{ $pdfLabel }} {{ $pdfRequired ? '*' : '' }}</label>
  <input type="file" class="form-control @error('page_files') is-invalid @enderror @error('page_files.*') is-invalid @enderror" id="np_pdf" accept="application/pdf,.pdf" {{ $pdfRequired ? 'required' : '' }}>
  <input type="file" name="page_files[]" id="page_files" multiple hidden>
  <small class="text-muted d-block">{{ $pdfHint }} Up to 20 pages.</small>
  @error('page_files')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
  @error('page_files.*')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
  {{-- Progress while the pages are made, then a green "ready" (or red error) message --}}
  <div id="np_pdf_box" class="np-pdf-box mt-2" hidden aria-live="polite">
    <div class="d-flex align-items-center gap-2">
      <span id="np_pdf_spin" class="spinner-border spinner-border-sm text-primary" role="status" aria-hidden="true"></span>
      <i id="np_pdf_icon" class="bi" hidden></i>
      <span id="np_pdf_status"></span>
    </div>
    <div id="np_pdf_bar_wrap" class="progress mt-2" style="height:8px">
      <div id="np_pdf_bar" class="progress-bar progress-bar-striped progress-bar-animated" style="width:0%"></div>
    </div>
  </div>
  <div id="np_pdf_pages" class="d-flex flex-wrap gap-2 mt-2"></div>
</div>
<style>
.np-pdf-box{font-size:13px;font-weight:600;border:1px solid #d6def5;background:#f4f7ff;color:#2b3f9e;border-radius:10px;padding:10px 12px}
.np-pdf-box.is-ok{border-color:#bbe8cf;background:#e8f7ef;color:#0a7a4a}
.np-pdf-box.is-error{border-color:#f6d5d5;background:#fdecec;color:#c42b30}
#np_pdf_pages figure{margin:0;text-align:center;font-size:11px;font-weight:600;color:#5f6b85}
#np_pdf_pages img{display:block;width:80px;height:110px;object-fit:cover;object-position:top;border-radius:6px;border:1px solid #e3e7f2;margin-bottom:2px}
</style>

<script>
// PDF -> one JPEG per page (pdf.js), put into the hidden page_files[] input for upload.
// This runs before the form's buttons exist (they come after these fields), so the button is looked up when needed.
(function () {
  const pdfInput = document.getElementById('np_pdf');
  const files = document.getElementById('page_files');
  const box = document.getElementById('np_pdf_box');
  const status = document.getElementById('np_pdf_status');
  const spin = document.getElementById('np_pdf_spin');
  const icon = document.getElementById('np_pdf_icon');
  const barWrap = document.getElementById('np_pdf_bar_wrap');
  const bar = document.getElementById('np_pdf_bar');
  const preview = document.getElementById('np_pdf_pages');
  const form = pdfInput.form;
  const submitButton = () => form.querySelector('button[type="submit"]');
  const MAX_PAGES = 20, WIDTH = 2000, LIB = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/';
  let busy = false;

  function setButton(disabled) { const b = submitButton(); if (b) b.disabled = disabled; }
  // state: 'busy' (spinner + bar) | 'ok' | 'error' | 'hide'
  function show(state, text, percent) {
    box.hidden = state === 'hide';
    box.classList.toggle('is-ok', state === 'ok');
    box.classList.toggle('is-error', state === 'error');
    spin.hidden = state !== 'busy';
    icon.hidden = state === 'busy';
    icon.className = 'bi ' + (state === 'ok' ? 'bi-check-circle-fill' : 'bi-exclamation-triangle-fill');
    barWrap.hidden = state !== 'busy';
    if (percent !== undefined) bar.style.width = percent + '%';
    status.textContent = text || '';
  }
  function loadLib() {
    if (window.pdfjsLib) return Promise.resolve(window.pdfjsLib);
    return new Promise(function (resolve, reject) {
      const s = document.createElement('script');
      s.src = LIB + 'pdf.min.js';
      s.onload = function () { pdfjsLib.GlobalWorkerOptions.workerSrc = LIB + 'pdf.worker.min.js'; resolve(pdfjsLib); };
      s.onerror = function () { reject(new Error('The PDF reader could not be loaded. Check the internet connection and try again.')); };
      document.head.appendChild(s);
    });
  }
  function reset() { files.value = ''; preview.innerHTML = ''; }

  // "GUJ - INVESTMENT POINT DAILY - 01.10.2026.pdf" -> empty title / date fields are filled in
  // (weekly: "… 19.09.2026 TO 25.09.2026" fills Week From / Week To). Fields already typed are kept.
  // $pdfDailyTitle (daily e-paper): the title is "INVESTMENT POINT - 01.10.2026" from the file name's date, like the
  // posts already on the blogs (owner, 27 Oct 2026), not the file name with GUJ / HINDI / ENG and DAILY
  const DAILY_TITLE = @json($pdfDailyTitle ?? false);
  function fillFromName(name) {
    const base = name.replace(/\.pdf$/i, '').replace(/[_]+/g, ' ').trim();
    const dates = Array.from(base.matchAll(/(\d{1,2})[.\-\/](\d{1,2})[.\-\/](\d{4})/g))
      .map(m => m[3] + '-' + m[2].padStart(2, '0') + '-' + m[1].padStart(2, '0'));
    const title = document.getElementById('title');
    const named = DAILY_TITLE && dates[0] ? 'INVESTMENT POINT - ' + dates[0].split('-').reverse().join('.') : base;
    if (title && !title.value.trim() && named) title.value = named;
    const set = (id, v) => { const el = document.getElementById(id); if (el && v && !el.dataset.typed) el.value = v; };
    if (document.getElementById('week_from')) { set('week_from', dates[0]); set('week_to', dates[1]); }
    else set('published_on', dates[0]);
  }
  // Dates typed by hand are never overwritten by a file name
  ['week_from', 'week_to', 'published_on'].forEach(id => {
    const el = document.getElementById(id);
    if (el) el.addEventListener('input', () => { el.dataset.typed = '1'; });
  });

  pdfInput.addEventListener('change', async function () {
    reset();
    const pdfFile = pdfInput.files[0];
    if (!pdfFile) { show('hide'); return; }
    fillFromName(pdfFile.name);
    busy = true;
    setButton(true);
    try {
      show('busy', 'Reading the PDF "' + pdfFile.name + '"…', 5);
      const lib = await loadLib();
      const pdf = await lib.getDocument({ data: await pdfFile.arrayBuffer() }).promise;
      const total = pdf.numPages;
      if (total > MAX_PAGES) throw new Error('This PDF has ' + total + ' pages. Up to ' + MAX_PAGES + ' pages can be uploaded at once.');
      const box2 = new DataTransfer();
      for (let n = 1; n <= total; n++) {
        show('busy', 'Making page images… page ' + n + ' of ' + total, Math.round(5 + (n - 1) / total * 95));
        const page = await pdf.getPage(n);
        const base = page.getViewport({ scale: 1 });
        const viewport = page.getViewport({ scale: WIDTH / base.width });
        const canvas = document.createElement('canvas');
        canvas.width = Math.round(viewport.width); canvas.height = Math.round(viewport.height);
        const ctx = canvas.getContext('2d');
        ctx.fillStyle = '#fff'; ctx.fillRect(0, 0, canvas.width, canvas.height); // white paper, not transparent
        // 'print' renders without waiting for animation frames, so it keeps going if the admin switches tabs
        await page.render({ canvasContext: ctx, viewport: viewport, intent: 'print' }).promise;
        const blob = await new Promise(function (r) { canvas.toBlob(r, 'image/jpeg', 0.88); });
        box2.items.add(new File([blob], 'page-' + String(n).padStart(2, '0') + '.jpg', { type: 'image/jpeg' }));
        const fig = document.createElement('figure');
        const thumb = document.createElement('img');
        thumb.src = URL.createObjectURL(blob); thumb.alt = 'Page ' + n;
        fig.append(thumb, 'Page ' + n);
        preview.appendChild(fig);
      }
      files.files = box2.files;
      const mb = Array.from(box2.files).reduce(function (t, f) { return t + f.size; }, 0) / 1048576;
      show('ok', 'PDF ready: ' + total + ' page' + (total === 1 ? '' : 's') + ' (' + mb.toFixed(1) + ' MB). Click "' + (submitButton()?.textContent.trim() || 'Save') + '" below to upload.');
    } catch (e) {
      reset(); pdfInput.value = '';
      show('error', e && e.name === 'PasswordException' ? 'This PDF is password-protected. Please upload a PDF without a password.' : ((e && e.message) || 'This PDF could not be read.'));
    } finally {
      busy = false;
      setButton(false);
    }
  });

  form.addEventListener('submit', function (e) {
    const b = submitButton();
    if (busy) { e.preventDefault(); return; } // the button is off while busy; this covers pressing Enter
    if (pdfInput.files.length && !files.files.length) { e.preventDefault(); show('error', 'Please choose the PDF again.'); return; }
    if (files.files.length && b) {
      // Show that the upload is running until the next page opens
      show('busy', 'Uploading ' + files.files.length + ' page' + (files.files.length === 1 ? '' : 's') + '… please keep this page open.', 100);
      b.disabled = true;
      b.innerHTML = '<span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span> Uploading…';
    }
  });
})();
</script>
