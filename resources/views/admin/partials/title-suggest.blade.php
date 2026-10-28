{{-- "Suggest SEO titles" under the Title box (#title) of an article form. $type: analysis | news | article.
     Sends what is typed in the box to the free AI and opens a drop-down with five titles right under the
     box; choosing one puts it in the box. On the Technical Analysis form the Support / Resistance boxes
     are sent too. --}}
<div data-title-suggest data-url="{{ route('admin.title-suggestions') }}" data-type="{{ $type }}">
  {{-- The drop-down: floats over the form, attached to the bottom of the Title box --}}
  <div class="position-relative">
    <div class="dropdown-menu w-100 shadow p-0" data-ts-menu role="listbox" aria-label="SEO title suggestions"
         style="top:4px;left:0;border-radius:12px;overflow:hidden;z-index:1055">
      <div class="d-flex justify-content-between align-items-center px-3 py-2" style="background:#f4f6fd;border-bottom:1px solid #e3e7f2">
        <span style="font-size:11px;font-weight:800;letter-spacing:.6px;text-transform:uppercase;color:#2b3f9e"><i class="bi bi-stars me-1"></i> SEO title suggestions</span>
        <button type="button" class="btn-close" data-ts-close aria-label="Close" style="font-size:10px"></button>
      </div>
      <div data-ts-list></div>
      <div class="px-3 py-2 text-muted" data-ts-note style="font-size:12px;border-top:1px solid #e3e7f2" hidden></div>
    </div>
  </div>
  <div class="d-flex align-items-center gap-2 flex-wrap mt-2">
    <button type="button" class="btn btn-sm btn-outline-primary dropdown-toggle" data-ts-button aria-expanded="false" style="border-radius:8px;font-weight:600"><i class="bi bi-stars me-1"></i> Suggest SEO titles (free AI)</button>
    <small class="text-muted" data-ts-status>Type the stock name or topic above, then click for 5 search-friendly titles.</small>
  </div>
</div>
<script>
(function () {
  const box = document.currentScript.previousElementSibling;
  const button = box.querySelector('[data-ts-button]');
  const status = box.querySelector('[data-ts-status]');
  const menu = box.querySelector('[data-ts-menu]');
  const list = box.querySelector('[data-ts-list]');
  const note = box.querySelector('[data-ts-note]');
  const title = document.getElementById('title');
  const value = id => { const el = document.getElementById(id); return el ? el.value.trim() : ''; };
  let loadedFor = null; // what was typed when the list was fetched

  function show(open) {
    menu.classList.toggle('show', open);
    button.setAttribute('aria-expanded', String(open));
  }
  function say(text, error) {
    status.textContent = text;
    status.className = error ? 'text-danger' : 'text-muted';
  }

  function fill(titles) {
    list.innerHTML = '';
    titles.forEach(function (text) {
      const item = document.createElement('button');
      item.type = 'button';
      item.setAttribute('role', 'option');
      item.className = 'dropdown-item d-flex justify-content-between align-items-center gap-3 px-3 py-2';
      item.style.cssText = 'white-space:normal;font-size:13.5px;font-weight:600;color:#141f55;border-bottom:1px solid #f0f2f8';
      const label = document.createElement('span');
      label.textContent = text;
      const count = document.createElement('small');
      count.className = 'text-muted flex-shrink-0';
      count.style.fontWeight = '400';
      count.textContent = text.length + ' chars';
      item.append(label, count);
      item.addEventListener('click', function () {
        title.value = text;
        title.dispatchEvent(new Event('input', { bubbles: true }));
        loadedFor = text; // the list still belongs to this title: the button just reopens it
        show(false);
        say('Title filled in. You can still edit it, or open the list again.');
        title.focus();
      });
      list.appendChild(item);
    });
  }

  button.addEventListener('click', async function () {
    const typed = title.value.trim();
    if (typed.length < 2) { say('Type the stock name or topic in the Title box first.', true); title.focus(); return; }
    // Same text as last time: just open / close the list again, no new request
    if (typed === loadedFor && list.children.length) { show(!menu.classList.contains('show')); return; }

    button.disabled = true;
    const label = button.innerHTML;
    button.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Thinking…';
    say('');
    show(false);
    try {
      const res = await fetch(box.dataset.url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': @json(csrf_token()) },
        body: JSON.stringify({ title: typed, type: box.dataset.type, support: value('support'), resistance: value('resistance') }),
      });
      const data = await res.json().catch(() => ({}));
      if (!res.ok) throw new Error(data.message || 'No titles this time. Please try again.');

      fill(data.titles);
      note.textContent = data.note || '';
      note.hidden = !data.note;
      loadedFor = typed;
      show(true);
      say('Choose a title from the list.');
    } catch (e) {
      say(e.message, true);
    } finally {
      button.disabled = false;
      button.innerHTML = label;
    }
  });

  box.querySelector('[data-ts-close]').addEventListener('click', () => show(false));
  document.addEventListener('click', function (e) { if (!box.contains(e.target) && e.target !== title) show(false); });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape') show(false); });
})();
</script>
