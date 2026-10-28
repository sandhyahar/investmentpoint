{{-- Editor's Desk extras on top of news.partials.styles. Raw CSS (layouts.app wraps extra-css in <style>) --}}
.bl-back{margin-top:16px}
.ed-head{display:flex;align-items:center;gap:16px}
.ed-head-photo{width:64px;height:64px;border-radius:50%;object-fit:cover;object-position:top;border:3px solid var(--gold);background:#fff;flex-shrink:0}
.ed-top{display:flex;align-items:center;justify-content:space-between;gap:10px}
.ed-noimg{object-fit:contain!important;background:#f1f4fb;padding:14%}
.np-popular .ed-noimg{padding:8px}
.ed-writer{display:flex;align-items:center;gap:12px}
.ed-writer img{width:56px;height:56px;border-radius:50%;object-fit:cover;object-position:top;border:2px solid var(--gold)}
.ed-writer b{display:block;color:var(--navy);font-size:15px}
.ed-writer small{color:#0f7a4a;font-weight:600;font-size:12.5px}
.ed-nav{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin:18px 0}
.ed-nav a{display:block;background:#fff;border:1px solid var(--line);border-radius:12px;padding:12px 14px;color:var(--navy)}
.ed-nav a:hover{border-color:var(--blue)}
.ed-nav a.older{grid-column:2;text-align:right}
.ed-nav small{display:block;font-size:12px;font-weight:700;color:var(--blue);margin-bottom:3px}
.ed-nav b{font-size:14px;line-height:1.4}
@media(max-width:600px){.ed-nav{grid-template-columns:1fr}.ed-nav a.older{grid-column:auto}}
