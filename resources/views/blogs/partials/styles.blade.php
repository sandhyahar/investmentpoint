{{-- Raw CSS on top of news.partials.styles --}}
.bl-item{padding:20px 22px;margin-bottom:14px;border-left:4px solid var(--blue);transition:transform .15s ease,box-shadow .15s ease}
.bl-item:hover{transform:translateY(-2px);box-shadow:0 10px 26px rgba(16,37,63,.12)}
.bl-item h2{font-size:19px;line-height:1.35;font-weight:800;color:var(--navy);margin:0 0 6px}
.bl-item h2 a:hover{color:var(--blue)}
.bl-meta{display:flex;flex-wrap:wrap;align-items:center;gap:6px 14px;font-size:12px;color:var(--muted);margin-bottom:10px}
.bl-writer{display:inline-flex;align-items:center;gap:8px}
.bl-writer b{color:var(--text);font-size:13px}
.bl-writer img,.bl-byline img{border-radius:50%;object-fit:cover;background:#fff;border:1px solid var(--line);flex-shrink:0}
.bl-writer img{width:30px;height:30px}
.bl-writer img.is-logo,.bl-byline img.is-logo{object-fit:contain;padding:3px}
.bl-byline{display:flex;align-items:center;gap:12px;padding-bottom:16px;margin-bottom:18px;border-bottom:1px solid var(--line)}
.bl-byline img{width:52px;height:52px;box-shadow:0 4px 12px rgba(27,42,107,.1)}
.bl-byline b{display:block;font-size:15px;color:var(--text)}
.bl-byline small{font-size:12px;color:var(--muted)}
.bl-byline .ip-share-btn{margin-left:auto;flex-shrink:0}
/* Optional writer designation + RA / IA / CA number under the name */
.bl-cred{display:flex;flex-wrap:wrap;gap:0 6px;font-size:12px;font-weight:600;color:#3f566a;line-height:1.5}
.bl-cred>span+span:before{content:"·";margin-right:6px;color:var(--muted)}
.bl-writer .bl-cred{font-size:11.5px}
.bl-cred b,.bl-writer .bl-cred b,.bl-byline .bl-cred b{display:inline;font-size:inherit;font-weight:800;color:var(--blue)}
.bl-byline .bl-cred{margin:1px 0 2px}
.bl-item p{font-size:14px;line-height:1.65;color:#3f566a;margin:0 0 12px}
.bl-read{font-size:12px;font-weight:800;color:var(--blue)}
.bl-read:hover{text-decoration:underline}
.bl-back{margin-top:16px}
.bl-cta p{font-size:13px;color:var(--muted);margin:0 0 12px}
.bl-side-item{display:grid;grid-template-columns:22px 1fr;gap:10px;align-items:center;padding:10px 0;border-bottom:1px solid var(--line)}
.bl-side-item:last-child{border-bottom:0;padding-bottom:0}
.bl-side-item .np-rank{font-size:18px;font-weight:800;color:#c5d2de}
.bl-side-item h4{font-size:13px;line-height:1.4;font-weight:700;margin:0 0 3px;color:var(--text)}
.bl-side-item:hover h4{color:var(--blue)}
.bl-side-item small{font-size:10px;color:var(--muted)}
@media(max-width:600px){
  .bl-item{padding:16px}
  .bl-item h2{font-size:17px}
}
