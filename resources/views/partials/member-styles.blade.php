{{-- Member login / register / dashboard styles. Raw CSS (included inside layouts.app's <style>) after subscription-styles --}}
/* Mobile number with a fixed +91 in front */
.mb-phone{display:flex;align-items:stretch;border:1px solid #d5ddeb;border-radius:10px;background:#fbfcff;overflow:hidden}
.mb-phone:focus-within{border-color:var(--blue);background:#fff;box-shadow:0 0 0 3px rgba(43,63,158,.12)}
.mb-phone span{display:flex;align-items:center;padding:0 12px;font-weight:800;color:var(--navy);background:#eef1fb;border-right:1px solid #d5ddeb}
.sp-form .mb-phone input{flex:1;min-width:0;border:0!important;box-shadow:none!important;background:transparent!important;font-size:16px;letter-spacing:.5px}
.mb-phone:has(input.is-invalid){border-color:#dc2626}
/* OTP: big, spaced digits */
.sp-form input.mb-otp{border:1px solid #d5ddeb;border-radius:10px;padding:12px 14px;font:inherit;font-size:24px;font-weight:800;letter-spacing:10px;text-align:center;background:#fbfcff;color:var(--navy)}
.sp-form input.mb-otp.is-invalid{border-color:#dc2626}
.mb-otp-actions{display:flex;justify-content:space-between;gap:10px;margin-top:14px}
.mb-otp-actions button{border:0;background:none;padding:4px 0;font:inherit;font-size:13px;font-weight:800;color:var(--blue);cursor:pointer}
.mb-otp-actions button:disabled{color:var(--muted);cursor:default}
.mb-auth{max-width:440px}
.mb-auth .sp-plan-top{padding:22px 24px}
.mb-auth .sp-plan-top h2{margin:0}
.mb-remember{display:flex!important;align-items:center;gap:8px;font-weight:500!important;color:var(--text)!important}
.mb-remember input{width:16px;height:16px;accent-color:var(--blue)}
.mb-switch{text-align:center;margin-top:16px;font-size:14px;color:var(--muted)}
.mb-switch a{color:var(--blue);font-weight:800}
.mb-new{text-align:center;padding:14px;border:1px dashed var(--line);border-radius:12px;background:#f8f9fe;margin:0 0 6px}
.mb-new b{display:block;font-size:14px;color:var(--navy);margin-bottom:10px}
.mb-new .sp-cta{display:block;text-decoration:none;color:#141f55}
.mb-new span{display:block;margin-top:8px;font-size:12px;color:var(--muted)}
.mb-ok{background:#ecfdf3;border:1px solid #bbf0d0;border-radius:12px;padding:12px 14px;margin:0 0 14px;font-size:13.5px;color:#067a4c}

/* Dashboard */
.mb-shell{max-width:1100px;margin:0 auto}
.mb-top{display:flex;justify-content:space-between;align-items:flex-end;gap:16px;flex-wrap:wrap;margin-bottom:20px}
.mb-top h1{font-size:clamp(22px,3vw,30px);font-weight:800;color:var(--navy);margin:8px 0 4px}
.mb-top p{margin:0;font-size:14px;color:var(--muted)}
.mb-top p b{color:#067a4c}
.mb-top-right{display:flex;align-items:center;gap:12px;flex-wrap:wrap}
.mb-period{display:flex;align-items:center;gap:14px;background:#fff;border:1px solid var(--line);border-radius:12px;padding:10px 16px;box-shadow:var(--shadow)}
.mb-period small{display:block;font-size:10.5px;font-weight:800;text-transform:uppercase;letter-spacing:.6px;color:var(--muted)}
.mb-period b{display:block;font-size:15px;color:var(--navy);margin-top:1px}
.mb-period>i{color:var(--blue)}
.mb-days{font-size:12px;font-weight:800;color:#067a4c;background:#ecfdf3;border:1px solid #bbf0d0;border-radius:20px;padding:4px 10px;white-space:nowrap}
.mb-days.mb-days-low{color:#991b1b;background:#fef2f2;border-color:#fecaca}
.mb-logout{border:1.5px solid var(--line);background:#fff;color:var(--navy);border-radius:10px;padding:9px 16px;font-size:13px;font-weight:700;cursor:pointer}
.mb-logout:hover{border-color:var(--blue);color:var(--blue)}


.mb-search{display:flex;align-items:center;gap:10px;flex-wrap:wrap;background:#fff;border:1px solid var(--line);border-radius:var(--radius);padding:12px 14px;margin:6px 0 22px}
.mb-search label{font-size:13px;font-weight:700;color:var(--navy)}
.mb-search input[type=date]{border:1px solid #d5ddeb;border-radius:10px;padding:8px 12px;font:inherit;font-size:14px;background:#fbfcff}
.mb-search button{border:0;border-radius:10px;padding:9px 18px;background:var(--blue);color:#fff;font-size:14px;font-weight:700;cursor:pointer}
.mb-search button:hover{background:var(--navy)}
.mb-search a{font-size:13px;font-weight:700;color:var(--muted)}
.mb-note{margin-top:14px;font-size:12px;color:var(--muted);text-align:center}

/* Daily stock picks: one row of cards per date (like the home page Technical Analysis cards, no pictures) */
.pk-day{margin-bottom:22px}
.pk-day-head{display:flex;align-items:baseline;justify-content:space-between;gap:10px;flex-wrap:wrap;margin-bottom:12px;padding-left:12px;border-left:4px solid var(--blue)}
.pk-day-head h2{margin:0;font-size:18px;font-weight:800;color:var(--navy)}
.pk-latest .pk-day-head{border-left-color:var(--gold)}
.pk-latest .pk-day-head h2{font-size:21px}
.pk-date{font-size:13px;font-weight:700;color:var(--muted)}
.pk-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:16px}
.pk-card{background:#fff;border:1px solid #e3e8f2;border-radius:14px;box-shadow:0 4px 14px rgba(20,31,85,.06);padding:16px;display:flex;flex-direction:column;gap:12px}
.pk-latest .pk-card{border-top:3px solid var(--gold)}
.pk-head{display:flex;align-items:flex-start;justify-content:space-between;gap:8px}
.pk-head h3{margin:0;font-size:15.5px;line-height:1.35;min-height:2.7em;font-weight:800;color:#141f55}
.pk-sym{flex-shrink:0;font-size:10.5px;font-weight:800;letter-spacing:.5px;color:#2b3f9e;background:#eef2fc;border-radius:6px;padding:3px 7px}
.pk-ach{flex-shrink:0;text-align:right;font-size:13px;color:#0a7a4b;background:#eaf7f0;border:1px solid #c9ead9;border-radius:8px;padding:3px 8px;line-height:1.25;max-width:55%;overflow-wrap:anywhere}
.pk-ach small{display:block;font-size:9px;font-weight:800;letter-spacing:.6px;text-transform:uppercase}
.pk-ach b{font-weight:800}
.pk-levels{display:grid;grid-template-columns:1fr 1fr;gap:8px}
.pk-levels>div{border-radius:10px;padding:7px 10px;border:1px solid}
.pk-levels small{display:block;font-size:9.5px;font-weight:800;letter-spacing:.6px;text-transform:uppercase}
.pk-levels b{display:block;font-size:15.5px;font-weight:800;color:#141f55;margin-top:1px;overflow-wrap:anywhere}
.pk-s{background:#fdf1f1;border-color:#f6d5d5!important}
.pk-s small{color:#c42b30}
.pk-r{background:#eef2fc;border-color:#d6def5!important}
.pk-r small{color:#2b3f9e}
/* CMP: neutral, full width above support / resistance */
.pk-c{grid-column:1/-1;background:#f6f7fb;border-color:#e3e7f2!important}
.pk-c small{color:#4b5675}
.pk-c small span{font-weight:600;letter-spacing:0;text-transform:none}
.pk-c b{font-size:17px}
/* When the stock was added / last changed */
.pk-time{font-size:11.5px;font-weight:600;color:#66728f;margin-top:-6px}
.pk-time .bi{margin-right:2px}
/* A stock with a description: the whole box opens it */
.pk-card[data-pick-open]{cursor:pointer;transition:box-shadow .15s ease,transform .15s ease,border-color .15s ease}
.pk-card[data-pick-open]:hover{box-shadow:0 10px 26px rgba(20,31,85,.14);border-color:#c9d3ee;transform:translateY(-2px)}
.pk-card[data-pick-open]:focus-visible{outline:3px solid #9fb0ea;outline-offset:2px}
.pk-more{font-size:12.5px;font-weight:800;color:var(--blue);display:flex;align-items:center;gap:5px}
.pk-card[data-pick-open]:hover .pk-more .bi{transform:translateX(3px)}
.pk-more .bi{transition:transform .15s ease}
/* The description dialog */
.pk-dialog{width:min(760px,calc(100vw - 24px));max-height:min(88vh,900px);padding:0;border:0;border-radius:16px;box-shadow:0 24px 70px rgba(10,16,48,.35);color:var(--text);overflow:hidden}
.pk-dialog[open]{display:flex;flex-direction:column}
.pk-dialog::backdrop{background:rgba(10,16,48,.55)}
.pk-dialog-head{display:flex;align-items:flex-start;justify-content:space-between;gap:12px;padding:18px 22px 14px;border-top:4px solid var(--gold);border-bottom:1px solid #e3e8f2;background:#f8f9fe}
.pk-dialog-head h2{margin:0 0 4px;font-size:21px;line-height:1.3;font-weight:800;color:#141f55}
.pk-dialog-head .pk-time{margin-top:0;font-size:12.5px}
.pk-dialog-close{flex-shrink:0;width:36px;height:36px;border:1px solid #dfe5f5;border-radius:10px;background:#fff;color:#141f55;font-size:15px;cursor:pointer}
.pk-dialog-close:hover{background:var(--blue2)}
.pk-dialog-scroll{padding:18px 22px 22px;overflow-y:auto;display:flex;flex-direction:column;gap:16px}
.pk-dialog-levels{display:grid;grid-template-columns:repeat(3,1fr);gap:10px}
.pk-dialog-levels>div{border-radius:10px;padding:9px 12px;border:1px solid}
.pk-dialog-levels small{display:block;font-size:10px;font-weight:800;letter-spacing:.6px;text-transform:uppercase}
.pk-dialog-levels b{display:block;font-size:17px;font-weight:800;color:#141f55;margin-top:1px;overflow-wrap:anywhere}
.pk-dialog-levels .pk-c{grid-column:auto}
.pk-a{background:#eaf7f0;border-color:#c9ead9!important}
.pk-a small{color:#0a7a4b}
.pk-dialog-levels .pk-a{grid-column:1/-1}
.pk-dialog-label{margin:0 0 -6px;font-size:12px;font-weight:800;letter-spacing:.7px;text-transform:uppercase;color:#66728f}
/* Text written in the admin's editor */
.pk-rich{font-size:15.5px;line-height:1.75;color:#1c2540;overflow-wrap:anywhere}
.pk-rich p{margin:0 0 12px}
.pk-rich > :first-child{margin-top:0}
.pk-rich > :last-child{margin-bottom:0}
.pk-rich h1,.pk-rich h2,.pk-rich h3,.pk-rich h4,.pk-rich h5,.pk-rich h6{color:#141f55;font-weight:800;line-height:1.35;margin:20px 0 8px}
.pk-rich h1{font-size:22px}.pk-rich h2{font-size:20px}.pk-rich h3{font-size:18px}.pk-rich h4,.pk-rich h5,.pk-rich h6{font-size:16px}
.pk-rich ul,.pk-rich ol{margin:0 0 14px;padding-left:24px}
.pk-rich li{margin-bottom:5px}
.pk-rich a{color:var(--blue);text-decoration:underline}
.pk-rich img{max-width:100%;height:auto;border-radius:10px;margin:6px 0}
.pk-rich blockquote{margin:0 0 16px;padding:10px 16px;border-left:4px solid var(--gold);background:#fff8e6;border-radius:6px}
.pk-rich table{width:100%;border-collapse:collapse;margin:0 0 16px;font-size:14.5px;display:block;overflow-x:auto}
.pk-rich th,.pk-rich td{border:1px solid #e3e8f2;padding:7px 10px;text-align:left}
.pk-rich th{background:#f3f5fb}
@media(max-width:560px){
  .pk-dialog-levels{grid-template-columns:1fr 1fr}
  .pk-dialog-levels .pk-c{grid-column:1/-1}
  .pk-dialog-head,.pk-dialog-scroll{padding-left:16px;padding-right:16px}
}
/* Short disclaimer at the bottom of each stock card */
.pk-disc{margin:0;padding:9px 11px;background:#fef2f2;border:1px solid #fca5a5;border-left:3px solid #dc2626;border-radius:9px;font-size:11.5px;font-weight:600;color:#7f1d1d;line-height:1.5}
.pk-disc b{color:#b91c1c;font-weight:800}
.pk-disc a{color:#991b1b;font-weight:800;text-decoration:underline}
/* "Today's data is updating soon" */
.pk-soon{display:flex;align-items:flex-start;gap:14px;background:#fff8e6;border:1px solid #f6dfa0;border-left:4px solid var(--gold);border-radius:14px;padding:18px 20px;color:#7a4e00;font-size:14px;line-height:1.55}
.pk-soon>i{font-size:26px;color:#d99a0b;line-height:1;animation:pk-turn 2.4s ease-in-out infinite}
.pk-soon b{display:block;font-size:17px;color:#5c3b00;margin-bottom:2px}
@keyframes pk-turn{0%,40%{transform:rotate(0)}50%,90%{transform:rotate(180deg)}100%{transform:rotate(360deg)}}
@media (prefers-reduced-motion: reduce){.pk-soon>i{animation:none}}
.pk-empty{background:#fff;border:1px dashed var(--line);border-radius:14px;padding:26px 18px;text-align:center;color:var(--muted);font-size:14px}
.pk-prev-bar{display:flex;justify-content:center;margin:4px 0 22px}
.pk-prev-btn{display:inline-flex;align-items:center;gap:8px;border:1.5px solid var(--blue);background:#fff;color:var(--blue);border-radius:30px;padding:10px 22px;font:inherit;font-size:14px;font-weight:800;cursor:pointer;transition:background .15s ease}
.pk-prev-btn:hover{background:var(--blue2)}
.pk-prev-btn .pk-chev{transition:transform .2s ease}
.pk-prev-btn.open .pk-chev{transform:rotate(180deg)}

@media(max-width:520px){
  .pk-grid{grid-template-columns:1fr}
  .pk-head h3{min-height:0}
  .mb-search{flex-direction:column;align-items:stretch}
  .mb-top-right,.mb-period{width:100%}
  .mb-period{flex-wrap:wrap;justify-content:space-between}
}