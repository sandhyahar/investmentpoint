{{-- Writer-first cards, shared by Expert's View and Reader Blogs. Raw CSS, included inside the layout's <style> tag --}}
/* Card + its Share button (the button sits outside the card link, floating on its top-right corner) */
.xv-share-wrap{position:relative;display:flex;flex-direction:column}
.xv-share-wrap > a{flex:1}
.xv-share-wrap .ip-share-btn.on-card{transition:transform .18s ease,background .15s,color .15s}
.xv-share-wrap:hover .xv-card + .ip-share-btn,.xv-share-wrap:hover .xb-card + .ip-share-btn{transform:translateY(-4px)}
.xv-feature-wrap{margin-top:30px}
.xv-feature-wrap .xv-feature{margin-top:0}
.xv-feature-wrap .ip-share-btn.on-card{top:16px;right:16px}

/* Writer-first cards (reader blogs have no cover picture) */
.xb-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:20px}
.xb-card{display:flex;flex-direction:column;gap:10px;background:#fff;border:1px solid var(--line);border-top:4px solid var(--gold);border-radius:14px;padding:18px 20px;box-shadow:var(--shadow);color:var(--text);transition:transform .18s ease,box-shadow .18s ease}
.xb-card:hover{transform:translateY(-4px);box-shadow:0 14px 30px rgba(27,42,107,.14);color:var(--text)}
.xb-card:hover .xv-btn.sm{gap:11px}
.xb-writer{display:flex;align-items:center;gap:12px;padding:0 42px 12px 0;border-bottom:1px solid var(--line)}
.xb-writer img{width:50px;height:50px;border-radius:50%;object-fit:cover;object-position:top;background:#fff;border:2px solid #fff;box-shadow:0 0 0 2px var(--blue2);flex-shrink:0}
.xb-writer img.is-logo{object-fit:contain;padding:4px}
.xb-writer b{display:block;font-size:15px;font-weight:800;color:var(--navy)}
.xb-writer small{display:block;font-size:12.5px;color:#5f6b85;margin-top:1px}
/* Expert's View articles: blue top line (reader blogs are gold) */
.xb-card.is-article{border-top-color:var(--blue)}
.xb-card.is-article .xb-writer img{width:56px;height:56px}
/* Featured article: text-only card, round picture in the writer row */
.xv-feature.is-person{grid-template-columns:1fr;border-top:4px solid var(--gold)}
.xv-feature.is-person .xb-writer{padding-right:0}
.xv-feature.is-person .xb-writer img{width:64px;height:64px}
.xv-feature.is-person .xv-btn{margin-top:4px}
.xb-card h3{font-size:17px;font-weight:800;line-height:1.4;color:var(--navy);margin:2px 0 0;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}
.xb-card p{font-size:13.5px;line-height:1.6;color:#5f6b85;margin:0;display:-webkit-box;-webkit-line-clamp:3;-webkit-box-orient:vertical;overflow:hidden}
.xb-foot{margin-top:auto;display:flex;justify-content:space-between;align-items:center;gap:10px;font-size:12px;color:var(--muted);padding-top:4px}
@media(max-width:1000px){.xb-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media(max-width:700px){.xb-grid{grid-template-columns:1fr}}
