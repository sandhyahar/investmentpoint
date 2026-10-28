{{--
    "Send this article" on the Market Trend / Technical Analysis form. $type: news | analysis; $article on the edit page.
    Rows of name + email, typed right here (no channel links on the form any more: saving clears them). They are remembered for next time
    (same list on both forms); the article is sent to all of them only when "Send this article" is ticked.
--}}
@php
    $emailRows = old('emails', \App\Models\PressContact::customers()->orderBy('id')->get(['name', 'email'])->toArray());
    $emailRows = $emailRows ?: [['name' => '', 'email' => '']];
    $sentBefore = isset($article)
        ? \App\Models\PressDelivery::where('article_type', $type)->where('article_id', $article->getKey())->latest('id')->take(12)->get()
        : collect();
    $mailReady = config('mail.default') !== 'smtp' || !in_array(config('mail.mailers.smtp.host'), [null, '', 'mailpit', 'localhost', '127.0.0.1'], true);
@endphp
<div class="press-box mb-3">
  <input type="hidden" name="press_list" value="1">
  <b class="d-block mb-1"><i class="bi bi-send-fill me-1"></i> Send this article</b>
  <p class="text-muted mb-3" style="font-size:12px">Type them once: they stay here for every Market Trend and Technical Analysis article.</p>

  {{-- Emails: name + email (the "News channels" links section was removed at the admin's request: email only) --}}
  <div class="press-section" data-press-rows="emails">
    <div class="press-head">Emails
      <span class="press-import">
        <label class="btn btn-sm btn-outline-success"><i class="bi bi-file-earmark-spreadsheet"></i> Import CSV / Excel<input type="file" accept=".csv,.xlsx,.xls,text/csv" data-press-import hidden></label>
        <a href="data:text/csv;charset=utf-8,{{ rawurlencode("Name,Email\nRamesh Patel,ramesh@example.com\n") }}" download="emails-sample.csv">Sample file</a>
      </span>
    </div>
    <div class="press-import-note" hidden></div>
    @foreach($emailRows as $i => $row)
      <div class="press-row">
        <div class="press-field"><label>Name:</label><input class="form-control form-control-sm" name="emails[{{ $i }}][name]" value="{{ $row['name'] ?? '' }}" maxlength="150" placeholder="e.g. Ramesh Patel"></div>
        <div class="press-field wide"><label>Email:</label><input class="form-control form-control-sm" type="email" name="emails[{{ $i }}][email]" value="{{ $row['email'] ?? '' }}" maxlength="190" placeholder="name@example.com"></div>
        <button type="button" class="press-remove" title="Remove" data-press-remove><i class="bi bi-x-lg"></i></button>
      </div>
    @endforeach
    <button type="button" class="btn btn-sm btn-outline-primary press-add" data-press-add><i class="bi bi-plus-lg"></i> Add email</button>
    @error('emails.*')<div class="text-danger mt-1" style="font-size:12px">{{ $message }}</div>@enderror

    {{-- Attach files: any type, sent with every email of this send (not kept for the next article) --}}
    <div class="press-files" data-press-files-box>
      <div class="press-files-head">
        <label class="btn btn-sm btn-outline-primary"><i class="bi bi-paperclip"></i> Attach files<input type="file" name="press_files[]" multiple data-press-files hidden></label>
        <small class="text-muted">Word, PDF, JPG, Excel, ZIP / RAR… any file. Up to 10 files, 10 MB each, 18 MB in total. Sent with every email above.</small>
      </div>
      <ul class="press-file-list" hidden></ul>
      @error('press_files')<div class="text-danger mt-1" style="font-size:12px">{{ $message }}</div>@enderror
      @error('press_files.*')<div class="text-danger mt-1" style="font-size:12px">{{ $message }}</div>@enderror
      @if($errors->any() && !$errors->has('press_files*'))<div class="text-muted mt-1" style="font-size:12px">If you had attached files, please attach them again.</div>@endif
    </div>
  </div>

  <label class="press-send">
    {{-- Ticked by default; after a form error it keeps what the admin chose --}}
    <input type="checkbox" class="form-check-input me-2" name="send_press" value="1" @checked(session()->hasOldInput() ? old('send_press') : true)>
    <span><b>Send this article to all the emails above when I click Save</b>
      <small class="d-block text-muted">Ticked = sent to everyone on Save. Untick it to only save the article (e.g. fixing a typo). Sent only if the status is Active.</small></span>
  </label>
  @unless($mailReady)
    <div class="text-warning-emphasis mt-2" style="font-size:12px"><i class="bi bi-exclamation-triangle-fill"></i> Emails can't go out until the email settings are added in <code>.env</code> (MAIL_HOST, MAIL_USERNAME, MAIL_PASSWORD...).</div>
  @endunless

  @if($sentBefore->isNotEmpty())
    <div class="mt-3" style="font-size:12px">
      <b>Sent before:</b>
      @foreach($sentBefore as $d)
        <span class="press-chip {{ $d->status }}" title="{{ $d->target }}{{ $d->message ? ' - ' . $d->message : '' }}">{{ $d->contact_name }} · {{ $d->status === 'sent' ? 'sent' : 'failed' }} · {{ $d->created_at->timezone('Asia/Kolkata')->format('d M, h:i A') }}</span>
      @endforeach
      @if($sentBefore->contains('status', 'failed'))
        <div class="text-muted mt-1">Hold the mouse on a red one to see why.</div>
      @endif
    </div>
  @endif
</div>

@once
<style>
.press-box{background:#f6f8fd;border:1px solid #e2e9f0;border-radius:10px;padding:14px 16px}
.press-section{background:#fff;border:1px solid #e2e9f0;border-radius:8px;padding:10px 12px;margin-bottom:12px}
.press-head{display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:6px 10px;font-weight:700;font-size:13px;margin-bottom:8px;color:#10253f}
.press-import{display:inline-flex;align-items:center;gap:10px;font-weight:600}
.press-import .btn{font-size:12px;border-radius:8px;margin:0;cursor:pointer}
.press-import a{font-size:12px;color:#2b3f9e}
.press-import-note{font-size:12px;font-weight:600;border-radius:8px;padding:7px 10px;margin-bottom:8px;background:#e8f7ef;color:#0a7a4a}
.press-import-note.is-error{background:#fdecec;color:#c0392b}
.press-row{display:flex;gap:10px;align-items:center;margin-bottom:8px}
.press-field{display:flex;align-items:center;gap:6px;flex:1;min-width:0}
.press-field.wide{flex:1.4}
.press-field label{font-size:12px;font-weight:600;color:#43546a;white-space:nowrap;margin:0}
.press-remove{border:0;background:none;color:#c0392b;padding:4px 6px;border-radius:6px;line-height:1}
.press-remove:hover{background:#fdecec}
.press-add{font-size:12px;border-radius:8px}
.press-send{display:flex;align-items:flex-start;gap:2px;font-size:13px;cursor:pointer;background:#fff;border:1px solid #e2e9f0;border-radius:8px;padding:10px 12px;margin:0}
.press-files{border-top:1px dashed #e2e9f0;margin-top:10px;padding-top:10px}
.press-files-head{display:flex;flex-wrap:wrap;align-items:center;gap:6px 10px}
.press-files-head .btn{font-size:12px;border-radius:8px;margin:0;cursor:pointer}
.press-files-head small{font-size:12px}
.press-file-list{list-style:none;margin:8px 0 0;padding:0;display:flex;flex-direction:column;gap:6px}
.press-file-list li{display:flex;align-items:center;gap:8px;font-size:12.5px;background:#f6f8fd;border:1px solid #e2e9f0;border-radius:8px;padding:6px 8px}
.press-file-list li .bi{color:#2b3f9e}
.press-file-list li span{flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.press-file-list li small{color:#6b7a90;white-space:nowrap}
.press-file-list li.too-big{background:#fdecec;border-color:#f5c6c6}
.press-file-list .press-files-total{background:none;border:0;padding:0 2px;font-weight:600;color:#43546a}
.press-file-list .press-files-total.is-error{color:#c0392b}
.press-chip{display:inline-block;margin:3px 4px 0 0;padding:2px 8px;border-radius:20px;background:#e8f7ef;color:#0a7a4a;cursor:default}
.press-chip.failed{background:#fdecec;color:#c0392b}
@media(max-width:600px){.press-row{flex-wrap:wrap}.press-field,.press-field.wide{flex:1 1 100%}}
</style>
<script>
document.addEventListener('click', function (e) {
  // "+ Add email": copy the last row, empty, with the next number in its field names
  var add = e.target.closest('[data-press-add]');
  if (add) {
    var section = add.closest('[data-press-rows]');
    var rows = section.querySelectorAll('.press-row');
    var row = rows[rows.length - 1].cloneNode(true);
    var next = Date.now();
    row.querySelectorAll('input').forEach(function (input) {
      input.value = '';
      input.name = input.name.replace(/\[\d+\]/, '[' + next + ']');
    });
    add.before(row);
    row.querySelector('input').focus();
    return;
  }
  // "x": remove the row (the last one is only emptied, so there is always a row to type in)
  var remove = e.target.closest('[data-press-remove]');
  if (remove) {
    var section = remove.closest('[data-press-rows]');
    var row = remove.closest('.press-row');
    if (section.querySelectorAll('.press-row').length > 1) row.remove();
    else row.querySelectorAll('input').forEach(function (input) { input.value = ''; });
  }
});

// "Attach files": picking again adds to the list (instead of replacing it); "x" takes one file off
(function () {
  var MAX_FILES = 10, MAX_FILE = 10 * 1048576, MAX_TOTAL = 18 * 1048576;
  function size(b) { return b >= 1048576 ? (b / 1048576).toFixed(1) + ' MB' : Math.max(1, Math.round(b / 1024)) + ' KB'; }
  function icon(name) {
    var ext = (name.split('.').pop() || '').toLowerCase();
    if (ext === 'pdf') return 'bi-file-earmark-pdf';
    if (/^(docx?|odt|rtf)$/.test(ext)) return 'bi-file-earmark-word';
    if (/^(xlsx?|csv|ods)$/.test(ext)) return 'bi-file-earmark-excel';
    if (/^(pptx?|odp)$/.test(ext)) return 'bi-file-earmark-ppt';
    if (/^(jpe?g|png|gif|webp|bmp|svg|heic)$/.test(ext)) return 'bi-file-earmark-image';
    if (/^(zip|rar|7z)$/.test(ext)) return 'bi-file-earmark-zip';
    if (/^(txt|md)$/.test(ext)) return 'bi-file-earmark-text';
    return 'bi-file-earmark';
  }
  function render(box, input) {
    var list = box.querySelector('.press-file-list');
    var files = Array.prototype.slice.call(input.files);
    list.innerHTML = '';
    list.hidden = !files.length;
    var total = 0;
    files.forEach(function (f, i) {
      total += f.size;
      var li = document.createElement('li');
      if (f.size > MAX_FILE) li.className = 'too-big';
      li.innerHTML = '<i class="bi"></i><span></span><small></small><button type="button" class="press-remove" title="Remove this file"><i class="bi bi-x-lg"></i></button>';
      li.querySelector('.bi').classList.add(icon(f.name));
      li.querySelector('span').textContent = f.name;
      li.querySelector('small').textContent = size(f.size) + (f.size > MAX_FILE ? ' – too big (max 10 MB)' : '');
      li.querySelector('button').dataset.pressFileRemove = i;
      list.appendChild(li);
    });
    if (files.length) {
      var sum = document.createElement('li');
      var bad = files.length > MAX_FILES || total > MAX_TOTAL;
      sum.className = 'press-files-total' + (bad ? ' is-error' : '');
      sum.textContent = files.length + ' file' + (files.length === 1 ? '' : 's') + ', ' + size(total) + ' in total'
        + (files.length > MAX_FILES ? ' – please keep it to 10 files' : total > MAX_TOTAL ? ' – too big together (max 18 MB)' : '');
      list.appendChild(sum);
    }
  }
  function setFiles(input, files) {
    var dt = new DataTransfer();
    files.forEach(function (f) { dt.items.add(f); });
    input.files = dt.files;
  }
  document.addEventListener('change', function (e) {
    var input = e.target.closest('[data-press-files]');
    if (!input) return;
    var box = input.closest('[data-press-files-box]');
    var kept = input._kept || [];
    var seen = {};
    kept.forEach(function (f) { seen[f.name + '|' + f.size] = true; });
    Array.prototype.forEach.call(input.files, function (f) { if (!seen[f.name + '|' + f.size]) kept.push(f); });
    input._kept = kept;
    try { setFiles(input, kept); } catch (err) { input._kept = Array.prototype.slice.call(input.files); }
    render(box, input);
  });
  document.addEventListener('click', function (e) {
    var btn = e.target.closest('[data-press-file-remove]');
    if (!btn) return;
    var box = btn.closest('[data-press-files-box]');
    var input = box.querySelector('[data-press-files]');
    var kept = (input._kept || Array.prototype.slice.call(input.files)).slice();
    kept.splice(Number(btn.dataset.pressFileRemove), 1);
    input._kept = kept;
    setFiles(input, kept);
    render(box, input);
  });
})();

// "Import CSV / Excel": read the file here, find the name + email / link in each row, add new rows.
// CSV / text files are read directly; Excel files need SheetJS, tried from 3 CDNs in turn (one may be
// blocked by the network or an ad-blocker).
(function () {
  var LIBS = [
    'https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js',
    'https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js',
    'https://unpkg.com/xlsx@0.18.5/dist/xlsx.full.min.js'
  ];
  var EMAIL = /[A-Za-z0-9._%+-]+@[A-Za-z0-9-]+(\.[A-Za-z0-9-]+)+/;
  function loadFrom(src) {
    return new Promise(function (resolve, reject) {
      var s = document.createElement('script');
      var timer = setTimeout(function () { s.remove(); reject(); }, 12000);
      s.src = src;
      s.onload = function () { clearTimeout(timer); window.XLSX ? resolve(window.XLSX) : reject(); };
      s.onerror = function () { clearTimeout(timer); s.remove(); reject(); };
      document.head.appendChild(s);
    });
  }
  async function loadLib() {
    if (window.XLSX) return window.XLSX;
    for (var i = 0; i < LIBS.length; i++) {
      try { return await loadFrom(LIBS[i]); } catch (e) { /* try the next CDN */ }
    }
    throw new Error('The Excel reader could not be loaded (the internet connection or an ad-blocker may be blocking it). Save the sheet as CSV (File → Save As → CSV) and import that, or try again.');
  }
  // CSV / text: no library needed. Handles "quoted, cells", and comma / semicolon / tab separators.
  function parseCsv(text) {
    text = text.replace(/^﻿/, '');
    var first = text.split(/\r?\n/, 1)[0];
    var sep = [',', ';', '\t'].reduce(function (best, s) { return first.split(s).length > first.split(best).length ? s : best; }, ',');
    var rows = [], row = [], cell = '', q = false;
    for (var i = 0; i < text.length; i++) {
      var ch = text[i];
      if (q) {
        if (ch === '"' && text[i + 1] === '"') { cell += '"'; i++; }
        else if (ch === '"') q = false;
        else cell += ch;
      } else if (ch === '"') q = true;
      else if (ch === sep) { row.push(cell); cell = ''; }
      else if (ch === '\n' || ch === '\r') {
        if (ch === '\r' && text[i + 1] === '\n') i++;
        row.push(cell); rows.push(row); row = []; cell = '';
      } else cell += ch;
    }
    if (cell !== '' || row.length) { row.push(cell); rows.push(row); }
    return rows;
  }
  async function readRows(file) {
    if (/\.(csv|txt|tsv)$/i.test(file.name) || /^text\//.test(file.type)) return parseCsv(await file.text());
    var XLSX = await loadLib();
    var book = XLSX.read(await file.arrayBuffer(), { type: 'array' });
    return XLSX.utils.sheet_to_json(book.Sheets[book.SheetNames[0]], { header: 1, defval: '', raw: false });
  }
  function asLink(v) {
    v = String(v || '').trim();
    if (!v || /\s/.test(v) || v.indexOf('@') > -1 || !/\.[a-z]{2,}/i.test(v)) return '';
    if (!/^https?:\/\//i.test(v)) v = 'https://' + v;
    try { var u = new URL(v); return /^https?:$/.test(u.protocol) ? u.href : ''; } catch (e) { return ''; }
  }
  // Rows of cells -> [{name, value}]: uses the header row ("Name", "Email" / "Link", "Website"...) when there
  // is one, otherwise the cell that looks like an email / link, and the first other cell as the name
  function pick(rows, kind) {
    var head = (rows[0] || []).map(function (c) { return String(c || '').trim().toLowerCase(); });
    var nameCol = head.findIndex(function (h) { return /name|naam|channel|website name|company/.test(h) && !/mail|link|url/.test(h); });
    var valCol = head.findIndex(function (h) { return kind === 'emails' ? /mail/.test(h) : /link|url|website|site|web/.test(h) && !/name/.test(h); });
    var hasHead = valCol > -1 || nameCol > -1;
    return rows.slice(hasHead ? 1 : 0).map(function (row) {
      var cells = row.map(function (c) { return String(c == null ? '' : c).trim(); });
      var v = '', vi = -1;
      if (valCol > -1) { v = cells[valCol] || ''; vi = valCol; }
      else {
        vi = cells.findIndex(function (c) { return kind === 'emails' ? EMAIL.test(c) : !!asLink(c) && !EMAIL.test(c); });
        v = vi > -1 ? cells[vi] : '';
      }
      if (kind === 'emails') { var m = String(v).match(EMAIL); v = m ? m[0].toLowerCase() : ''; } else { v = asLink(v); }
      var name = nameCol > -1 ? cells[nameCol] : (cells.find(function (c, i) { return i !== vi && c && !EMAIL.test(c) && !asLink(c); }) || '');
      return { name: name.slice(0, 150), value: v };
    });
  }
  function addRows(section, kind, items) {
    var field = kind === 'emails' ? 'email' : 'url';
    var have = {};
    section.querySelectorAll('.press-row').forEach(function (r) {
      var v = r.querySelector('input[name$="[' + field + ']"]').value.trim().toLowerCase();
      if (v) have[v] = true;
    });
    var added = 0, dup = 0, bad = 0;
    var add = section.querySelector('[data-press-add]');
    items.forEach(function (it, n) {
      if (!it.value) { if (it.name) bad++; return; }
      var key = it.value.toLowerCase();
      if (have[key]) { dup++; return; }
      have[key] = true;
      // Fill an empty row first, otherwise copy the last row
      var rows = section.querySelectorAll('.press-row');
      var row = Array.prototype.find.call(rows, function (r) { return !Array.prototype.some.call(r.querySelectorAll('input'), function (i) { return i.value.trim(); }); });
      if (!row) {
        row = rows[rows.length - 1].cloneNode(true);
        var next = Date.now() + '' + n;
        row.querySelectorAll('input').forEach(function (i) { i.value = ''; i.name = i.name.replace(/\[\d+\]/, '[' + next + ']'); });
        add.before(row);
      }
      row.querySelector('input[name$="[name]"]').value = it.name;
      row.querySelector('input[name$="[' + field + ']"]').value = it.value;
      added++;
    });
    return { added: added, dup: dup, bad: bad };
  }
  document.addEventListener('change', async function (e) {
    var input = e.target.closest('[data-press-import]');
    if (!input || !input.files[0]) return;
    var section = input.closest('[data-press-rows]');
    var kind = section.dataset.pressRows;
    var note = section.querySelector('.press-import-note');
    var show = function (text, error) { note.hidden = false; note.textContent = text; note.classList.toggle('is-error', !!error); };
    try {
      show('Reading the file…');
      var rows = await readRows(input.files[0]);
      var r = addRows(section, kind, pick(rows.filter(function (row) { return row.some(function (c) { return String(c).trim(); }); }), kind));
      var what = kind === 'emails' ? 'email' : 'channel';
      if (!r.added && !r.dup) throw new Error('No ' + (kind === 'emails' ? 'email addresses' : 'links') + ' were found in this file. Put the name in one column and the ' + (kind === 'emails' ? 'email' : 'link') + ' in the next (see the sample file).');
      show('✓ Added ' + r.added + ' ' + what + (r.added === 1 ? '' : 's') + (r.dup ? ', ' + r.dup + ' already in the list' : '') + (r.bad ? ', ' + r.bad + ' row' + (r.bad === 1 ? '' : 's') + ' skipped (no valid ' + (kind === 'emails' ? 'email' : 'link') + ')' : '') + '. Click Save to keep them.');
    } catch (err) {
      show(err.message || 'This file could not be read. Please use a .csv or .xlsx file.', true);
    } finally {
      input.value = '';
    }
  });

  // Big lists: send all rows as one field, so the server's limit on form fields (max_input_vars,
  // usually 1000) can't cut the list short - a shortened list would delete the missing contacts.
  document.addEventListener('submit', function (e) {
    var box = e.target.querySelector('.press-box');
    if (!box || e.defaultPrevented) return;
    var data = { channels: [], emails: [] };
    box.querySelectorAll('[data-press-rows]').forEach(function (section) {
      var kind = section.dataset.pressRows, field = kind === 'emails' ? 'email' : 'url';
      section.querySelectorAll('.press-row').forEach(function (r) {
        var row = { name: r.querySelector('input[name$="[name]"]').value };
        row[field] = r.querySelector('input[name$="[' + field + ']"]').value;
        data[kind].push(row);
      });
    });
    var hidden = box.querySelector('input[name="press_json"]') || box.appendChild(Object.assign(document.createElement('input'), { type: 'hidden', name: 'press_json' }));
    hidden.value = JSON.stringify(data);
    // The row fields are left out of this submit only (names come back right after the form data is taken)
    var inputs = box.querySelectorAll('.press-row input');
    inputs.forEach(function (i) { i.dataset.name = i.name; i.removeAttribute('name'); });
    setTimeout(function () { inputs.forEach(function (i) { i.name = i.dataset.name; }); }, 0);
  });
})();
</script>
@endonce
