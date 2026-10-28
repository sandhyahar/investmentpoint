{{-- Legal pages (legal.blade.php), also loaded into the admin editor. Raw CSS: included inside a <style> tag --}}
.tc-page{background:var(--bg);padding-bottom:50px}

/* Hero */
.tc-hero{background:var(--premium-grad);color:#fff;padding:44px 0 50px}
.tc-kicker{display:inline-block;background:rgba(255,255,255,.12);color:#6ee7b7;font-size:11px;font-weight:800;letter-spacing:1px;text-transform:uppercase;padding:6px 12px;border-radius:20px}
.tc-hero h1{font-size:clamp(28px,4.2vw,44px);font-weight:900;line-height:1.1;margin:14px 0 10px;color:#fff;letter-spacing:-.5px}
.tc-hero p{font-size:15px;line-height:1.7;color:#c9d7e4;margin:0}
.tc-meta{display:flex;flex-wrap:wrap;gap:10px;margin-top:18px}
.tc-meta span{background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.16);border-radius:30px;padding:7px 14px;font-size:13px;color:#dfe6f5}
.tc-meta b{color:#ffd36a}

/* Layout */
.tc-body{display:grid;grid-template-columns:260px minmax(0,1fr);gap:24px;margin-top:28px;align-items:start}
.tc-side{position:sticky;top:90px}
.tc-card{background:#fff;border:1px solid var(--line);border-radius:14px;box-shadow:var(--shadow);padding:18px}
.tc-toc{display:flex;flex-direction:column;max-height:calc(100vh - 120px);overflow:auto}
.tc-toc h3{font-size:13px;font-weight:800;text-transform:uppercase;letter-spacing:.6px;color:var(--muted);margin:0 0 6px}
.tc-toc b{font-size:11px;font-weight:800;text-transform:uppercase;letter-spacing:.5px;color:var(--blue);margin:12px 0 4px}
.tc-toc a{display:flex;align-items:baseline;gap:4px;font-size:13px;color:var(--text);padding:5px 8px;border-radius:6px}
.tc-toc a:hover{background:var(--blue2);color:var(--blue)}
.tc-num{flex-shrink:0;min-width:30px;font-size:11px;font-weight:800;color:var(--blue)}

/* Document */
.tc-doc{background:#fff;border:1px solid var(--line);border-radius:14px;box-shadow:var(--shadow);padding:34px 40px;color:#293f51;font-size:15px;line-height:1.8}
.tc-doc a{color:var(--blue);font-weight:700}
.tc-doc p{margin:0 0 14px}
.tc-doc ul{margin:0 0 14px;padding-left:20px}
.tc-doc li{margin:0 0 8px}
.tc-doc li::marker{color:var(--blue)}
.tc-doc b{color:var(--navy)}

.tc-entity{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px 24px;background:var(--blue2);border-radius:12px;padding:18px 20px;margin-bottom:10px}
.tc-entity div:first-child,.tc-entity div:nth-child(3){grid-column:1/-1}
.tc-entity span{display:block;font-size:11px;font-weight:800;text-transform:uppercase;letter-spacing:.5px;color:var(--muted)}
.tc-entity p{margin:2px 0 0;font-size:14px;color:var(--text);line-height:1.6}

.tc-part{display:flex;align-items:center;gap:12px;flex-wrap:wrap;font-size:21px;font-weight:800;color:var(--navy);margin:34px 0 6px;padding-bottom:12px;border-bottom:2px solid var(--line)}
.tc-part span{background:var(--blue);color:#fff;font-size:11px;font-weight:800;letter-spacing:.8px;text-transform:uppercase;padding:5px 10px;border-radius:6px}

.tc-sec{padding-top:18px;scroll-margin-top:100px}
.tc-doc h3[id]{scroll-margin-top:100px}
.tc-sec h3{display:flex;align-items:center;gap:10px;font-size:17px;font-weight:800;color:var(--navy);margin:0 0 10px}
.tc-sec h3 em{font-style:normal;flex-shrink:0;width:28px;height:28px;border-radius:50%;background:var(--blue2);color:var(--blue);font-size:13px;display:inline-flex;align-items:center;justify-content:center}
.tc-note{font-size:14px;color:#9a3412;background:#fff4ed;border-radius:8px;padding:10px 14px}
.tc-warn{background:#fff8e6;border-left:4px solid var(--gold);border-radius:10px;padding:18px 20px 6px;margin-top:18px}

.tc-officer{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:1px;background:var(--line);border:1px solid var(--line);border-radius:10px;overflow:hidden;margin-bottom:14px}
.tc-officer div{background:#fff;padding:12px 16px}
.tc-officer span{display:block;font-size:11px;font-weight:800;text-transform:uppercase;letter-spacing:.5px;color:var(--muted)}
.tc-officer b{font-size:14px;font-weight:600;line-height:1.5}

/* Breadcrumb in the hero */
.tc-crumbs{font-size:13px;color:#aebfd0;margin-bottom:14px}
.tc-crumbs a{color:#fff;font-weight:600}
.tc-crumbs a:hover{color:#ffd36a}
.tc-crumbs span{margin:0 8px;opacity:.7}

/* Refund page: warning banner, exception cards, claim steps */
.tc-alert{display:flex;gap:14px;align-items:flex-start;background:#fff4f4;border:1px solid #fcd4d6;border-left:4px solid #dc2626;border-radius:12px;padding:16px 18px;margin-bottom:6px}
.tc-alert>span{font-size:22px;line-height:1.2}
.tc-alert b{display:block;color:#b4232b;font-size:15px}
.tc-alert p{margin:4px 0 0;font-size:14px}
.tc-cases{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;margin:4px 0 18px}
.tc-cases div{position:relative;background:#f6f8fc;border:1px solid var(--line);border-radius:12px;padding:16px 18px 14px 58px}
.tc-cases em{position:absolute;left:16px;top:16px;font-style:normal;width:28px;height:28px;border-radius:50%;background:var(--blue);color:#fff;font-size:13px;font-weight:800;display:flex;align-items:center;justify-content:center}
.tc-cases b{display:block;margin-bottom:2px}
.tc-cases p{margin:0;font-size:14px;line-height:1.6}
.tc-sub{font-size:15px;font-weight:800;color:var(--navy);margin:0 0 10px}
.tc-steps{display:grid;gap:10px;margin-bottom:14px}
.tc-steps div{display:flex;gap:12px;align-items:flex-start;border:1px solid var(--line);border-radius:10px;padding:12px 16px}
.tc-steps span{font-size:18px;line-height:1.5}
.tc-steps p{margin:0;font-size:14px}

/* Privacy page: intro, highlight pills, info grid, 3-up rights cards */
.tc-lead{font-size:16px;color:var(--text)}
.tc-pills{display:flex;flex-wrap:wrap;gap:8px;margin:0 0 6px}
.tc-pills span{background:var(--blue2);color:var(--navy);font-size:13px;font-weight:600;padding:7px 12px;border-radius:30px}
.tc-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;margin-bottom:14px}
.tc-grid div{background:#f6f8fc;border:1px solid var(--line);border-radius:12px;padding:16px 18px}
.tc-grid span{font-size:22px}
.tc-grid b{display:block;margin:6px 0 4px}
.tc-grid p{margin:0;font-size:14px;line-height:1.6}
.tc-cases-3{grid-template-columns:repeat(3,minmax(0,1fr))}

.tc-disc{background:#f6f8fc;border:1px solid var(--line);border-radius:12px;padding:16px 18px;margin-bottom:14px}
.tc-disc b{display:block;margin-bottom:6px}
.tc-disc p{font-size:14px;margin:0}

/* Disclaimer page: "applies to" box, roman-numbered parts, keyword tags */
.dc-main{display:flex;flex-direction:column;gap:24px;min-width:0}
.dc-applies{background:var(--blue2);border-radius:12px;padding:16px 20px;margin-bottom:6px}
.dc-applies b{display:block;font-size:11px;font-weight:800;text-transform:uppercase;letter-spacing:.6px;color:var(--muted);margin-bottom:4px}
.dc-applies p{margin:0;font-size:14px}
.dc-part{border-bottom:1px solid var(--line);padding-bottom:10px}
.tc-sec h3 em.dc-num{width:auto;min-width:28px;padding:0 9px;border-radius:14px;background:var(--blue);color:#fff;font-size:11px;letter-spacing:.5px}
.dc-h{font-size:15px;font-weight:800;color:var(--navy);margin:18px 0 6px}
.dc-tags{display:flex;flex-wrap:wrap;gap:8px;margin:2px 0 14px}
.dc-tags span{background:#f1f4fb;border:1px solid #dfe5f3;color:var(--navy);font-size:13px;font-weight:600;padding:5px 12px;border-radius:30px}
/* Disclaimer page: points whose label is a tag (same look as .dc-tags) with the sentence under it */
.dc-terms{display:flex;flex-direction:column;gap:14px;margin:4px 0 14px}
.dc-terms span{display:inline-block;background:#f1f4fb;border:1px solid #dfe5f3;color:var(--navy);font-size:13px;font-weight:600;padding:5px 12px;border-radius:30px;margin-bottom:6px}
.dc-terms p{margin:0}
.dc-risk{margin:6px 0 0!important;padding-left:18px!important}
.dc-risk li{font-size:14px;margin-bottom:6px!important}

/* Disclaimer page: "Ask anything" form */
.dc-ask{background:#fff;border:1px solid var(--line);border-radius:14px;box-shadow:var(--shadow);padding:28px 32px;scroll-margin-top:100px}
.dc-ask-head{display:flex;gap:14px;align-items:flex-start;margin-bottom:18px}
.dc-ask-head>span{flex-shrink:0;width:46px;height:46px;border-radius:12px;background:var(--blue);color:#fff;font-size:20px;display:flex;align-items:center;justify-content:center}
.dc-ask-head h2{font-size:21px;font-weight:800;color:var(--navy);margin:0 0 2px}
.dc-ask-head p{font-size:14px;color:var(--muted);margin:0}
.dc-done{background:#ecfdf3;border:1px solid #bbf0d0;color:#067a4c;font-weight:600;font-size:14px;border-radius:10px;padding:12px 16px;margin-bottom:16px}
.dc-form{display:flex;flex-direction:column;gap:16px}
.dc-row{display:grid;grid-template-columns:1fr 1fr;gap:16px}
.dc-field{display:flex;flex-direction:column;gap:6px;min-width:0}
.dc-field>label{font-size:13px;font-weight:700;color:var(--navy)}
.dc-field>label span{font-weight:500;color:var(--muted)}
.dc-field input[type=text],.dc-field textarea{width:100%;border:1px solid #d5ddeb;border-radius:10px;padding:11px 14px;font:inherit;font-size:14px;color:var(--text);background:#fbfcff;transition:border-color .15s,box-shadow .15s}
.dc-field textarea{resize:vertical;min-height:140px;line-height:1.6}
.dc-field input::placeholder,.dc-field textarea::placeholder{color:#9aabbb}
.dc-field input:focus,.dc-field textarea:focus{outline:0;border-color:var(--blue);background:#fff;box-shadow:0 0 0 3px rgba(43,63,158,.12)}
.dc-field .is-invalid{border-color:#dc2626}
.dc-err{color:#dc2626;font-size:12px;font-weight:600}
.dc-drop{position:relative;display:flex;flex-direction:column;align-items:center;gap:4px;border:2px dashed #c9d3ea;border-radius:12px;background:#f8faff;padding:22px 16px;text-align:center;font-size:14px;color:var(--muted);cursor:pointer;transition:border-color .15s,background .15s}
.dc-drop:hover,.dc-drop.over{border-color:var(--blue);background:var(--blue2)}
.dc-drop i{font-size:28px;color:var(--blue)}
.dc-drop b{color:var(--blue)}
.dc-drop small{font-size:12px}
.dc-drop input{position:absolute;inset:0;opacity:0;cursor:pointer}
.dc-files{list-style:none;margin:0;padding:0;display:flex;flex-direction:column;gap:6px}
.dc-files li{display:flex;align-items:center;gap:8px;border:1px solid var(--line);border-radius:8px;padding:8px 12px;font-size:13px}
.dc-files li span{flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.dc-files li small{color:var(--muted)}
.dc-send{align-self:flex-start;display:inline-flex;align-items:center;gap:8px;background:var(--blue);color:#fff;border:0;border-radius:10px;padding:12px 22px;font-size:14px;font-weight:700;cursor:pointer}
.dc-send:hover{background:var(--navy)}
.dc-hp{position:absolute;left:-9999px;width:1px;height:1px;opacity:0}

@media(max-width:900px){
  .tc-body{grid-template-columns:1fr}
  .tc-side{display:none}
}
@media(max-width:640px){
  .tc-hero{padding:32px 0 36px}
  .tc-doc{padding:22px 18px;font-size:14px}
  .tc-entity,.tc-officer,.tc-cases,.tc-grid{grid-template-columns:1fr}
  .dc-ask{padding:22px 18px}
  .dc-row{grid-template-columns:1fr}
  .dc-send{align-self:stretch;justify-content:center}
}
