{{--
    "Read more" for an article page: put it right after @include('partials.article-body').
    The article opens with about its first $words words (default 200), cut at the end of a paragraph,
    and a button shows the rest. The whole text stays in the page (hidden, not removed), so Google and
    the share / schema tags still get all of it. Styles: news.partials.styles (.np-readmore).
--}}
<div class="np-readmore" data-words="{{ $words ?? 200 }}" hidden>
    <button type="button" class="btn-custom primary np-readmore-btn">Read more <i class="bi bi-chevron-down"></i></button>
</div>
<script>
(function () {
  // Looked up again on every call: the live admin updates replace the article's elements
  function apply() {
    const bar = document.querySelector('.np-readmore');
    const body = bar && bar.closest('article') && bar.closest('article').querySelector('.np-content');
    if (!body || bar.dataset.open === '1') return;

    const limit = parseInt(bar.dataset.words, 10) || 200;
    const count = el => (el.textContent.trim().match(/\S+/g) || []).length;
    const blocks = Array.from(body.children);
    let words = 0, cut = -1;
    for (let i = 0; i < blocks.length; i++) {
      words += count(blocks[i]);
      if (words >= limit) { cut = i; break; }
    }
    const rest = cut < 0 ? [] : blocks.slice(cut + 1);
    // Nothing worth hiding (a short article, or only a line or two left): show it all, no button
    if (rest.reduce((n, el) => n + count(el), 0) < 40) { bar.hidden = true; return; }

    rest.forEach(el => el.classList.add('np-more-hidden'));
    bar.hidden = false;
  }

  document.addEventListener('click', function (e) {
    const btn = e.target.closest('.np-readmore-btn');
    if (!btn) return;
    const bar = btn.closest('.np-readmore');
    bar.dataset.open = '1';
    bar.closest('article').querySelectorAll('.np-more-hidden').forEach(el => el.classList.remove('np-more-hidden'));
    bar.hidden = true;
  });
  document.addEventListener('live:content-changed', apply);
  apply();
})();
</script>
