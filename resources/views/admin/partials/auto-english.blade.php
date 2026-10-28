{{-- "Auto-translate Gujarati / Hindi to English" for an article form.
     $fields: box id => 'text' (a plain box, e.g. title) or 'html' (a rich-text editor), default title + content.
     While the box is ticked, Gujarati or Hindi pasted (or typed) into those boxes is translated with Google
     Translate (App\Services\Translator) and replaced by English; "Undo" brings the original back. Unticked = the text is saved as written
     (for articles meant to stay in Gujarati / Hindi). The choice is remembered in this browser.
     Only what the admin types or pastes is translated: an article already saved in Gujarati / Hindi opens
     with the switch off, and the automatic AI news never passes through here. --}}
@php($autoFields = $fields ?? ['title' => 'text', 'content' => 'html'])
<div class="mt-2 p-2 px-3" data-auto-english data-url="{{ route('admin.translate') }}" data-fields='@json($autoFields)'
     style="background:#f6f8ff;border:1px solid #e3e7f2;border-radius:10px">
  <div class="form-check form-switch mb-0">
    <input class="form-check-input" type="checkbox" role="switch" id="autoEnglish" checked>
    <label class="form-check-label" for="autoEnglish" style="font-size:13px;font-weight:600"><i class="bi bi-translate me-1" style="color:#2b3f9e"></i> Auto-translate Gujarati / Hindi to English</label>
  </div>
  <small class="text-muted d-block" data-ae-status style="font-size:12px">Paste Gujarati or Hindi in the title or the article and it is changed to English with Google Translate. Switch off to keep the original language.</small>
</div>
<script>
(function () {
  const box = document.currentScript.previousElementSibling;
  // This script sits under the Title box, above the article editor: wait until the whole form is on the page
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start); else start();

  function start() {
  const toggle = box.querySelector('#autoEnglish');
  const status = box.querySelector('[data-ae-status]');
  const fields = JSON.parse(box.dataset.fields);
  const INDIC = /[ऀ-ॿ઀-૿]/;     // Hindi (Devanagari) and Gujarati letters
  const LABELS = { title: 'title', content: 'article', summary: 'summary' };
  const STORE = 'ip_auto_english';
  const tried = {};                                   // text already sent per box: never asked twice for the same text
  let busy = 0;

  try { if (localStorage.getItem(STORE) === '0') toggle.checked = false; } catch (e) {}
  // Only for text the admin adds now. An article that is already saved in Gujarati / Hindi (the edit form
  // of one written in that language on purpose) is left alone: the switch starts off on this page, and
  // switching it on by hand translates it.
  if (toggle.checked && Object.keys(fields).some(id => { const el = document.getElementById(id); return el && INDIC.test(el.value); })) {
    toggle.checked = false;
    status.textContent = 'This article is already in Gujarati / Hindi, so it is left as it is. Switch on to translate it to English.';
  }

  // The same box again right above each article editor, so it is in view while pasting there.
  // All copies show the same switch and the same messages.
  const toggles = [toggle], statuses = [status];
  Object.keys(fields).filter(id => fields[id] === 'html').forEach(function (id) {
    const el = document.getElementById(id);
    if (!el) return;
    const copy = box.cloneNode(true);
    copy.removeAttribute('data-auto-english');
    copy.classList.remove('mt-2');
    copy.classList.add('mb-2');
    const input = copy.querySelector('input');
    input.id = 'autoEnglish-' + id;
    input.checked = toggle.checked;
    copy.querySelector('label').htmlFor = input.id;
    el.parentNode.insertBefore(copy, el);
    toggles.push(input);
    statuses.push(copy.querySelector('[data-ae-status]'));
  });

  toggles.forEach(function (one) {
    one.addEventListener('change', function () {
      toggles.forEach(other => { other.checked = one.checked; });
      try { localStorage.setItem(STORE, one.checked ? '1' : '0'); } catch (e) {}
      say(one.checked ? 'On: Gujarati or Hindi you paste is changed to English.' : 'Off: the text is saved in the language you write it.');
      if (one.checked) Object.keys(fields).forEach(check);
    });
  });

  const editor = id => window.tinymce && tinymce.get(id);
  const read = id => editor(id) ? editor(id).getContent() : document.getElementById(id).value;
  function write(id, text) {
    const ed = editor(id);
    if (ed) { ed.undoManager.transact(() => ed.setContent(text)); ed.fire('change'); }
    else { const el = document.getElementById(id); el.value = text; el.dispatchEvent(new Event('input', { bubbles: true })); }
  }

  function say(text, error, undo) {
    statuses.forEach(function (status) {
      status.textContent = text;
      status.className = (error ? 'text-danger' : 'text-muted') + ' d-block';
      if (undo) {
        const link = document.createElement('a');
        link.href = '#';
        link.className = 'ms-2 fw-semibold';
        link.textContent = 'Undo';
        link.addEventListener('click', function (e) { e.preventDefault(); undo(); });
        status.appendChild(link);
      }
    });
  }

  async function check(id) {
    if (!toggle.checked || !document.getElementById(id)) return;
    const original = read(id);
    if (!INDIC.test(original) || tried[id] === original) return;
    tried[id] = original;

    const name = LABELS[id] || 'text';
    const ed = editor(id);
    busy++;
    say('Translating the ' + name + ' to English…');
    if (ed) ed.setProgressState(true);
    try {
      const res = await fetch(box.dataset.url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': @json(csrf_token()) },
        body: JSON.stringify({ text: original, html: fields[id] === 'html' }),
      });
      const data = await res.json().catch(() => ({}));
      if (!res.ok) throw new Error(data.message || 'The translation did not work. Please try again.');
      // Changed while we were waiting? Then leave what the admin has now
      if (read(id) !== original) { say('The ' + name + ' was changed while translating, so it was left as it is.'); return; }

      write(id, data.text);
      tried[id] = read(id);
      say('The ' + name + ' was translated to English. Please check names and numbers.', false, function () {
        write(id, original);
        tried[id] = read(id);
        say('The original ' + name + ' is back. Switch auto-translate off to keep it in that language.');
      });
    } catch (e) {
      say('Not translated: ' + e.message + ' The ' + name + ' is unchanged.', true);
    } finally {
      busy--;
      if (ed) ed.setProgressState(false);
    }
  }

  Object.keys(fields).forEach(function (id) {
    const el = document.getElementById(id);
    if (!el) return;
    // The box itself (also the plain text box under a rich editor that did not load)
    el.addEventListener('paste', () => setTimeout(() => check(id), 100));
    el.addEventListener('change', () => check(id));
    if (fields[id] === 'html') {
      // The rich editor loads a moment after the page: wait for it, then listen inside it
      let waited = 0;
      const timer = setInterval(function () {
        const ed = editor(id);
        if (ed && ed.initialized) {
          clearInterval(timer);
          ed.on('paste', () => setTimeout(() => check(id), 400));
          ed.on('blur', () => check(id));
        } else if (++waited > 120) {
          clearInterval(timer);
        }
      }, 500);
    }
  });

  // Saving while a translation is still running would save the untranslated text
  const form = box.closest('form');
  if (form) form.addEventListener('submit', function (e) {
    if (busy > 0) { e.preventDefault(); e.stopImmediatePropagation(); alert('Please wait: the translation to English is still running.'); }
  }, true);
  }
})();
</script>
