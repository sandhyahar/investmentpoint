/* Writer card at the top of full articles (partials.author-card) */
.ac-card{position:relative;display:flex;gap:22px;align-items:center;margin:0 0 24px;padding:20px 22px;border-radius:14px;background:linear-gradient(120deg,#f4f7ff 0%,#fff 70%);border:1px solid var(--line);border-left:4px solid var(--gold)}
.ac-photo{width:132px;height:132px;object-fit:cover;object-position:top;border-radius:14px;border:3px solid #fff;box-shadow:0 8px 22px rgba(16,37,63,.18);flex-shrink:0;background:#fff}
.ac-photo.is-logo{object-fit:contain;padding:12px}
.ac-info{min-width:0;padding-right:96px}
.ac-label{display:inline-block;font-size:11px;font-weight:800;letter-spacing:.6px;text-transform:uppercase;color:var(--blue);background:#e8edfb;padding:3px 10px;border-radius:20px}
.ac-card .ac-name{margin:8px 0 4px;font-size:24px;font-weight:800;color:var(--navy);line-height:1.3}
.ac-role{margin:0;font-size:14px;font-weight:600;color:#5f6b85}
.ac-role.is-sebi{color:#0f7a4a}
.ac-contacts{display:flex;flex-wrap:wrap;gap:8px;margin-top:12px}
.ac-contacts a{display:inline-flex;align-items:center;gap:6px;font-size:13px;font-weight:700;color:var(--navy);background:#fff;border:1px solid var(--line);border-radius:20px;padding:5px 12px;transition:border-color .15s,color .15s}
.ac-contacts a i{color:var(--blue)}
.ac-contacts a:hover{border-color:var(--blue);color:var(--blue)}
.ac-meta{display:flex;flex-wrap:wrap;gap:6px 16px;margin-top:12px;font-size:13px;font-weight:600;color:var(--muted)}
.ac-meta:empty{display:none}
.ac-meta i{margin-right:4px;color:var(--blue)}
.ac-card .ac-share{position:absolute;top:16px;right:16px}
@media(max-width:600px){
  .ac-card{flex-direction:column;text-align:center;padding:20px 16px;border-left-width:1px;border-top:4px solid var(--gold)}
  .ac-info{padding-right:0}
  .ac-contacts,.ac-meta{justify-content:center}
  .ac-photo{width:120px;height:120px}
  .ac-card .ac-name{font-size:21px}
  .ac-card .ac-share{position:static;margin-top:2px}
}
/* Like / dislike (blogs) */
.ac-react{display:inline-flex;gap:6px;margin-left:2px}
.ac-react-btn{display:inline-flex;align-items:center;gap:6px;padding:3px 11px;border-radius:20px;border:1px solid var(--line);background:#fff;font-size:13px;font-weight:700;color:var(--muted);cursor:pointer;transition:border-color .15s,color .15s,background .15s,transform .1s}
.ac-meta .ac-react-btn i{margin:0;color:inherit;font-size:14px}
.ac-react-btn:hover{border-color:var(--blue);color:var(--blue)}
.ac-react-btn:active{transform:scale(.94)}
.ac-react-btn.is-like.active{background:#e7f6ef;border-color:var(--green);color:var(--green)}
.ac-react-btn.is-dislike.active{background:#fdecec;border-color:var(--red);color:var(--red)}
.ac-react-btn[disabled]{opacity:.6;cursor:wait}
