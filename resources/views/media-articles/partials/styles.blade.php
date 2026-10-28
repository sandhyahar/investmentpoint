{{-- Media articles: extras on top of the e-paper styles. Raw CSS, included inside the layout's <style> tag --}}
.ma-filter{display:flex;gap:8px;margin-top:18px;overflow-x:auto;scrollbar-width:none}
.ma-filter::-webkit-scrollbar{display:none}
.ma-filter a{flex-shrink:0;display:inline-flex;align-items:center;gap:6px;padding:7px 14px;border-radius:30px;background:rgba(255,255,255,.1);border:1px solid rgba(255,255,255,.2);color:#e3e9f5;font-size:13px;font-weight:700;white-space:nowrap}
.ma-filter a small{font-size:11px;opacity:.75}
.ma-filter a:hover{background:rgba(255,255,255,.18);color:#fff}
.ma-filter a.active{background:var(--gold);border-color:var(--gold);color:#141f55}
/* Media Articles: every newspaper in one row. No scroll bar: the row fades out softly on the side where
   more names are hidden, with small frosted ‹ › buttons there; it also scrolls by mouse wheel or by dragging. */
.ma-filter-wrap{position:relative;margin-top:18px}
.ma-filter.ma-filter-scroll{margin-top:0;padding:2px 2px 4px;cursor:grab;scroll-behavior:smooth}
.ma-filter.ma-filter-scroll.dragging{cursor:grabbing;scroll-behavior:auto;user-select:none}
.ma-filter.ma-filter-scroll.dragging a{pointer-events:none}
.ma-filter-wrap.more-left .ma-filter-scroll{-webkit-mask-image:linear-gradient(90deg,transparent,#000 64px);mask-image:linear-gradient(90deg,transparent,#000 64px)}
.ma-filter-wrap.more-right .ma-filter-scroll{-webkit-mask-image:linear-gradient(90deg,#000 calc(100% - 64px),transparent);mask-image:linear-gradient(90deg,#000 calc(100% - 64px),transparent)}
.ma-filter-wrap.more-left.more-right .ma-filter-scroll{-webkit-mask-image:linear-gradient(90deg,transparent,#000 64px,#000 calc(100% - 64px),transparent);mask-image:linear-gradient(90deg,transparent,#000 64px,#000 calc(100% - 64px),transparent)}
.ma-filter-arrow{position:absolute;top:4px;z-index:2;width:30px;height:30px;display:flex;align-items:center;justify-content:center;padding:0;
  border:1px solid rgba(255,255,255,.28);border-radius:50%;background:rgba(255,255,255,.12);color:#fff;font-size:13px;cursor:pointer;
  -webkit-backdrop-filter:blur(6px);backdrop-filter:blur(6px);box-shadow:0 6px 16px rgba(5,10,40,.25);transition:background .2s ease,color .2s ease,border-color .2s ease,transform .2s ease}
.ma-filter-arrow.prev{left:0}.ma-filter-arrow.next{right:0}
.ma-filter-arrow:hover{background:var(--gold);border-color:var(--gold);color:#141f55;transform:scale(1.08)}
.ma-filter-arrow[hidden]{display:none}
@media(hover:none){.ma-filter-arrow{display:none}}
/* The slim scroll bar under the row: a soft track with a glowing gold handle (thicker under the mouse) */
.ma-filter-bar{position:relative;height:4px;margin:10px 2px 0;border-radius:4px;background:rgba(255,255,255,.14);cursor:pointer;touch-action:none;transition:height .2s ease}
.ma-filter-bar::before{content:"";position:absolute;left:0;right:0;top:-6px;bottom:-6px} /* easier to grab */
.ma-filter-bar span{position:absolute;top:0;bottom:0;left:0;border-radius:4px;background:linear-gradient(90deg,#f4bd32,#ffd76b);box-shadow:0 0 10px rgba(244,189,50,.45)}
.ma-filter-bar:hover,.ma-filter-bar.dragging{height:6px}
.ma-filter-bar.dragging span{background:linear-gradient(90deg,#ffcc4d,#ffe08a)}
.ma-filter-bar[hidden]{display:none}
.ma-cover{aspect-ratio:3/4}
.ma-col{display:block;font-size:11px;font-weight:700;letter-spacing:.3px;color:var(--blue);margin-top:3px;text-transform:uppercase}
.ma-paper{position:absolute;left:8px;bottom:8px;max-width:calc(100% - 16px);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;background:rgba(20,31,85,.88);color:#fff;font-size:11px;font-weight:700;padding:4px 9px;border-radius:20px}

/* About Me + Disclaimer block */
.ma-about{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1.4fr);gap:20px;margin-top:40px}
.ma-about-card{background:#fff;border:1px solid var(--line);border-radius:14px;box-shadow:var(--shadow);padding:22px 24px}
.ma-about-card h3{display:flex;align-items:center;gap:8px;font-size:16px;font-weight:800;color:var(--navy);margin:0 0 14px;padding-bottom:12px;border-bottom:1px solid var(--line)}
.ma-about-card h3 i{color:var(--blue)}
.ma-about-card p{font-size:14px;line-height:1.75;color:#293f51;margin:0 0 12px}
.ma-about-card a{color:var(--blue);font-weight:700}
.ma-me{display:flex;align-items:center;gap:14px;margin-bottom:14px}
.ma-me img{width:72px;height:72px;border-radius:50%;object-fit:cover;border:3px solid var(--blue2);flex-shrink:0;background:#f3f5fa}
.ma-me b{display:block;font-size:17px;color:var(--navy)}
.ma-me small{font-size:13px;color:var(--muted)}
.ma-profile-btn{display:inline-flex;align-items:center;gap:6px;background:var(--blue);color:#fff!important;padding:10px 16px;border-radius:10px;font-size:13px}
.ma-profile-btn:hover{background:var(--navy)}
.ma-disc{background:#fffbf0;border-color:#f6dfa0}
.ma-disc h3 i{color:#b7791f}
.ma-disc p{font-size:13px}

/* Single article */
.ma-view{display:grid;grid-template-columns:minmax(0,1fr) 300px;gap:24px;margin-top:24px;align-items:start}
.ma-clip{background:#fff;border:1px solid var(--line);border-radius:12px;box-shadow:var(--shadow);overflow:hidden}
.ma-clip img{display:block;width:100%;height:auto;background:#f3f5fa;min-height:240px}
.ma-side{display:flex;flex-direction:column;gap:16px;position:sticky;top:96px}
.ma-card{background:#fff;border:1px solid var(--line);border-radius:12px;box-shadow:var(--shadow);padding:18px}
.ma-card h3{font-size:13px;font-weight:800;text-transform:uppercase;letter-spacing:.6px;color:var(--muted);margin:0 0 12px}
.ma-facts{display:grid;gap:12px}
.ma-facts span{display:block;font-size:11px;font-weight:800;text-transform:uppercase;letter-spacing:.5px;color:var(--muted)}
.ma-facts b{font-size:15px;color:var(--navy)}
.ma-same a{display:flex;gap:10px;align-items:center;padding:8px 0;border-bottom:1px solid var(--line);color:var(--text)}
.ma-same a:last-child{border:0;padding-bottom:0}
.ma-same img{width:44px;height:58px;object-fit:cover;object-position:top;border-radius:6px;border:1px solid var(--line);flex-shrink:0;background:#f3f5fa}
.ma-same b{display:block;font-size:13px;color:var(--navy);line-height:1.35}
.ma-same small{font-size:12px;color:var(--muted)}
.ma-same a:hover b{color:var(--blue)}

@media(max-width:900px){
  .ma-view{grid-template-columns:1fr}
  .ma-side{position:static}
  .ma-about{grid-template-columns:1fr}
}
