{{-- "Share" button for one article: opens a share sheet (WhatsApp, Facebook, Instagram, LinkedIn, X,
     Telegram, Email, Copy link). Pass url + title; optional class (e.g. "on-card") and label;
     signed => true adds today's date and the signature (config profile.share_signature) to the message.
     Admin lists: edit => the address that saves the Share URL (+ shareName, autoName) makes the link editable in the sheet.
     The sheet's CSS/JS is printed once per page, the first time this partial is used. --}}
<button type="button" class="ip-share-btn {{ $class ?? '' }}" data-share data-url="{{ $url }}" data-title="{{ $title }}" @if(!empty($signed)) data-signed @endif
  @if(!empty($edit)) data-edit="{{ $edit }}" data-csrf="{{ csrf_token() }}" data-share-name="{{ $shareName ?? '' }}" data-auto-name="{{ $autoName ?? '' }}" @endif
  aria-label="Share: {{ $title }}" title="Share">
    <i class="bi bi-share-fill"></i>@if(empty($iconOnly))<span>{{ $label ?? 'Share' }}</span>@endif
</button>

@once
<style>
.ip-share-btn{display:inline-flex;align-items:center;justify-content:center;gap:7px;height:36px;padding:0 15px;border:1px solid var(--line);border-radius:10px;background:#fff;color:var(--navy);font-size:13px;font-weight:700;cursor:pointer;transition:background .15s,color .15s,border-color .15s,transform .18s ease,box-shadow .15s}
.ip-share-btn:hover{background:var(--blue);border-color:var(--blue);color:#fff}
.ip-share-btn i{font-size:13px}
/* Round icon button floating on a card */
.ip-share-btn.on-card{position:absolute;top:12px;right:12px;z-index:2;width:38px;height:38px;padding:0;border-radius:50%;border:0;background:rgba(255,255,255,.95);box-shadow:0 4px 14px rgba(16,37,63,.22)}
.ip-share-btn.on-card:hover{background:var(--blue);color:#fff}
/* Admin lists: a small square button beside View / Edit / Delete */
.ip-share-btn.ip-share-admin{width:31px;height:31px;padding:0;border-radius:6px;border-color:#25d366;color:#128c7e;font-size:11px;vertical-align:middle}
.ip-share-btn.ip-share-admin:hover{background:#25d366;border-color:#25d366;color:#fff}
.ip-share-btn.ip-share-admin i{font-size:12px}
/* On the dark page header */
.ip-share-btn.on-dark{height:30px;padding:0 12px;font-size:12px;background:rgba(255,255,255,.12);border-color:rgba(255,255,255,.3);color:#fff}
.ip-share-btn.on-dark:hover{background:#ffd36a;border-color:#ffd36a;color:#141f55}

/* "Share it with friends" strip under an article */
.ip-share-row{display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:10px 16px;margin-top:22px;padding:14px 18px;background:#f5f7fc;border:1px solid var(--line);border-radius:12px}
.ip-share-row b{font-size:14px;color:var(--navy)}
.ip-share-row .ip-share-btn{background:var(--blue);border-color:var(--blue);color:#fff}
.ip-share-row .ip-share-btn:hover{background:var(--navy);border-color:var(--navy)}

.ip-share-back{position:fixed;inset:0;z-index:2000;background:rgba(10,20,50,.55);display:flex;align-items:center;justify-content:center;padding:16px;opacity:0;transition:opacity .18s ease}
.ip-share-back[hidden]{display:none}
.ip-share-back.open{opacity:1}
.ip-share-sheet{width:100%;max-width:440px;background:#fff;border-radius:18px;box-shadow:0 24px 60px rgba(10,20,50,.35);padding:20px 22px 22px;transform:translateY(14px);transition:transform .2s ease}
.ip-share-back.open .ip-share-sheet{transform:none}
.ip-share-head{display:flex;align-items:flex-start;justify-content:space-between;gap:12px;margin-bottom:6px}
.ip-share-head h3{margin:0;font-size:17px;font-weight:800;color:var(--navy)}
.ip-share-close{border:0;background:#f1f4fa;width:32px;height:32px;border-radius:50%;flex-shrink:0;color:var(--navy);font-size:15px;cursor:pointer}
.ip-share-close:hover{background:#e3e8f4}
.ip-share-title{font-size:13px;line-height:1.5;color:var(--muted);margin:0 0 16px;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}
/* The signed message (date + signature) as it will be sent */
.ip-share-msg{margin:-6px 0 16px;padding:9px 12px;border-radius:10px;background:#f5f7fc;border:1px solid var(--line);font-size:12px;line-height:1.5;color:#33445c;white-space:pre-line}
.ip-share-msg[hidden]{display:none}
.ip-share-msg small{display:block;font-size:10.5px;font-weight:700;letter-spacing:.5px;text-transform:uppercase;color:var(--muted);margin-bottom:2px}
.ip-share-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:14px 8px}
.ip-share-grid a,.ip-share-grid button{display:flex;flex-direction:column;align-items:center;gap:7px;border:0;background:none;padding:0;color:#33445c;font-size:11.5px;font-weight:600;cursor:pointer;text-decoration:none}
.ip-share-grid i{width:52px;height:52px;border-radius:50%;display:flex;align-items:center;justify-content:center;color:#fff;font-size:22px;transition:transform .15s ease,box-shadow .15s ease}
.ip-share-grid a:hover i,.ip-share-grid button:hover i{transform:translateY(-2px);box-shadow:0 8px 18px rgba(16,37,63,.22)}
.ip-share-grid .whatsapp i{background:#25d366}.ip-share-grid .facebook i{background:#1877f2}
.ip-share-grid .instagram i{background:linear-gradient(45deg,#f58529,#dd2a7b 55%,#8134af)}.ip-share-grid .linkedin i{background:#0a66c2}
.ip-share-grid .x i{background:#111}.ip-share-grid .telegram i{background:#229ed9}
.ip-share-grid .email i{background:#ea4335}.ip-share-grid .more i{background:#5f6b85}
.ip-share-link{display:flex;margin-top:18px;border:1px solid var(--line);border-radius:10px;overflow:hidden}
.ip-share-link input{flex:1;min-width:0;border:0;padding:10px 12px;font-size:13px;color:#33445c;background:#f7f9fc;outline:0}
.ip-share-link button{border:0;background:var(--blue);color:#fff;font-size:13px;font-weight:700;padding:0 16px;cursor:pointer;white-space:nowrap}
.ip-share-link button.done{background:#16a34a}
/* Admin: the Share URL, editable (empty = made from the title) */
.ip-share-edit{margin:-4px 0 16px}
.ip-share-edit[hidden]{display:none}
.ip-share-edit small{display:block;font-size:10.5px;font-weight:700;letter-spacing:.5px;text-transform:uppercase;color:var(--muted);margin-bottom:4px}
.ip-share-edit div{display:flex;align-items:center;border:1px solid var(--line);border-radius:10px;overflow:hidden;background:#f7f9fc}
.ip-share-edit div span{padding:0 0 0 12px;font-size:13px;color:var(--muted);white-space:nowrap}
.ip-share-edit input{flex:1;min-width:0;border:0;padding:9px 6px 9px 1px;font-size:13px;color:#33445c;background:transparent;outline:0}
.ip-share-edit button{border:0;background:#16a34a;color:#fff;font-size:13px;font-weight:700;padding:9px 14px;cursor:pointer;white-space:nowrap}
.ip-share-edit button:disabled{opacity:.6;cursor:default}
.ip-share-edit .err{display:block;margin-top:4px;font-size:12px;color:#dc2626;font-weight:600}
.ip-share-edit .err[hidden]{display:none}
.ip-share-note{margin:10px 0 0;font-size:12px;color:#16a34a;font-weight:600;min-height:16px}
@media(max-width:560px){
  .ip-share-back{align-items:flex-end;padding:0}
  .ip-share-sheet{max-width:none;border-radius:18px 18px 0 0;padding-bottom:26px}
  .ip-share-grid i{width:48px;height:48px;font-size:20px}
}
</style>
<script>
(function () {
  let back, current = { url: '', title: '', signed: false };
  const enc = encodeURIComponent;
  const signature = @json(config('profile.share_signature', []));
  // Today in India as day.month.year ("20.10.2026")
  function today() {
    const p = {};
    new Intl.DateTimeFormat('en-GB', { timeZone: 'Asia/Kolkata', day: '2-digit', month: '2-digit', year: 'numeric' })
      .formatToParts(new Date()).forEach(x => { p[x.type] = x.value; });
    return p.day + '.' + p.month + '.' + p.year;
  }
  function signed() { return current.signed && signature.length > 0; }
  // The message. Signed (admin): the date, "Stay update with... 👇", the link, then the signature with the
  // name in bold on WhatsApp (*...*); the link's preview card shows the title and picture. Else: title + link.
  // $withLink false: the same without the link, for apps that take the link separately (Telegram).
  function message(bold, withLink = true) {
    if (!signed()) return current.title + '\n' + current.url;
    const [name, ...rest] = signature;
    return today() + '\n\nStay update with... 👇\n\n' + (withLink ? current.url + '\n\n' : '') + (bold ? '*' + name + '*' : name) + '\n' + rest.join('\n');
  }
  // The phone's share menu: the whole signed message as text (a separate link would come twice)
  function nativeShare() {
    return navigator.share(signed() ? { text: message(false) } : { title: current.title, url: current.url }).catch(() => {});
  }
  // Computers go straight to the web version (WhatsApp Web, Telegram Web) instead of the
  // app's "Open app / Continue to web" page; phones open the app itself. (iPads say "Macintosh".)
  const ua = navigator.userAgent;
  const isPhone = /Android|iPhone|iPad|iPod|Mobile|Silk|Kindle|BlackBerry|Opera Mini/i.test(ua)
    || (navigator.userAgentData && navigator.userAgentData.mobile)
    || (/Macintosh/.test(ua) && navigator.maxTouchPoints > 1);
  const targets = {
    // (Facebook and LinkedIn take only the link: their own preview shows the title and picture; a signed
    // message is copied when they are clicked, to paste in the post)
    whatsapp: () => (isPhone ? 'https://wa.me/?text=' : 'https://web.whatsapp.com/send?text=') + enc(message(true)),
    facebook: () => 'https://www.facebook.com/sharer/sharer.php?u=' + enc(current.url),
    linkedin: () => 'https://www.linkedin.com/sharing/share-offsite/?url=' + enc(current.url),
    x:        () => signed() ? 'https://x.com/intent/tweet?text=' + enc(message(false))
                             : 'https://x.com/intent/tweet?text=' + enc(current.title) + '&url=' + enc(current.url),
    telegram: () => {
      const text = signed() ? message(false, false) : current.title;
      return isPhone
        ? 'https://t.me/share/url?url=' + enc(current.url) + '&text=' + enc(text)
        : 'https://web.telegram.org/k/#?tgaddr=' + enc('tg://msg_url?url=' + enc(current.url) + '&text=' + enc(text));
    },
    email:    () => 'mailto:?subject=' + enc(current.title) + '&body=' + enc(signed() ? message(false) : current.title + '\n\n' + current.url),
  };

  // Old-style copy, for browsers that block the clipboard API
  function copyFallback(text) {
    const t = document.createElement('textarea');
    t.value = text; t.style.position = 'fixed'; t.style.opacity = '0';
    back.appendChild(t); t.select();
    try { document.execCommand('copy'); } finally { t.remove(); }
  }

  function copy(text) {
    if (navigator.clipboard && window.isSecureContext) {
      return navigator.clipboard.writeText(text).catch(() => copyFallback(text));
    }
    copyFallback(text);
    return Promise.resolve();
  }

  function note(msg) { back.querySelector('.ip-share-note').textContent = msg; }

  function build() {
    back = document.createElement('div');
    back.className = 'ip-share-back';
    back.hidden = true;
    back.innerHTML =
      '<div class="ip-share-sheet" role="dialog" aria-modal="true" aria-labelledby="ip-share-h">' +
        '<div class="ip-share-head"><h3 id="ip-share-h">Share this article</h3><button type="button" class="ip-share-close" aria-label="Close"><i class="bi bi-x-lg"></i></button></div>' +
        '<p class="ip-share-title"></p>' +
        '<div class="ip-share-msg" hidden><small>Message</small><span></span></div>' +
        '<form class="ip-share-edit" hidden><small>Share URL</small><div><span>investmentpoint.in/</span>' +
          '<input type="text" maxlength="100" autocomplete="off" aria-label="Share URL"><button type="submit">Save</button></div><span class="err" hidden></span></form>' +
        '<div class="ip-share-grid">' +
          '<a class="whatsapp" data-to="whatsapp" target="_blank" rel="noopener"><i class="bi bi-whatsapp"></i>WhatsApp</a>' +
          '<a class="facebook" data-to="facebook" target="_blank" rel="noopener"><i class="bi bi-facebook"></i>Facebook</a>' +
          '<button type="button" class="instagram" data-instagram><i class="bi bi-instagram"></i>Instagram</button>' +
          '<a class="linkedin" data-to="linkedin" target="_blank" rel="noopener"><i class="bi bi-linkedin"></i>LinkedIn</a>' +
          '<a class="x" data-to="x" target="_blank" rel="noopener"><i class="bi bi-twitter-x"></i>X</a>' +
          '<a class="telegram" data-to="telegram" target="_blank" rel="noopener"><i class="bi bi-telegram"></i>Telegram</a>' +
          '<a class="email" data-to="email"><i class="bi bi-envelope-fill"></i>Email</a>' +
          '<button type="button" class="more" data-native><i class="bi bi-three-dots"></i>More</button>' +
        '</div>' +
        '<div class="ip-share-link"><input type="text" readonly aria-label="Article link"><button type="button" data-copy><i class="bi bi-link-45deg"></i> Copy link</button></div>' +
        '<p class="ip-share-note" aria-live="polite"></p>' +
      '</div>';
    document.body.appendChild(back);

    back.addEventListener('click', function (e) {
      if (e.target === back || e.target.closest('.ip-share-close')) return close();

      if (e.target.closest('[data-copy]')) {
        const b = e.target.closest('[data-copy]');
        copy(current.url).then(() => {
          b.classList.add('done'); b.innerHTML = '<i class="bi bi-check2"></i> Copied';
          note('Link copied to clipboard.');
        });
        return;
      }

      // Facebook / LinkedIn with a signed message: the message is copied too (the window opens as usual)
      const fbLi = e.target.closest('[data-to=facebook],[data-to=linkedin]');
      if (fbLi && signed()) {
        copy(message(false)).then(() => note('Message copied — paste it in your ' + (fbLi.dataset.to === 'facebook' ? 'Facebook' : 'LinkedIn') + ' post.'));
        return;
      }

      // Instagram has no web "share" link: phones use the system share sheet (Instagram is listed there);
      // computers copy the link (signed: the whole message) and open Instagram to paste it in a post, story or DM.
      if (e.target.closest('[data-instagram]')) {
        if (navigator.share) return nativeShare();
        copy(signed() ? message(false) : current.url).then(() => note((signed() ? 'Message' : 'Link') + ' copied — paste it in your Instagram post, story or DM.'));
        window.open('https://www.instagram.com/', '_blank', 'noopener');
        return;
      }

      if (e.target.closest('[data-native]')) {
        if (navigator.share) return nativeShare();
        copy(signed() ? message(false) : current.url).then(() => note((signed() ? 'Message' : 'Link') + ' copied — paste it anywhere to share.'));
      }
    });

    // Admin: save the Share URL (empty = made from the title); the message and links then use the new link
    back.querySelector('.ip-share-edit').addEventListener('submit', function (e) {
      e.preventDefault();
      const btn = current.btn, form = this, save = form.querySelector('button'), err = form.querySelector('.err');
      const value = form.querySelector('input').value.trim();
      save.disabled = true; err.hidden = true;
      fetch(btn.dataset.edit, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': btn.dataset.csrf },
        body: JSON.stringify({ share_name: value }),
      }).then(r => r.json().then(data => ({ ok: r.ok, data }))).then(({ ok, data }) => {
        if (!ok) throw new Error((data.errors && data.errors.share_name && data.errors.share_name[0]) || data.message || 'Could not save.');
        btn.dataset.url = data.url;
        btn.dataset.shareName = data.share_name || '';
        btn.dataset.autoName = data.auto || '';
        current.url = data.url;
        render();
        // The short link under the title in the list
        const row = btn.closest('tr'), shown = row && row.querySelector('[data-short-link]');
        if (shown) { shown.href = data.url; shown.lastChild.textContent = data.url.replace(/^https?:\/\//, ''); }
        note('Share URL saved: ' + data.url.replace(/^https?:\/\//, ''));
      }).catch(ex => { err.textContent = ex.message; err.hidden = false; })
        .finally(() => { save.disabled = false; });
    });

    document.addEventListener('keydown', e => { if (e.key === 'Escape' && !back.hidden) close(); });
  }

  // The message, the link box and the share links for current.url
  function render() {
    // Signed (admin): the message as it will be sent
    const msg = back.querySelector('.ip-share-msg');
    msg.hidden = !signed();
    msg.querySelector('span').textContent = signed() ? message(false) : '';
    back.querySelector('.ip-share-link input').value = current.url;
    back.querySelectorAll('[data-to]').forEach(a => { a.href = targets[a.dataset.to](); });
  }

  function open(btn) {
    if (!back) build();
    const { url, title } = btn.dataset;
    current = { url, title, signed: 'signed' in btn.dataset, btn };
    back.querySelector('.ip-share-title').textContent = title;
    render();
    // Admin: the Share URL box (grey = the link made from the title, used while the box is empty)
    const edit = back.querySelector('.ip-share-edit');
    edit.hidden = !btn.dataset.edit;
    edit.querySelector('input').value = btn.dataset.shareName || '';
    edit.querySelector('input').placeholder = btn.dataset.autoName || 'made from the title';
    edit.querySelector('.err').hidden = true;
    const c = back.querySelector('[data-copy]');
    c.classList.remove('done'); c.innerHTML = '<i class="bi bi-link-45deg"></i> Copy link';
    note('');
    back.hidden = false;
    requestAnimationFrame(() => back.classList.add('open'));
    document.body.style.overflow = 'hidden';
    back.querySelector('.ip-share-close').focus();
  }

  function close() {
    back.classList.remove('open');
    document.body.style.overflow = '';
    setTimeout(() => { back.hidden = true; }, 180);
  }

  document.addEventListener('click', function (e) {
    const btn = e.target.closest('[data-share]');
    if (!btn) return;
    e.preventDefault();
    e.stopPropagation();
    open(btn);
  });
})();
</script>
@endonce
