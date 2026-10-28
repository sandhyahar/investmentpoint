{{-- Subscription page + payment / receipt page styles. Raw CSS (included inside layouts.app's <style>) --}}
.sp-page{background:var(--bg);padding:34px 20px 50px}
.sp-shell{max-width:880px;margin:0 auto}

.sp-head{text-align:center;margin-bottom:26px}
.sp-head h1{font-size:clamp(26px,3.6vw,38px);font-weight:800;color:var(--navy);margin:10px 0 8px;letter-spacing:-.5px}
.sp-head p{font-size:15px;color:var(--muted);max-width:560px;margin:0 auto}

/* PLAN CARD */
.sp-plan{position:relative;max-width:500px;margin:0 auto;background:#fff;border:1px solid var(--line);border-radius:18px;box-shadow:0 18px 44px rgba(16,37,63,.12);overflow:hidden}
.sp-plan-top{background:var(--premium-grad);color:#fff;padding:30px 28px 26px;text-align:center}
.sp-plan-top small{display:inline-block;font-size:11px;font-weight:800;letter-spacing:1px;text-transform:uppercase;color:#6ee7b7;margin-bottom:12px}
.sp-plan-top h2{font-size:22px;font-weight:800;color:#fff;margin:6px 0 14px}
.sp-price{display:flex;align-items:baseline;justify-content:center;gap:6px}
.sp-price strong{font-size:48px;line-height:1;font-weight:900;color:#ffd36a;letter-spacing:-1px}
.sp-price span{font-size:15px;font-weight:700;color:#c9d7e4}
.sp-permonth{font-size:12px;color:#aebfd0;margin:8px 0 0}

.sp-plan-body{padding:24px 28px 28px}
.sp-plan-body h3{font-size:13px;font-weight:800;text-transform:uppercase;letter-spacing:.6px;color:var(--muted);margin:0 0 10px}
.sp-benefits{list-style:none;margin:0 0 22px;padding:0}
.sp-benefits li{display:flex;align-items:center;gap:12px;padding:11px 0;border-bottom:1px solid var(--line);font-size:15px;font-weight:600;color:var(--text)}
.sp-benefits li:last-child{border-bottom:0}
.sp-benefits li span{flex-shrink:0;width:30px;height:30px;border-radius:8px;background:var(--blue2);display:flex;align-items:center;justify-content:center;font-size:17px}
/* What subscribers get after paying */
.sp-after{background:#eef2fc;border:1px solid #d6def5;border-left:4px solid var(--blue);border-radius:12px;padding:12px 14px;margin:0 0 14px}
.sp-after b{display:block;font-size:14px;color:var(--navy);margin-bottom:3px}
.sp-after b i{color:var(--blue);margin-right:4px}
.sp-after p{margin:0;font-size:13.5px;line-height:1.55;color:var(--text)}
/* Educational only / no recommendations */
.sp-note{display:flex;flex-direction:column;gap:6px;background:#fff8e6;border:1px solid #f6dfa0;border-radius:12px;padding:12px 14px;margin:0 0 20px;font-size:14px;font-weight:700;color:#7a4e00}
.sp-note span{display:inline-block;width:22px}
/* Subscribe form */
.sp-form{display:flex;flex-direction:column}
.sp-form label{font-size:13px;font-weight:700;color:var(--navy);margin:10px 0 5px}
.sp-form label span{font-weight:500;color:var(--muted)}
.sp-form input[type=text],.sp-form input[type=tel],.sp-form input[type=email]{border:1px solid #d5ddeb;border-radius:10px;padding:11px 14px;font:inherit;font-size:14px;background:#fbfcff}
.sp-form input:focus{outline:0;border-color:var(--blue);background:#fff;box-shadow:0 0 0 3px rgba(43,63,158,.12)}
.sp-form input.is-invalid{border-color:#dc2626}
.sp-err{color:#dc2626;font-size:12px;font-weight:600;margin-top:4px}
.sp-agree{display:flex!important;gap:10px;align-items:flex-start;margin:16px 0 4px!important;padding:12px;border:1px solid var(--line);border-radius:10px;background:#f8f9fe;font-weight:500!important;font-size:13px!important;line-height:1.55;color:var(--text)!important;cursor:pointer}
.sp-agree input{flex-shrink:0;width:18px;height:18px;margin-top:2px;accent-color:var(--blue);cursor:pointer}
.sp-agree a{color:var(--blue);font-weight:700;text-decoration:underline}
.sp-form .sp-cta{margin-top:14px}
.sp-cta:disabled{opacity:.5;cursor:not-allowed;transform:none;box-shadow:none}
.sp-hint{text-align:center;font-size:12px;color:var(--muted);margin-top:6px}
.sp-hp{position:absolute;left:-9999px;width:1px;height:1px;opacity:0}
.sp-done{background:#ecfdf3;border:1px solid #bbf0d0;border-radius:12px;padding:16px;color:#067a4c}
.sp-done b{display:block;font-size:15px}
.sp-done p{margin:6px 0 0;font-size:14px;color:#1f5f45}
.sp-cta{width:100%;border:0;border-radius:12px;padding:15px;background:var(--gold);color:#141f55;font-size:16px;font-weight:800;cursor:pointer;box-shadow:0 8px 20px rgba(244,189,50,.3);transition:background .15s ease,transform .15s ease}
.sp-cta:hover{background:#e5aa18;transform:translateY(-1px)}
.sp-contact{margin-top:18px;padding:14px;border:1px dashed var(--line);border-radius:12px;text-align:center;background:#f8f9fe}
.sp-contact small{display:block;font-size:12px;color:var(--muted);margin-bottom:2px}
.sp-contact b{display:block;font-size:17px;color:var(--navy)}
.sp-contact a{display:block;margin-top:4px;font-size:16px;font-weight:800;color:var(--blue);word-break:break-word}
.sp-terms{margin:16px 0 0;padding:12px 14px;border-left:3px solid var(--blue);border-radius:8px;background:#f4f6fd;font-size:12.5px;line-height:1.6;color:var(--text)}
.sp-terms b{display:block;font-size:13px;color:var(--navy);margin-bottom:4px}
.sp-terms p{margin:0 0 6px}
.sp-terms p:last-child{margin:0}

/* STEPS */
.sp-steps{display:grid;grid-template-columns:repeat(3,1fr);gap:14px;margin-top:30px}
.sp-step{display:flex;gap:12px;align-items:flex-start;background:#fff;border:1px solid var(--line);border-radius:var(--radius);box-shadow:var(--shadow);padding:16px}
.sp-step span{flex-shrink:0;width:30px;height:30px;border-radius:50%;background:var(--blue);color:#fff;font-weight:800;display:flex;align-items:center;justify-content:center}
.sp-step b{display:block;font-size:14px;color:var(--text)}
.sp-step small{font-size:12px;color:var(--muted)}

@media(max-width:720px){
  .sp-steps{grid-template-columns:1fr}
}
@media(max-width:520px){
  .sp-page{padding:22px 12px 36px}
  .sp-plan-top{padding:44px 20px 22px}
  .sp-plan-body{padding:20px}
  .sp-price strong{font-size:40px}
}

/* Payment / receipt page */
.sp-secure{display:block;text-align:center;font-size:12px;color:var(--muted);margin-top:8px}
.sp-secure i{color:#16a34a}
.sp-summary{border:1px solid var(--line);border-radius:12px;overflow:hidden;margin:0 0 18px}
.sp-summary div{display:flex;justify-content:space-between;gap:12px;padding:10px 14px;font-size:14px;border-bottom:1px solid var(--line)}
.sp-summary div:last-child{border-bottom:0}
.sp-summary span{color:var(--muted)}
.sp-summary b{color:var(--navy);text-align:right;word-break:break-all}
.sp-summary .sp-total{background:#f8f9fe}
.sp-summary .sp-total b{font-size:18px}
.sp-period{display:grid;grid-template-columns:1fr auto 1fr;align-items:center;gap:10px;background:#f4f6fd;border:1px solid #dbe2f5;border-radius:12px;padding:14px;margin:0 0 18px;text-align:center}
.sp-period small{display:block;font-size:11px;font-weight:800;text-transform:uppercase;letter-spacing:.6px;color:var(--muted)}
.sp-period b{display:block;font-size:17px;color:var(--navy);margin-top:2px}
.sp-period i{color:var(--blue);font-size:18px}
.sp-demo{background:#fff8e6;border:1px dashed #e0b24a;border-radius:12px;padding:12px 14px;margin:0 0 14px;font-size:13px;color:#7a4e00;line-height:1.55}
.sp-demo b{display:block;font-size:13.5px}
.sp-error{background:#fef2f2;border:1px solid #fecaca;border-radius:12px;padding:12px 14px;margin:0 0 14px;font-size:13.5px;color:#991b1b}
.sp-badge{display:inline-block;font-size:10.5px;font-weight:800;letter-spacing:.5px;padding:2px 8px;border-radius:20px;background:#fde68a;color:#7a4e00;vertical-align:middle}
.sp-done .sp-wa{margin-top:10px;font-size:13px;color:#1f5f45}
.sp-done .sp-wa i{color:#25d366}
.sp-back{display:block;text-align:center;margin-top:14px;font-size:13px;font-weight:700;color:var(--blue)}
