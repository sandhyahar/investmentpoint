{{--
    YouTube-style loading bar: a thin bar across the top of the screen that runs while the next
    page loads (link clicks and form sends) and finishes when it is shown. Included right after
    <body> so it also shows while the page itself is still loading.
--}}
<div id="ip-progress" aria-hidden="true"></div>
<style>
#ip-progress{position:fixed;top:0;left:0;z-index:10000;height:3px;width:0;opacity:0;pointer-events:none;background:linear-gradient(90deg,#f4bd32,#ff9f1a);box-shadow:0 0 10px rgba(244,189,50,.8),0 0 4px rgba(244,189,50,.6);border-radius:0 3px 3px 0;transition:width .25s ease,opacity .3s ease}
@media (prefers-reduced-motion:reduce){#ip-progress{transition:opacity .3s ease}}
</style>
<script>
(function () {
  var bar = document.getElementById('ip-progress'), timer, safety, width = 0;
  function set(w) { width = w; bar.style.width = w + '%'; }
  function start(from) {
    clearInterval(timer); clearTimeout(safety);
    bar.style.transition = 'none'; set(from); bar.style.opacity = '1';
    void bar.offsetWidth; bar.style.transition = '';
    // Creep towards 90% (fast at first, then slower) until the page is ready
    timer = setInterval(function () { if (width < 90) set(width + (90 - width) * 0.1); }, 200);
    safety = setTimeout(done, 12000); // e.g. a link that downloads a file: never leave it hanging
  }
  function done() {
    clearInterval(timer); clearTimeout(safety);
    set(100);
    setTimeout(function () { bar.style.opacity = '0'; setTimeout(function () { if (bar.style.opacity === '0') set(0); }, 300); }, 200);
  }

  // This page loading
  start(30);
  if (document.readyState !== 'loading') done(); else document.addEventListener('DOMContentLoaded', done);
  // Back / forward from the browser's cache: nothing to load
  window.addEventListener('pageshow', function (e) { if (e.persisted) done(); });

  // Next page: a normal click on a link to another page of this site
  document.addEventListener('click', function (e) {
    var a = e.target.closest && e.target.closest('a[href]');
    if (!a || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
    if ((a.target && a.target !== '_self') || a.hasAttribute('download')) return;
    var url = new URL(a.href, location.href);
    if (url.origin !== location.origin || !/^https?:$/.test(url.protocol)) return;
    if (url.pathname === location.pathname && url.search === location.search) return; // same page / #section
    setTimeout(function () { if (!e.defaultPrevented) start(10); }, 0);
  });
  // ...or a form sent the normal way (not by a script)
  document.addEventListener('submit', function (e) {
    var form = e.target;
    setTimeout(function () { if (!e.defaultPrevented && (!form.target || form.target === '_self')) start(10); }, 0);
  });
})();
</script>
