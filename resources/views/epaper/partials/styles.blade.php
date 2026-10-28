{{-- E-paper list + reader. Raw CSS: included inside the layout's <style> tag --}}
.ep-page{background:var(--bg);padding-bottom:50px}
.ep-hero{background:var(--premium-grad);color:#fff;padding:36px 0 30px}
.ep-crumbs{font-size:13px;color:#aebfd0;margin-bottom:12px}
.ep-crumbs a{color:#fff;font-weight:600}
.ep-crumbs a:hover{color:#ffd36a}
.ep-crumbs span{margin:0 8px;opacity:.7}
.ep-hero h1{font-size:clamp(26px,3.8vw,40px);font-weight:900;margin:0;color:#fff;line-height:1.2}
.ep-hero h1 small{display:block;font-size:14px;font-weight:600;color:#c9d7e4;margin-top:6px;letter-spacing:.3px}
.ep-hero-row{display:flex;justify-content:space-between;align-items:flex-end;gap:18px;flex-wrap:wrap}

/* Language tabs */
.ep-tabs{display:flex;gap:8px;flex-wrap:wrap;margin-top:18px}
.ep-tabs a{display:inline-flex;align-items:center;gap:6px;padding:8px 16px;border-radius:30px;background:rgba(255,255,255,.1);border:1px solid rgba(255,255,255,.2);color:#e3e9f5;font-size:14px;font-weight:700}
.ep-tabs a:hover{background:rgba(255,255,255,.18);color:#fff}
.ep-tabs a.active{background:var(--gold);border-color:var(--gold);color:#141f55}

/* Search */
.ep-search{display:flex;background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 8px 24px rgba(0,0,0,.18);min-width:min(340px,100%)}
.ep-search input{flex:1;min-width:0;border:0;padding:12px 16px;font:inherit;font-size:14px;outline:0}
.ep-search button{border:0;background:var(--blue);color:#fff;padding:0 18px;font-weight:700;font-size:14px;cursor:pointer}
.ep-search button:hover{background:var(--navy)}

/* Edition grid */
.ep-bar{display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;margin:26px 0 16px}
.ep-bar h2{font-size:19px;font-weight:800;color:var(--navy);margin:0}
.ep-bar span{font-size:13px;color:var(--muted)}
.ep-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:18px}
.ep-card{display:flex;flex-direction:column;background:#fff;border:1px solid var(--line);border-radius:12px;overflow:hidden;box-shadow:var(--shadow);transition:transform .18s ease,box-shadow .18s ease;color:var(--text)}
.ep-card:hover{transform:translateY(-4px);box-shadow:0 14px 30px rgba(27,42,107,.16);color:var(--text)}
.ep-cover{position:relative;aspect-ratio:452/640;background:#eef1f7;overflow:hidden}
.ep-cover img{width:100%;height:100%;object-fit:cover;object-position:top;display:block}
.ep-pages{position:absolute;right:8px;top:8px;background:rgba(20,31,85,.85);color:#fff;font-size:11px;font-weight:700;padding:3px 8px;border-radius:20px}
.ep-card-body{padding:12px 14px 14px}
.ep-card-body b{display:block;font-size:14px;line-height:1.4;color:var(--navy)}
.ep-card-body small{display:flex;align-items:center;gap:5px;font-size:12px;color:var(--muted);margin-top:4px}
.ep-empty{background:#fff;border:1px solid var(--line);border-radius:14px;padding:50px 20px;text-align:center;color:var(--muted)}
.ep-empty i{font-size:40px;color:var(--blue)}
.ep-empty h3{font-size:19px;font-weight:800;color:var(--navy);margin:10px 0 6px}

/* Pagination */
.ep-pager{display:flex;justify-content:center;align-items:center;gap:8px;flex-wrap:wrap;margin-top:30px}
.ep-pager a,.ep-pager span{min-width:40px;height:40px;padding:0 14px;display:inline-flex;align-items:center;justify-content:center;gap:6px;border-radius:10px;border:1px solid var(--line);background:#fff;font-size:14px;font-weight:700;color:var(--navy)}
.ep-pager a:hover{border-color:var(--blue);color:var(--blue)}
.ep-pager .current{background:var(--blue);border-color:var(--blue);color:#fff}
.ep-pager .dots{border:0;background:none;min-width:auto;padding:0 4px}
.ep-pager .disabled{opacity:.45}

/* Reader */
.ep-reader{display:grid;grid-template-columns:150px minmax(0,1fr);gap:22px;margin-top:24px;align-items:start}
.ep-thumbs{position:sticky;top:96px;display:flex;flex-direction:column;gap:10px;max-height:calc(100vh - 120px);overflow:auto;padding-right:4px}
.ep-thumbs a{display:block;border:2px solid var(--line);border-radius:8px;overflow:hidden;background:#fff;position:relative}
.ep-thumbs a:hover{border-color:var(--blue)}
.ep-thumbs img{display:block;width:100%;height:auto}
.ep-thumbs span{position:absolute;left:6px;bottom:6px;background:rgba(20,31,85,.85);color:#fff;font-size:11px;font-weight:700;padding:2px 7px;border-radius:12px}
.ep-sheets{display:flex;flex-direction:column;gap:22px}
.ep-sheet{background:#fff;border:1px solid var(--line);border-radius:12px;box-shadow:var(--shadow);overflow:hidden;scroll-margin-top:96px}
.ep-sheet-head{display:flex;justify-content:space-between;align-items:center;padding:10px 16px;border-bottom:1px solid var(--line);font-size:13px;font-weight:700;color:var(--navy)}
.ep-sheet-head a{display:inline-flex;align-items:center;gap:5px;color:var(--blue);font-size:12px}
.ep-sheet img{display:block;width:100%;height:auto;background:#f3f5fa;min-height:300px}
.ep-note{background:#fff;border:1px solid var(--line);border-radius:12px;padding:16px 18px;font-size:14px;line-height:1.7}
.ep-actions{display:flex;gap:10px;flex-wrap:wrap}
.ep-btn{display:inline-flex;align-items:center;gap:7px;padding:10px 16px;border-radius:10px;font-size:13px;font-weight:700;background:rgba(255,255,255,.12);border:1px solid rgba(255,255,255,.22);color:#fff}
.ep-btn:hover{background:rgba(255,255,255,.2);color:#fff}
.ep-btn.gold{background:var(--gold);border-color:var(--gold);color:#141f55}
.ep-btn.gold:hover{background:#e5aa18;color:#141f55}
.ep-nav{display:flex;justify-content:space-between;gap:12px;margin-top:26px}
.ep-nav a{flex:1;max-width:48%;display:flex;flex-direction:column;gap:2px;background:#fff;border:1px solid var(--line);border-radius:12px;padding:14px 18px;box-shadow:var(--shadow)}
.ep-nav a:hover{border-color:var(--blue)}
.ep-nav a small{font-size:12px;color:var(--muted);font-weight:700}
.ep-nav a b{font-size:14px;color:var(--navy)}
.ep-nav a.older{text-align:right;margin-left:auto}

@media(max-width:900px){
  .ep-reader{grid-template-columns:1fr}
  .ep-thumbs{position:static;flex-direction:row;max-height:none;overflow-x:auto;padding:0 0 4px}
  .ep-thumbs a{flex:0 0 90px}
}
@media(max-width:600px){
  .ep-hero{padding:26px 0 22px}
  .ep-grid{grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}
  .ep-card-body{padding:10px}
  .ep-card-body b{font-size:13px}
  .ep-search{min-width:100%}
  .ep-nav{flex-direction:column}
  .ep-nav a{max-width:none}
}
