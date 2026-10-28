{{-- Digital News Paper: year archive chips (the rest comes from the e-paper styles). Raw CSS --}}
.dn-years{display:flex;gap:8px;flex-wrap:wrap;margin-top:24px}
.dn-years a{display:inline-flex;align-items:center;gap:6px;padding:7px 14px;border-radius:30px;background:#fff;border:1px solid var(--line);color:var(--navy);font-size:13px;font-weight:700}
.dn-years a small{font-size:11px;font-weight:700;color:var(--muted);background:var(--blue2);border-radius:20px;padding:1px 7px}
.dn-years a:hover{border-color:var(--blue);color:var(--blue)}
.dn-years a.active{background:var(--blue);border-color:var(--blue);color:#fff}
.dn-years a.active small{background:rgba(255,255,255,.2);color:#fff}

/* "Total Pageviews" counter (numbers from the blogspot) */
.dn-stats{display:inline-flex;flex-direction:column;gap:6px;background:#fff;border:1px solid var(--line);border-radius:14px;box-shadow:var(--shadow);padding:14px 20px;margin-top:40px}
.dn-stats-label{font-size:12px;font-weight:800;text-transform:uppercase;letter-spacing:.6px;color:var(--muted)}
.dn-stats-label i{color:var(--blue)}
.dn-stats-row{display:flex;align-items:center;gap:14px}
.dn-spark{display:block;background:#f3f5fa;border-radius:6px}
.dn-spark polygon{fill:rgba(43,63,158,.15)}
.dn-spark polyline{fill:none;stroke:var(--blue);stroke-width:1.6;stroke-linejoin:round}
.dn-stats-row b{font-size:30px;font-weight:900;color:var(--navy);letter-spacing:.5px;line-height:1}
.dn-stats+.ma-about{margin-top:20px}

/* In the hero: a white panel below the search box, same width - label + big number left, trend line right */
.dn-hero-side{display:flex;flex-direction:column;align-items:stretch;gap:10px}
.dn-hero-side .ep-search{min-width:340px}
.dn-hero-side .dn-stats{display:grid;grid-template-columns:1fr auto;grid-template-areas:"label spark" "num spark";align-items:center;column-gap:16px;row-gap:3px;margin:0;padding:11px 16px;background:#fff;border:0;border-radius:12px;box-shadow:0 8px 24px rgba(0,0,0,.18)}
.dn-hero-side .dn-stats-row{display:contents}
/* Label: same chip as the ticker's index names (SENSEX, NIFTY 50) */
.dn-hero-side .dn-stats-label{grid-area:label;justify-self:start;display:inline-flex;align-items:center;gap:5px;padding:2px 8px;border-radius:5px;background:#eef1fb;color:var(--blue);font-size:11px;font-weight:800;letter-spacing:.7px;line-height:1.4;white-space:nowrap}
.dn-hero-side .dn-stats-label i{color:var(--blue);font-size:11px}
.dn-hero-side .dn-stats-row b{grid-area:num;font-size:24px;font-weight:900;color:var(--navy);letter-spacing:.5px;line-height:1.1}
.dn-hero-side .dn-spark{grid-area:spark;width:120px;height:40px;background:#f5f7fc;border-radius:8px}
.dn-hero-side .dn-spark polygon{fill:rgba(43,63,158,.12)}
.dn-hero-side .dn-spark polyline{stroke:var(--blue);stroke-width:1.8}
@media (max-width:991px){
  .dn-hero-side{width:100%}
  .dn-hero-side .ep-search{width:100%;min-width:100%}
}
@media (max-width:420px){
  .dn-hero-side .dn-spark{width:90px}
}
