{{-- Full-screen picture viewer for article pages: clicking the article's picture (or a picture inside the
     article text) opens it large, with zoom in / out. Included once in layouts.app.
     Pictures it works on: img.np-hero, .np-content img, img.xv-cover. --}}
<style>
  .np-hero,.np-content img,img.xv-cover{cursor:zoom-in}
  .iz{position:fixed;inset:0;z-index:3000;display:flex;flex-direction:column;background:rgba(8,12,34,.95)}
  .iz[hidden]{display:none}
  .iz-bar{display:flex;align-items:center;justify-content:flex-end;gap:8px;padding:10px 14px;color:#fff;flex-shrink:0}
  .iz-bar span{min-width:52px;text-align:center;font-size:13px;font-weight:700;opacity:.85}
  .iz-bar button{width:40px;height:40px;border:1px solid rgba(255,255,255,.28);border-radius:10px;background:rgba(255,255,255,.1);color:#fff;font-size:18px;line-height:1;cursor:pointer;display:inline-flex;align-items:center;justify-content:center}
  .iz-bar button:hover{background:rgba(255,255,255,.22)}
  .iz-bar button:disabled{opacity:.35;cursor:default}
  .iz-stage{flex:1;min-height:0;overflow:auto;display:flex;-webkit-overflow-scrolling:touch}
  .iz-stage img{margin:auto;display:block;max-width:none;border-radius:6px;cursor:zoom-in;user-select:none;-webkit-user-drag:none}
  .iz-stage img.zoomed{cursor:zoom-out}
  .iz-hint{text-align:center;color:#c9d2ee;font-size:12px;padding:6px 10px 10px;flex-shrink:0}
</style>
<div class="iz" id="imgZoom" hidden role="dialog" aria-modal="true" aria-label="Picture, full size">
  <div class="iz-bar">
    <button type="button" data-iz="out" aria-label="Zoom out" title="Zoom out"><i class="bi bi-zoom-out"></i></button>
    <span id="izLevel">100%</span>
    <button type="button" data-iz="in" aria-label="Zoom in" title="Zoom in"><i class="bi bi-zoom-in"></i></button>
    <button type="button" data-iz="close" aria-label="Close" title="Close"><i class="bi bi-x-lg"></i></button>
  </div>
  <div class="iz-stage" id="izStage"><img id="izImg" alt=""></div>
  <div class="iz-hint">Tap the picture to zoom, then move it with your finger or the scroll bars.</div>
</div>
<script>
(function () {
  const box = document.getElementById('imgZoom');
  const stage = document.getElementById('izStage');
  const img = document.getElementById('izImg');
  const level = document.getElementById('izLevel');
  const STEPS = [1, 1.5, 2, 3, 4];   // 1 = the whole picture fits the screen
  let step = 0, fit = 1;

  function show() {
    const scale = STEPS[step];
    img.style.width = Math.round(img.naturalWidth * fit * scale) + 'px';
    img.style.height = 'auto';
    img.classList.toggle('zoomed', step > 0);
    level.textContent = Math.round(scale * 100) + '%';
    box.querySelector('[data-iz="out"]').disabled = step === 0;
    box.querySelector('[data-iz="in"]').disabled = step === STEPS.length - 1;
  }
  function zoom(to) {
    // Keep the middle of what is on screen in the middle
    const cx = (stage.scrollLeft + stage.clientWidth / 2) / (stage.scrollWidth || 1);
    const cy = (stage.scrollTop + stage.clientHeight / 2) / (stage.scrollHeight || 1);
    step = Math.max(0, Math.min(STEPS.length - 1, to));
    show();
    stage.scrollLeft = cx * stage.scrollWidth - stage.clientWidth / 2;
    stage.scrollTop = cy * stage.scrollHeight - stage.clientHeight / 2;
  }
  function open(source) {
    img.onload = function () {
      // The whole picture inside the screen (a small picture is not blown up)
      fit = Math.min((stage.clientWidth - 16) / img.naturalWidth, (stage.clientHeight - 16) / img.naturalHeight, 1);
      step = 0;
      show();
    };
    img.alt = source.alt || '';
    box.hidden = false;
    document.documentElement.style.overflow = 'hidden';
    img.src = source.currentSrc || source.src;
    if (img.complete && img.naturalWidth) img.onload();
  }
  function close() {
    box.hidden = true;
    document.documentElement.style.overflow = '';
    img.removeAttribute('src');
  }

  // Listening on the document: article pages replace their content on live admin updates
  document.addEventListener('click', function (e) {
    const picture = e.target.closest && e.target.closest('img.np-hero, .np-content img, img.xv-cover');
    if (picture && !picture.closest('a')) { e.preventDefault(); open(picture); return; }
    if (box.hidden) return;
    const button = e.target.closest('[data-iz]');
    if (button) { button.dataset.iz === 'close' ? close() : zoom(step + (button.dataset.iz === 'in' ? 1 : -1)); return; }
    if (e.target === img) { zoom(step === 0 ? 2 : 0); return; }
    if (e.target === stage) close();   // the dark area around the picture
  });
  document.addEventListener('keydown', function (e) {
    if (box.hidden) return;
    if (e.key === 'Escape') close();
    else if (e.key === '+' || e.key === '=') zoom(step + 1);
    else if (e.key === '-') zoom(step - 1);
  });
  window.addEventListener('resize', function () { if (!box.hidden && img.naturalWidth) { img.onload(); } });
})();
</script>
