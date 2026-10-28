{{-- Raw CSS on top of news.partials.styles --}}
.ta-grid{grid-template-columns:repeat(3,1fr)}
.ta-keylevels{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:12px;margin:0 0 20px}
.ta-keylevel{border-radius:12px;padding:18px 22px;border:0;color:#fff}
.ta-keylevel small{display:block;font-size:12px;font-weight:800;letter-spacing:.6px;text-transform:uppercase;opacity:.9}
.ta-keylevel strong{font-size:28px;font-weight:800;line-height:1.3}
/* Support = red, Resistance = blue */
.ta-keylevel.support{background:#dc2626}
.ta-keylevel.resistance{background:#2b3f9e}
.ta-keylevel.cmp{background:#141f55}
.bl-back{margin-top:16px}
@media(max-width:1000px){
  .ta-grid{grid-template-columns:repeat(2,1fr)}
}
@media(max-width:600px){
  .ta-grid{grid-template-columns:1fr}
}
