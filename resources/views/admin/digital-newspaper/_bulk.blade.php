{{-- Bulk PDF upload (Add Edition -> "Bulk upload"), like Media Articles: many PDFs, or a folder with sub-folders.
     Each PDF becomes one edition of this paper ($edition), made exactly like the single form: its pages are turned
     into images here in the browser (pdf.js) and uploaded one edition at a time. Title and date(s) come from the file
     name, as in the single form: "INVESTMENT POINT - 23.10.2026.pdf" (Weekly: "... 17.10.2026 TO 23.10.2026.pdf"). --}}
@php $weekly = $edition === 'weekly'; @endphp
<div class="form-card" style="max-width:1100px" id="bulkUpload">
  <h2 style="font-size:16px;font-weight:800;color:#10253f;margin:0 0 4px"><i class="bi bi-files me-1"></i> Bulk upload</h2>
  <p class="text-muted" style="font-size:13px;margin-bottom:14px">Choose many PDFs or a whole folder (sub-folders included). Every PDF becomes its own {{ \App\Models\DigitalNewspaper::EDITIONS[$edition] }} edition. The <b>{{ $weekly ? 'week' : 'edition date' }}</b> is read from the file name in the format <code>DD.MM.YYYY</code> (e.g. <code>{{ $weekly ? 'INVESTMENT POINT WEEKLY - 17.10.2026 TO 23.10.2026.pdf' : 'ENG - INVESTMENT POINT DAILY - 23.10.2026.pdf' }}</code>) and the <b>title</b> is {!! $weekly ? 'the file name' : 'made from that date: <b>INVESTMENT POINT - 23.10.2026</b>' !!}. Check the list, fix anything that is wrong, then upload.</p>

  <div class="d-flex flex-wrap gap-2 align-items-center mb-3">
    <label class="btn btn-outline-primary btn-sm mb-0" style="border-radius:8px;font-weight:600">
      <i class="bi bi-file-earmark-pdf me-1"></i> Choose PDF files
      <input type="file" id="bulkFiles" accept="application/pdf,.pdf" multiple hidden>
    </label>
    <label class="btn btn-outline-primary btn-sm mb-0" style="border-radius:8px;font-weight:600">
      <i class="bi bi-folder2-open me-1"></i> Choose a folder
      <input type="file" id="bulkFolder" webkitdirectory directory multiple hidden>
    </label>
    <button type="button" class="btn btn-outline-secondary btn-sm" id="bulkClear" style="border-radius:8px" hidden><i class="bi bi-x-lg me-1"></i> Clear list</button>
    <span class="text-muted ms-auto" style="font-size:12px">Up to 20 pages per PDF · sorted by date</span>
  </div>

  <div id="bulkSummary" class="bulk-summary mb-2" hidden></div>

  <div class="table-responsive" id="bulkTableWrap" hidden>
    <table class="table align-middle mb-2 bulk-table">
      <thead>
        <tr>
          <th style="width:34px"><input class="form-check-input" type="checkbox" id="bulkAll" aria-label="Select all ready files" checked></th>
          <th style="width:36px">#</th>
          <th>PDF file</th>
          @if($weekly)
            <th style="width:150px">Week From *</th>
            <th style="width:150px">Week To</th>
          @else
            <th style="width:150px">Edition Date *</th>
          @endif
          <th style="min-width:240px">Title *</th>
          <th style="width:190px">Status</th>
        </tr>
      </thead>
      <tbody id="bulkRows"></tbody>
    </table>
  </div>

  <div id="bulkActions" class="d-flex flex-wrap gap-2 align-items-center" hidden>
    <label class="d-flex align-items-center gap-2 mb-0" style="font-size:13px">Status
      <select class="form-select form-select-sm" id="bulkStatus" style="width:auto">
        <option value="active">Active (shown on the website)</option>
        <option value="inactive">Inactive (hidden)</option>
      </select>
    </label>
    <button type="button" class="btn btn-primary" id="bulkStart" style="border-radius:8px;font-weight:600"><i class="bi bi-cloud-arrow-up me-1"></i> <span>Upload</span></button>
    <button type="button" class="btn btn-outline-danger" id="bulkStop" style="border-radius:8px;font-weight:600" hidden><i class="bi bi-stop-circle me-1"></i> Stop after this file</button>
    <button type="button" class="btn btn-outline-warning" id="bulkRetry" style="border-radius:8px;font-weight:600" hidden><i class="bi bi-arrow-repeat me-1"></i> Retry failed</button>
  </div>

  <div id="bulkProgress" class="mt-3" hidden aria-live="polite">
    <div class="d-flex justify-content-between" style="font-size:13px;font-weight:600;color:#2b3f9e"><span id="bulkProgressText"></span><span id="bulkProgressCount"></span></div>
    <div class="progress mt-1" style="height:10px"><div id="bulkBar" class="progress-bar progress-bar-striped progress-bar-animated" style="width:0%"></div></div>
  </div>
</div>

<style>
#bulkUpload [hidden]{display:none!important}
.bulk-table{font-size:13px}
.bulk-table td{vertical-align:middle}
.bulk-table .form-control{font-size:12.5px;padding:4px 8px}
.bulk-table .file b{display:block;max-width:300px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.bulk-table .file small{display:block;color:#7a849c;max-width:300px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.bulk-table tr.is-off td{opacity:.55}
.bulk-st{display:inline-flex;align-items:center;gap:5px;font-size:11.5px;font-weight:700;padding:3px 9px;border-radius:20px;line-height:1.3}
.bulk-st.ok{background:#e8f7ef;color:#0a7a4a}.bulk-st.warn{background:#fff6e0;color:#8a5a00}.bulk-st.err{background:#fdecec;color:#c42b30}
.bulk-st.busy{background:#eef2ff;color:#2b3f9e}.bulk-st.done{background:#0a9f63;color:#fff}
.bulk-st a{color:inherit;text-decoration:underline}
.bulk-msg{display:block;font-size:11px;color:#7a849c;margin-top:2px;max-width:200px}
.bulk-summary{font-size:13px;font-weight:600;border:1px solid #d6def5;background:#f4f7ff;color:#2b3f9e;border-radius:10px;padding:9px 12px}
</style>

<script>
(function () {
  const EDITION = @json($edition), WEEKLY = EDITION === 'weekly';
  const CHECK_URL = @json(route('admin.digital-newspaper.bulk-check'));
  const STORE_URL = @json(route('admin.digital-newspaper.bulk-store'));
  const CSRF = @json(csrf_token());
  const LIB = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/', MAX_PAGES = 20, WIDTH = 2000;
  const $ = id => document.getElementById(id);
  const tbody = $('bulkRows');
  let rows = [], running = false, stopAsked = false, seq = 0, reportText = '', skippedText = '';

  // ---------- reading file names (the same rules as the single form) ----------
  // Every valid DD.MM.YYYY in the name, in order -> ['2026-10-17', '2026-10-23']
  function datesFrom(name) {
    return [...name.matchAll(/(\d{1,2})[.\-_\/](\d{1,2})[.\-_\/](\d{4})/g)].map(m => {
      const d = +m[1], mo = +m[2], y = +m[3], t = new Date(Date.UTC(y, mo - 1, d));
      return y >= 1990 && t.getUTCFullYear() === y && t.getUTCMonth() === mo - 1 && t.getUTCDate() === d
        ? y + '-' + String(mo).padStart(2, '0') + '-' + String(d).padStart(2, '0') : '';
    }).filter(Boolean);
  }
  // A daily paper's title, like the posts already on the blogs (owner, 27 Oct 2026): "INVESTMENT POINT - 23.10.2026",
  // without GUJ / HINDI / ENG or DAILY. The Weekly News Paper keeps the file name.
  const dailyTitle = date => 'INVESTMENT POINT - ' + date.split('-').reverse().join('.');
  function guess(file) {
    const path = file.webkitRelativePath || file.name;
    const name = file.name.replace(/\.pdf$/i, '').replace(/_+/g, ' ').replace(/\s+/g, ' ').trim();
    const dates = datesFrom(file.name);
    const title = !WEEKLY && dates[0] ? dailyTitle(dates[0]) : name;
    return { path, title, date: dates[0] || '', dateTo: WEEKLY ? (dates[1] || '') : '' };
  }
  // "GUJ - INVESTMENT POINT DAILY - 25.02.2026" chosen under English -> 'Gujarati' (empty when it fits this paper)
  const PAPERS = { gujarati: [/^guj/i, 'Gujarati'], hindi: [/^hin/i, 'Hindi'], english: [/^eng/i, 'English'] };
  function otherPaper(title) {
    if (WEEKLY) return '';
    const hit = Object.entries(PAPERS).find(([, [re]]) => re.test(title.trim()));
    return hit && hit[0] !== EDITION ? hit[1][1] : '';
  }

  // ---------- the list ----------
  function addFiles(list) {
    const pdfs = Array.from(list).filter(f => /\.pdf$/i.test(f.name) || f.type === 'application/pdf');
    const skipped = list.length - pdfs.length;
    pdfs.forEach(file => rows.push({ key: 'f' + (++seq), file, ...guess(file), include: true, state: 'ready', message: '', existing: null, url: '', touched: false }));
    byDate();
    skippedText = skipped ? skipped + ' file(s) that are not PDFs were left out.' : skippedText;
    render();
    checkDuplicates();
  }
  function validate() {
    const seen = new Map(), files = new Map();
    rows.forEach(r => {
      const fk = r.file.name + '|' + r.file.size;
      if (['done', 'busy', 'skipped'].includes(r.state)) {
        if (!files.has(fk)) files.set(fk, r);
        if (r.date && !seen.has(r.date)) seen.set(r.date, r);
        return;
      }
      r.problem = '';
      if (!r.date) r.problem = 'No DD.MM.YYYY date in the file name: enter the ' + (WEEKLY ? 'week start' : 'edition date');
      else if (WEEKLY && r.dateTo && r.dateTo < r.date) r.problem = 'The week must end on or after its start';
      else if (!r.title.trim()) r.problem = 'Title is empty';
      // The file's own name says the paper (GUJ / HINDI / ENG); a daily title no longer has it
      else if (otherPaper(r.file.name)) r.problem = 'This is the ' + otherPaper(r.file.name) + ' paper: upload it under E-Paper → ' + otherPaper(r.file.name);
      else if (r.file.size > 60 * 1048576) r.problem = 'File is larger than 60 MB';
      r.dupInBatch = (files.has(fk) && files.get(fk) !== r) ? 'The same file is already in the list (row ' + (rows.indexOf(files.get(fk)) + 1) + ')'
        : (r.date && seen.has(r.date) && seen.get(r.date) !== r ? 'Same date as row ' + (rows.indexOf(seen.get(r.date)) + 1) : '');
      if (!files.has(fk)) files.set(fk, r);
      if (r.date && !seen.has(r.date)) seen.set(r.date, r);
      r.state = r.problem ? 'invalid' : (r.dupInBatch ? 'dup' : (r.existing ? 'exists' : (r.state === 'failed' ? 'failed' : 'ready')));
      if (!r.touched) r.include = r.state === 'ready' || r.state === 'failed';
    });
  }
  const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  function statusHtml(r) {
    switch (r.state) {
      case 'ready': return '<span class="bulk-st ok"><i class="bi bi-check-circle"></i> Ready</span>';
      case 'invalid': return '<span class="bulk-st err"><i class="bi bi-exclamation-triangle"></i> Check</span><span class="bulk-msg">' + esc(r.problem) + '</span>';
      case 'dup': return '<span class="bulk-st warn"><i class="bi bi-files"></i> Duplicate</span><span class="bulk-msg">' + esc(r.dupInBatch) + '</span>';
      case 'exists': return '<span class="bulk-st warn"><i class="bi bi-info-circle"></i> Already on site</span><span class="bulk-msg"><a href="' + esc(r.existing.url) + '" target="_blank" rel="noopener">' + esc(r.existing.title) + '</a> · tick to upload anyway</span>';
      case 'busy': return '<span class="bulk-st busy"><span class="spinner-border spinner-border-sm" style="width:.8rem;height:.8rem"></span> ' + esc(r.message || 'Working…') + '</span>';
      case 'done': return '<span class="bulk-st done"><i class="bi bi-check-lg"></i> Saved</span><span class="bulk-msg"><a href="' + esc(r.url) + '" target="_blank" rel="noopener">Open edition</a>' + (r.message ? ' · ' + esc(r.message) : '') + '</span>';
      case 'skipped': return '<span class="bulk-st warn">Skipped</span><span class="bulk-msg">' + esc(r.message) + '</span>';
      default: return '<span class="bulk-st err"><i class="bi bi-x-circle"></i> Failed</span><span class="bulk-msg">' + esc(r.message) + '</span>';
    }
  }
  function render() {
    validate();
    const locked = r => running || r.state === 'done';
    const dateCell = (r, f) => '<td><input type="date" class="form-control" data-f="' + f + '" value="' + esc(r[f]) + '"' + (locked(r) ? ' disabled' : '') + '></td>';
    tbody.innerHTML = rows.map((r, i) => '<tr data-key="' + r.key + '" class="' + (r.include ? '' : 'is-off') + '">'
      + '<td><input class="form-check-input" type="checkbox" data-f="include" ' + (r.include ? 'checked' : '') + (locked(r) || r.state === 'invalid' ? ' disabled' : '') + ' aria-label="Upload this file"></td>'
      + '<td>' + (i + 1) + '</td>'
      + '<td class="file"><b title="' + esc(r.file.name) + '">' + esc(r.file.name) + '</b><small title="' + esc(r.path) + '">' + esc(r.path !== r.file.name ? r.path : '') + ' ' + (r.file.size / 1048576).toFixed(1) + ' MB</small></td>'
      + dateCell(r, 'date') + (WEEKLY ? dateCell(r, 'dateTo') : '')
      + '<td><input type="text" class="form-control" data-f="title" value="' + esc(r.title) + '"' + (locked(r) ? ' disabled' : '') + ' maxlength="255"></td>'
      + '<td>' + statusHtml(r) + '</td></tr>').join('');
    const has = rows.length > 0;
    $('bulkTableWrap').hidden = $('bulkActions').hidden = $('bulkClear').hidden = !has;
    const todo = rows.filter(r => r.include && !['done', 'invalid', 'skipped'].includes(r.state)).length;
    $('bulkStart').disabled = running || todo === 0;
    $('bulkStart').querySelector('span').textContent = 'Upload ' + todo + ' edition' + (todo === 1 ? '' : 's');
    $('bulkStop').hidden = !running;
    $('bulkClear').disabled = running;
    $('bulkRetry').hidden = running || !rows.some(r => r.state === 'failed');
    const ready = rows.filter(r => r.state === 'ready').length, need = rows.filter(r => r.state === 'invalid').length,
      dups = rows.filter(r => ['dup', 'exists'].includes(r.state)).length, done = rows.filter(r => r.state === 'done').length;
    if (has && !running) note(rows.length + ' PDF' + (rows.length === 1 ? '' : 's') + ' in the list: ' + ready + ' ready'
      + (need ? ', ' + need + ' need a date or title' : '') + (dups ? ', ' + dups + ' duplicate' + (dups === 1 ? '' : 's') + ' (not ticked)' : '')
      + (done ? ', ' + done + ' saved' : '') + '.' + (skippedText ? ' ' + skippedText : '') + (reportText ? ' ' + reportText : ''));
  }
  function note(text) { $('bulkSummary').hidden = !text; $('bulkSummary').textContent = text || ''; }

  let checkTimer;
  tbody.addEventListener('input', e => {
    const tr = e.target.closest('tr'), r = rows.find(x => x.key === tr?.dataset.key), f = e.target.dataset.f;
    if (!r || !f) return;
    if (f === 'include') { r.include = e.target.checked; r.touched = true; tr.classList.toggle('is-off', !r.include); render(); return; }
    r[f] = e.target.value;
    if (f === 'title') r.titleTyped = true;
    // A daily edition's date changed by hand: its title follows (unless the title was typed by hand)
    if (f === 'date' && !WEEKLY && !r.titleTyped && r.date) {
      r.title = dailyTitle(r.date);
      const box = tr.querySelector('[data-f="title"]');
      if (box) box.value = r.title;
    }
    if (f === 'date' || f === 'title') r.existing = null;
    clearTimeout(checkTimer);
    checkTimer = setTimeout(checkDuplicates, 600);
  });
  const byDate = () => rows.sort((a, b) => (a.date || '9999').localeCompare(b.date || '9999') || a.path.localeCompare(b.path));
  tbody.addEventListener('change', e => {
    if (!e.target.dataset.f || e.target.dataset.f === 'include') return;
    if (e.target.dataset.f === 'date' && !running) byDate();
    render();
  });
  $('bulkAll').addEventListener('change', e => {
    rows.forEach(r => { if (!['done', 'invalid'].includes(r.state)) { r.include = e.target.checked; r.touched = true; } });
    render();
  });

  // Already on the website?
  async function checkDuplicates() {
    const items = rows.filter(r => r.state !== 'done' && (r.date || r.title)).map(r => ({ key: r.key, title: r.title, published_on: r.date || null }));
    if (!items.length) { render(); return; }
    try {
      const res = await fetch(CHECK_URL, { method: 'POST', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF, 'X-Requested-With': 'XMLHttpRequest' }, body: JSON.stringify({ edition: EDITION, items }) });
      const data = await res.json();
      rows.forEach(r => { if (r.state !== 'done') r.existing = (data.duplicates || {})[r.key] || null; });
    } catch (e) { /* the upload checks again on the server */ }
    render();
  }

  // ---------- PDF -> page images (the same way as the single form) ----------
  function loadLib() {
    if (window.pdfjsLib) { pdfjsLib.GlobalWorkerOptions.workerSrc = LIB + 'pdf.worker.min.js'; return Promise.resolve(window.pdfjsLib); }
    return new Promise((resolve, reject) => {
      const s = document.createElement('script');
      s.src = LIB + 'pdf.min.js';
      s.onload = () => { pdfjsLib.GlobalWorkerOptions.workerSrc = LIB + 'pdf.worker.min.js'; resolve(pdfjsLib); };
      s.onerror = () => reject(new Error('The PDF reader could not be loaded. Check the internet connection.'));
      document.head.appendChild(s);
    });
  }
  async function pages(file, onPage) {
    const lib = await loadLib();
    let pdf;
    try { pdf = await lib.getDocument({ data: await file.arrayBuffer() }).promise; }
    catch (e) { throw new Error(e && e.name === 'PasswordException' ? 'The PDF is password-protected' : 'Not a readable PDF'); }
    if (pdf.numPages > MAX_PAGES) throw new Error('The PDF has ' + pdf.numPages + ' pages (up to ' + MAX_PAGES + ')');
    const out = [];
    for (let n = 1; n <= pdf.numPages; n++) {
      onPage(n, pdf.numPages);
      const page = await pdf.getPage(n);
      const base = page.getViewport({ scale: 1 });
      const viewport = page.getViewport({ scale: WIDTH / base.width });
      const canvas = document.createElement('canvas');
      canvas.width = Math.round(viewport.width); canvas.height = Math.round(viewport.height);
      const ctx = canvas.getContext('2d');
      ctx.fillStyle = '#fff'; ctx.fillRect(0, 0, canvas.width, canvas.height);
      await page.render({ canvasContext: ctx, viewport, intent: 'print' }).promise;
      out.push(await new Promise(r => canvas.toBlob(r, 'image/jpeg', 0.88)));
      canvas.width = canvas.height = 0;
    }
    pdf.destroy();
    return out;
  }

  // ---------- upload, one edition at a time ----------
  function progress(text, done, total) {
    $('bulkProgress').hidden = false;
    $('bulkProgressText').textContent = text;
    $('bulkProgressCount').textContent = done + ' / ' + total;
    $('bulkBar').style.width = (total ? Math.round(done / total * 100) : 0) + '%';
  }
  async function upload(r) {
    r.state = 'busy'; r.message = 'Reading PDF…'; render();
    const blobs = await pages(r.file, (n, t) => { r.message = 'Page ' + n + ' of ' + t; const td = tbody.querySelector('tr[data-key="' + r.key + '"] td:last-child'); if (td) td.innerHTML = statusHtml(r); });
    r.message = 'Uploading ' + blobs.length + ' page' + (blobs.length === 1 ? '' : 's') + '…'; render();
    const fd = new FormData();
    fd.append('_token', CSRF);
    fd.append('edition', EDITION);
    fd.append('title', r.title.trim());
    if (WEEKLY) { fd.append('week_from', r.date); if (r.dateTo) fd.append('week_to', r.dateTo); }
    else fd.append('published_on', r.date);
    fd.append('status', $('bulkStatus').value);
    if (r.existing) fd.append('force', '1');
    blobs.forEach((b, i) => fd.append('page_files[]', new File([b], 'page-' + String(i + 1).padStart(2, '0') + '.jpg', { type: 'image/jpeg' })));
    const res = await fetch(STORE_URL, { method: 'POST', body: fd, headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
    const data = await res.json().catch(() => ({}));
    if (res.status === 201) { r.state = 'done'; r.url = data.url; r.message = data.pages + ' page' + (data.pages === 1 ? '' : 's') + (data.message ? ' · in the Blogger queue' : ''); return; }
    if (res.status === 409) { r.state = 'skipped'; r.message = data.message || 'Already on the website'; return; }
    if (res.status === 419) throw new Error('Your session expired: reload the page and sign in again');
    if (res.status === 413) throw new Error('Too large for the server: split the PDF');
    const first = data.errors ? Object.values(data.errors)[0][0] : '';
    throw new Error(first || data.message || ('Server error ' + res.status));
  }
  async function run(onlyFailed) {
    validate();
    const queue = rows.filter(r => r.include && (onlyFailed ? r.state === 'failed' : ['ready', 'failed', 'dup', 'exists'].includes(r.state)));
    if (!queue.length) return;
    running = true; stopAsked = false;
    $('bulkStop').disabled = false;
    $('bulkBar').classList.add('progress-bar-animated');
    window.addEventListener('beforeunload', guard);
    let done = 0;
    for (const r of queue) {
      if (stopAsked) break;
      progress('Uploading ' + (done + 1) + ' of ' + queue.length + ': ' + r.file.name, done, queue.length);
      try { await upload(r); } catch (e) { r.state = 'failed'; r.message = (e && e.message) || 'Upload failed'; }
      done++;
      render();
    }
    running = false;
    window.removeEventListener('beforeunload', guard);
    progress(stopAsked ? 'Stopped.' : 'Finished.', done, queue.length);
    $('bulkBar').classList.remove('progress-bar-animated');
    const saved = rows.filter(r => r.state === 'done').length, failed = rows.filter(r => r.state === 'failed').length, skipped = rows.filter(r => r.state === 'skipped').length;
    reportText = 'Last upload: ' + saved + ' saved, ' + skipped + ' skipped (already on the website), ' + failed + ' failed.' + (failed ? ' Click "Retry failed" to try those again.' : '');
    render();
    checkDuplicates();
  }
  function guard(e) { e.preventDefault(); e.returnValue = ''; }

  $('bulkFiles').addEventListener('change', e => { addFiles(e.target.files); e.target.value = ''; });
  $('bulkFolder').addEventListener('change', e => { addFiles(e.target.files); e.target.value = ''; });
  $('bulkClear').addEventListener('click', () => { rows = []; reportText = skippedText = ''; note(''); $('bulkProgress').hidden = true; render(); });
  $('bulkStart').addEventListener('click', () => run(false));
  $('bulkRetry').addEventListener('click', () => run(true));
  $('bulkStop').addEventListener('click', () => { stopAsked = true; $('bulkStop').disabled = true; });
})();
</script>
