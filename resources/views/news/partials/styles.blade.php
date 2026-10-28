{{-- Raw CSS: layouts.app yields extra-css inside its own <style> tag --}}
.np-page{background:var(--bg);padding:20px 0 44px}
.np-wrap{max-width:1180px;margin:0 auto;padding:0 20px;display:grid;grid-template-columns:minmax(0,1fr) 320px;gap:24px;align-items:start}
.np-muted{color:var(--muted);font-size:13px}
.np-card-box{background:#fff;border:1px solid var(--line);border-radius:var(--radius);box-shadow:var(--shadow)}

/* BREADCRUMB */
.np-crumbs{font-size:12px;color:var(--muted);margin-bottom:14px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.np-crumbs a{color:var(--blue);font-weight:600}
.np-crumbs a:hover{text-decoration:underline}
.np-crumbs i{font-style:normal;margin:0 6px;color:#b5c2cf}

/* ARTICLE */
.np-article{padding:28px 32px 30px}
.np-article .tag{margin-bottom:10px}
/* Section tag on the left, Share button on the right (Market Trend / Technical Analysis articles) */
.np-top{display:flex;align-items:center;justify-content:space-between;gap:10px;margin-bottom:10px}
.np-top .tag{margin-bottom:0}
.np-article h1{font-size:clamp(24px,3vw,34px);line-height:1.25;font-weight:800;color:var(--navy);margin:0 0 12px;letter-spacing:-.3px}
.np-meta{display:flex;flex-wrap:wrap;align-items:center;gap:6px 14px;font-size:12px;color:var(--muted);padding-bottom:16px;margin-bottom:18px;border-bottom:1px solid var(--line)}
.np-meta span{display:inline-flex;align-items:center;gap:5px}
.np-meta .np-writer{font-weight:700;color:var(--blue)}
.np-share{display:flex;flex-wrap:wrap;align-items:center;gap:8px}
.np-share b{font-size:12px;color:var(--muted);font-weight:700;margin-right:2px}
.np-share a{display:inline-flex;align-items:center;justify-content:center;gap:6px;height:32px;padding:0 13px;border-radius:8px;color:#fff;font-size:12px;font-weight:700;transition:transform .15s ease,opacity .15s ease}
.np-share a:hover{color:#fff;opacity:.9;transform:translateY(-1px)}
.np-share .facebook{background:#1877f2}.np-share .instagram{background:linear-gradient(45deg,#f58529,#dd2a7b 55%,#8134af)}.np-share .whatsapp{background:#25d366}.np-share .linkedin{background:#0a66c2}.np-share .telegram{background:#229ed9}
.np-share .fb{background:#1877f2}.np-share .wa{background:#25d366}.np-share .li{background:#0a66c2}.np-share .tg{background:#229ed9}
/* The article picture in its own shape, never cut (a very tall one is limited and shown whole) */
.np-hero{width:100%;height:auto;max-height:640px;object-fit:contain;border-radius:10px;margin:0 0 24px;background:#e8eef4}
.np-photo-credit{margin:-18px 0 22px;font-size:11.5px;color:var(--muted);text-align:right}
.np-photo-credit a{color:var(--muted);text-decoration:underline}

.np-content{font-size:17px;line-height:1.8;color:#1f3448;overflow-wrap:break-word}
.np-content p{margin:0 0 18px}
.np-content h3{font-size:19px;font-weight:800;color:var(--navy);margin:26px 0 8px;line-height:1.35}
.np-content h3:first-child{margin-top:0}
/* Content written in the admin rich text editor (or imported from Blogger): one line per
   paragraph, so tighter spacing; images follow their paragraph's alignment (centred etc.) */
.np-rich p{margin:0 0 12px}
.np-rich > :last-child{margin-bottom:0}
.np-rich h1,.np-rich h2,.np-rich h4{color:var(--navy);font-weight:800;line-height:1.35;margin:24px 0 10px}
.np-rich h1{font-size:26px}.np-rich h2{font-size:22px}.np-rich h4{font-size:17px}
.np-rich > :first-child{margin-top:0}
.np-rich ul,.np-rich ol{margin:0 0 16px;padding-left:24px}
.np-rich li{margin-bottom:6px}
.np-rich a{color:var(--blue);text-decoration:underline}
.np-rich img{display:inline-block;vertical-align:middle;max-width:100%;height:auto;border-radius:12px;margin:8px 0;box-shadow:0 6px 20px rgba(16,37,63,.14)}
.np-rich a:has(> img){text-decoration:none}
.np-rich blockquote{margin:0 0 20px;padding:10px 18px;border-left:4px solid var(--gold);background:#fff8e6;border-radius:6px}
.np-rich table{width:100%;border-collapse:collapse;margin:0 0 20px;font-size:15px;display:block;overflow-x:auto}
.np-rich th,.np-rich td{border:1px solid var(--line);padding:8px 10px;text-align:left}
.np-rich th{background:#f3f5fb}
/* "Read more": the rest of a long article, shown by the button (partials.read-more) */
.np-more-hidden{display:none!important}
.np-readmore{position:relative;text-align:center;margin:0 0 22px;padding-top:6px}
.np-readmore::before{content:"";position:absolute;left:0;right:0;bottom:100%;height:90px;background:linear-gradient(rgba(255,255,255,0),#fff);pointer-events:none}
.np-readmore-btn{font-size:14px;padding:11px 26px;border-radius:10px;cursor:pointer;box-shadow:0 6px 16px rgba(43,63,158,.25)}
.np-readmore-btn:hover{filter:brightness(1.08)}

/* Disclaimer: red box, red text (dark enough to read comfortably on the pale red background) */
.np-disclaimer{margin-top:28px;background:#fef2f2;border:1px solid #fca5a5;border-left:5px solid #dc2626;border-radius:12px;padding:16px 20px;box-shadow:0 4px 14px rgba(220,38,38,.08)}
.np-disclaimer h2{display:flex;align-items:center;gap:8px;color:#b91c1c;font-size:15px;font-weight:800;margin:0 0 8px;text-transform:uppercase;letter-spacing:.5px}
.np-disclaimer h2 i{color:#dc2626}
.np-disclaimer p{color:#b91c1c;font-size:14px;font-weight:500;line-height:1.75;margin:0}
.np-disclaimer p + p{margin-top:10px}
.np-disclaimer strong{color:#7f1d1d;font-weight:800}
.np-disclaimer a{color:#991b1b;font-weight:800;text-decoration:underline;text-underline-offset:2px}
.np-disclaimer a:hover{color:#7f1d1d}

.np-share-foot{margin-top:22px;padding-top:18px;border-top:1px solid var(--line)}

/* SECTION TITLES (same as home page) */
.np-title{display:flex;align-items:center;margin:0 0 14px;font-size:17px;font-weight:800;color:var(--text)}
.np-title:before{content:"";display:inline-block;width:5px;height:18px;background:var(--blue);border-radius:5px;margin-right:8px}

/* CARDS GRID */
.np-related{margin-top:24px}
.np-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:16px}
.np-card{background:#fff;border:1px solid var(--line);border-radius:var(--radius);box-shadow:var(--shadow);overflow:hidden;display:flex;flex-direction:column;transition:transform .15s ease,box-shadow .15s ease}
.np-card:hover{transform:translateY(-2px);box-shadow:0 10px 26px rgba(16,37,63,.12)}
.np-card-img{position:relative;display:block}
.np-card-img img{width:100%;height:auto;max-height:320px;object-fit:contain;display:block;background:#e8eef4}
.np-card-img .tag{position:absolute;left:10px;bottom:10px}
.np-card-body{padding:12px 14px 14px;display:flex;flex-direction:column;gap:5px;flex:1}
.np-card h3{font-size:14px;line-height:1.4;font-weight:800;margin:0;color:var(--text)}
.np-card h3 a:hover{color:var(--blue)}
.np-card small{font-size:11px;color:var(--muted)}
.np-card p{font-size:12px;line-height:1.55;color:var(--muted);margin:0}
.np-source{display:inline-block;background:#eef1fb;color:#2b3f9e;font-weight:700;font-size:10px;padding:1px 6px;border-radius:4px}
.np-readon{margin-top:auto;padding-top:4px;font-size:11px;font-weight:800;color:#2b3f9e}

/* SIDEBAR */
.np-sidebar{display:flex;flex-direction:column;gap:18px}
.np-widget{padding:16px}
.np-search{display:flex;border:1px solid var(--line);border-radius:8px;overflow:hidden;background:#fff}
.np-search:focus-within{border-color:var(--blue);box-shadow:0 0 0 3px var(--blue2)}
.np-search input{flex:1;min-width:0;border:0;padding:9px 12px;font-size:13px;outline:0}
.np-search button{background:var(--blue);color:#fff;border:0;padding:0 16px;font-size:12px;font-weight:700}
.np-search button:hover{background:#22327f}
.np-popular{display:grid;grid-template-columns:22px 80px 1fr;gap:10px;align-items:center;padding:10px 0;border-bottom:1px solid var(--line)}
.np-popular:last-child{border-bottom:0;padding-bottom:0}
.np-popular .np-rank{font-size:18px;font-weight:800;color:#c5d2de}
.np-popular img{width:80px;height:64px;object-fit:cover;border-radius:7px;background:#eef1f8;display:block} /* fills the box, like the home page Business News pictures */
.np-popular h4{font-size:12px;line-height:1.4;font-weight:700;margin:0 0 3px;color:var(--text);display:-webkit-box;-webkit-line-clamp:3;-webkit-box-orient:vertical;overflow:hidden}
.np-popular:hover h4{color:var(--blue)}
.np-popular small{font-size:10px;color:var(--muted)}
.np-popular .np-author{font-weight:700;color:var(--blue)}
.np-popular .np-author::after{content:"·";color:var(--muted);font-weight:400;margin:0 5px}
.np-sub{background:var(--premium-grad);color:#fff;border-radius:var(--radius);padding:18px}
.np-sub b{display:block;color:#ffd36a;font-size:16px;margin-bottom:4px}
.np-sub p{font-size:12px;color:#c9d7e4;margin:0 0 12px}
.np-sub-list{list-style:none;margin:8px 0 10px;padding:0}
.np-sub-list li{font-size:13px;font-weight:600;color:#fff;margin:7px 0}
.np-sub-note{border-top:1px solid rgba(255,255,255,.18);padding-top:9px;margin-bottom:14px;font-size:12.5px;font-weight:700;color:#ffd36a;display:flex;flex-direction:column;gap:5px}

/* LIST PAGE HEADER */
.np-listhead{padding:18px 20px;margin-bottom:18px}
.np-listhead h1{font-size:22px;font-weight:800;color:var(--navy);margin:0}
.np-listhead p{margin:4px 0 0;font-size:13px;color:var(--muted)}
.np-pagination{margin-top:22px}
.np-empty{padding:40px 20px;text-align:center}

@media(max-width:1000px){
  .np-wrap{grid-template-columns:minmax(0,1fr)}
  .np-grid{grid-template-columns:repeat(2,1fr)}
}
@media(max-width:600px){
  .np-wrap{padding:0 12px}
  .np-article{padding:20px 16px}
  .np-content{font-size:16px;line-height:1.8}
  .np-share a span{display:none}
  .np-grid{grid-template-columns:1fr}
}
