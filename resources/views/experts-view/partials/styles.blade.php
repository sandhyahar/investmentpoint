{{-- Expert's View list + article. Raw CSS, included inside the layout's <style> tag --}}
/* No picture in the article: show the company logo instead */
.xv-noimg{width:100%;height:100%;display:flex;align-items:center;justify-content:center;background:#fff;padding:8%}
.xv-noimg img{width:100%;height:100%;max-width:340px;object-fit:contain}
.xv-more .xv-noimg{padding:4px;border:1px solid var(--line)}
.xv-more .xv-noimg img{width:100%;height:100%;border:0;border-radius:0;object-fit:contain}
.xv-tag{align-self:flex-start;background:var(--gold);color:#141f55;font-size:11px;font-weight:800;letter-spacing:.5px;text-transform:uppercase;padding:4px 10px;border-radius:20px}
.xv-meta{display:flex;flex-wrap:wrap;align-items:center;gap:6px 14px;font-size:12px;color:var(--muted)}
.xv-meta i{margin-right:3px}
.xv-author{font-weight:700;color:var(--blue)}
.xv-btn{display:inline-flex;align-items:center;gap:7px;align-self:flex-start;background:var(--blue);color:#fff;font-size:14px;font-weight:700;padding:10px 18px;border-radius:10px;transition:background .15s,gap .15s}
.xv-btn.sm{background:none;color:var(--blue);padding:0;font-size:13px}

/* Featured */
.xv-feature{display:grid;grid-template-columns:minmax(0,1.1fr) minmax(0,1fr);background:#fff;border:1px solid var(--line);border-radius:16px;overflow:hidden;box-shadow:var(--shadow);margin-top:26px;color:var(--text);transition:box-shadow .18s ease}
.xv-feature:hover{box-shadow:0 16px 36px rgba(27,42,107,.16);color:var(--text)}
.xv-feature:hover .xv-btn{background:var(--navy);gap:11px}
.xv-feature-img{min-height:300px;background:#f3f5fa}
.xv-feature-img img{width:100%;height:100%;object-fit:contain;display:block}
.xv-feature-body{display:flex;flex-direction:column;gap:12px;padding:28px 30px}
.xv-feature-body h2{font-size:clamp(20px,2.2vw,27px);font-weight:800;line-height:1.3;color:var(--navy);margin:0}
.xv-feature-body p{font-size:15px;line-height:1.7;color:#43546a;margin:0}

/* Grid */
.xv-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:20px}
.xv-card{display:flex;flex-direction:column;background:#fff;border:1px solid var(--line);border-radius:14px;overflow:hidden;box-shadow:var(--shadow);color:var(--text);transition:transform .18s ease,box-shadow .18s ease}
.xv-card:hover{transform:translateY(-4px);box-shadow:0 14px 30px rgba(27,42,107,.14);color:var(--text)}
.xv-card:hover .xv-btn.sm{gap:11px}
.xv-card-img{background:#f3f5fa;overflow:hidden}
.xv-card-img img{width:100%;height:auto;max-height:320px;object-fit:contain;display:block}
.xv-card-body{display:flex;flex-direction:column;gap:8px;padding:16px 18px 18px;flex:1}
.xv-card-body h3{font-size:16px;font-weight:800;line-height:1.4;color:var(--navy);margin:0;display:-webkit-box;-webkit-line-clamp:3;-webkit-box-orient:vertical;overflow:hidden}
.xv-card-body p{font-size:13px;line-height:1.6;color:#5f6b85;margin:0;display:-webkit-box;-webkit-line-clamp:3;-webkit-box-orient:vertical;overflow:hidden}
.xv-card-body .xv-btn{margin-top:auto}

/* Article page */
.xv-hero{padding:28px 0 26px}
.xv-hero h1{max-width:960px;font-size:clamp(24px,3vw,34px);font-weight:800;line-height:1.4;letter-spacing:-.2px}
.xv-hero .xv-meta{margin-top:12px;color:#c9d7e4;font-size:13px}
.xv-hero .xv-author{color:#ffd36a}
.xv-article{display:grid;grid-template-columns:minmax(0,1fr) 320px;gap:24px;margin-top:22px;align-items:start}
.xv-read{background:#fff;border:1px solid var(--line);border-radius:16px;box-shadow:var(--shadow);padding:28px 34px}
.xv-cover{display:block;width:100%;height:auto;border-radius:12px;margin-bottom:22px}
.xv-read .np-content{font-size:16px;line-height:1.75;color:#26374b}
.xv-read .xv-read-share{float:right;margin:-10px -14px 10px 16px;position:relative;z-index:1;box-shadow:0 2px 8px rgba(16,37,63,.08)}.xv-read .np-content a{color:var(--blue);word-break:break-word}
.xv-side{display:flex;flex-direction:column;gap:16px;position:sticky;top:96px}
.xv-byline{display:flex;gap:12px;align-items:center}
.xv-avatar{width:48px;height:48px;border-radius:50%;background:var(--blue);color:#fff;font-weight:800;font-size:17px;display:flex;align-items:center;justify-content:center;flex-shrink:0}
.xv-byline b{display:block;font-size:15px;color:var(--navy)}
.xv-byline small{font-size:12px;color:var(--muted)}
.xv-more a{display:flex;gap:10px;align-items:center;padding:9px 0;border-bottom:1px solid var(--line);color:var(--text)}
.xv-more a:last-child{border:0;padding-bottom:0}
.xv-more img,.xv-more .xv-noimg{width:64px;height:44px;border-radius:6px;object-fit:contain;background:#eef1f8;flex-shrink:0;font-size:18px}
.xv-more b{display:block;font-size:13px;line-height:1.4;color:var(--navy);display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}
.xv-more small{font-size:11px;color:var(--muted)}
.xv-more a:hover b{color:var(--blue)}

/* Total Pageviews (like the old Blogger widget): 30-day chart + the total in a blue box */
.xv-pv-label{display:block;font-size:12px;font-weight:800;letter-spacing:.6px;text-transform:uppercase;color:var(--muted);margin-bottom:10px}
.xv-pv-row{display:flex;align-items:center;gap:12px}
.xv-pv svg{width:96px;height:36px;flex-shrink:0;background:#f3f5fa;border-radius:6px;padding:2px}
.xv-pv-line{fill:none;stroke:#1f2a44;stroke-width:1.6;stroke-linejoin:round;vector-effect:non-scaling-stroke}
.xv-pv-fill{fill:#9aa3b5;opacity:.55}
.xv-pv b{background:linear-gradient(135deg,var(--blue),var(--navy));color:#fff;font-size:24px;font-weight:800;letter-spacing:1px;line-height:1;padding:8px 14px;border-radius:8px;font-variant-numeric:tabular-nums;box-shadow:0 6px 16px rgba(27,42,107,.25)}
/* On the dark Expert's View banner */
.xv-pv.on-dark{background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.16);border-radius:12px;padding:12px 16px}
.xv-pv.on-dark .xv-pv-label{color:#c9d7e4}
.xv-pv.on-dark svg{background:rgba(255,255,255,.1)}
.xv-pv.on-dark .xv-pv-line{stroke:#ffd36a}
.xv-pv.on-dark .xv-pv-fill{fill:#ffd36a;opacity:.25}
.xv-pv.on-dark b{background:#fff;color:var(--navy);box-shadow:none}

/* Writer profile card at the top of an article (photo left, details right) */
.xv-profile{position:relative;display:flex;gap:22px;align-items:center;margin:-6px -8px 26px;padding:20px 22px;border-radius:14px;background:linear-gradient(120deg,#f4f7ff 0%,#fff 70%);border:1px solid var(--line);border-left:4px solid var(--gold)}
.xv-profile-photo{width:132px;height:132px;object-fit:cover;object-position:top;border-radius:14px;border:3px solid #fff;box-shadow:0 8px 22px rgba(16,37,63,.18);flex-shrink:0;background:#fff}
.xv-profile-info{min-width:0;padding-right:84px}
.xv-profile-label{display:inline-block;font-size:11px;font-weight:800;letter-spacing:.6px;text-transform:uppercase;color:var(--blue);background:#e8edfb;padding:3px 10px;border-radius:20px}
.xv-profile h2{margin:8px 0 4px;font-size:24px;font-weight:800;color:var(--navy);line-height:1.3}
.xv-profile-role{margin:0;font-size:14px;font-weight:600;color:#5f6b85}
.xv-profile-role.is-sebi{color:#0f7a4a}
.xv-profile-contacts{display:flex;flex-wrap:wrap;gap:8px;margin-top:12px}
.xv-profile-contacts a{display:inline-flex;align-items:center;gap:6px;font-size:13px;font-weight:700;color:var(--navy);background:#fff;border:1px solid var(--line);border-radius:20px;padding:5px 12px;transition:border-color .15s,color .15s}
.xv-profile-contacts a i{color:var(--blue)}
.xv-profile-contacts a:hover{border-color:var(--blue);color:var(--blue)}
.xv-profile .xv-profile-share{position:absolute;top:16px;right:16px}
img.xv-avatar{object-fit:cover;object-position:top;border:2px solid #fff;box-shadow:0 0 0 2px var(--blue2)}

/* Imported Blogger text: tidy spacing, lists and pictures */
.xv-read .np-rich p:empty{display:none}
.xv-read .np-rich p{margin:0 0 14px}
.xv-read .np-rich ul,.xv-read .np-rich ol{padding-left:1.3em;margin:0 0 16px}
.xv-read .np-rich li{margin:6px 0}
.xv-read .np-rich li::marker{color:var(--blue)}
.xv-read .np-rich img{display:block;margin:14px auto}

@media(max-width:1000px){
  .xv-grid{grid-template-columns:repeat(2,minmax(0,1fr))}
  .xv-article{grid-template-columns:1fr}
  .xv-side{position:static}
}
@media(max-width:700px){
  .xv-feature{grid-template-columns:1fr}
  .xv-feature-img{min-height:0;aspect-ratio:16/9}
  .xv-feature-body{padding:20px}
  .xv-grid{grid-template-columns:1fr}
  .xv-hero{padding:22px 0 20px}
  .xv-read{padding:20px 18px}
  .xv-read .np-content{font-size:15px}
  .xv-profile{flex-direction:column;text-align:center;margin:0 0 22px;padding:20px 16px;border-left-width:1px;border-top:4px solid var(--gold)}
  .xv-profile-info{padding-right:0}
  .xv-profile-contacts{justify-content:center}
  .xv-profile-photo{width:120px;height:120px}
  .xv-profile h2{font-size:21px}
  .xv-profile .xv-profile-share{position:static;margin-top:2px}
  /* Justified lines stretch into big gaps on a narrow screen */
  .xv-read .np-rich [style*="justify"]{text-align:left!important}
}
