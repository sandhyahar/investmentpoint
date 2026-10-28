<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex, nofollow">
<title>Admin Login | Investment Point</title>
@include('partials.favicon')
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
:root{--navy:#0b1438;--blue:#2b3f9e;--blue-2:#4a63d8;--gold:#f4bd32;--ink:#0f1b3d;--muted:#7b86a3;--line:#dde3ef;--red:#dc3545}
*{box-sizing:border-box}
html,body{height:100%}
body{margin:0;font-family:'Inter','Segoe UI',system-ui,-apple-system,Roboto,Arial,sans-serif;-webkit-font-smoothing:antialiased;color:var(--ink);
  min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px;overflow:hidden;
  background:linear-gradient(135deg,#0b1438,#1b2b78,#2b3f9e,#14205e);background-size:300% 300%;animation:bgShift 18s ease infinite}
@keyframes bgShift{0%,100%{background-position:0% 50%}50%{background-position:100% 50%}}

/* Soft glowing shapes drifting behind the card */
.blob{position:fixed;border-radius:50%;filter:blur(60px);opacity:.55;pointer-events:none;animation:drift 16s ease-in-out infinite}
.blob.b1{width:380px;height:380px;background:#4a63d8;top:-120px;left:-100px}
.blob.b2{width:300px;height:300px;background:#f4bd32;bottom:-110px;right:-80px;opacity:.35;animation-delay:-5s;animation-duration:20s}
.blob.b3{width:220px;height:220px;background:#7c8cff;top:55%;left:12%;opacity:.3;animation-delay:-9s}
@keyframes drift{0%,100%{transform:translate(0,0) scale(1)}33%{transform:translate(40px,30px) scale(1.08)}66%{transform:translate(-30px,20px) scale(.95)}}

/* A market line drawing itself across the background */
.chart{position:fixed;left:0;right:0;bottom:6%;width:100%;height:42vh;pointer-events:none;opacity:.5}
.chart path{fill:none;stroke:url(#lineGrad);stroke-width:3;stroke-linecap:round;stroke-linejoin:round;stroke-dasharray:2600;stroke-dashoffset:2600;animation:draw 4.5s ease-out .3s forwards}
.chart .area{stroke:none;fill:url(#areaGrad);opacity:0;animation:fadeIn 1.6s ease 3s forwards}
@keyframes draw{to{stroke-dashoffset:0}}
@keyframes fadeIn{to{opacity:1}}

/* The sign-in card */
.card{position:relative;z-index:1;width:100%;max-width:400px;padding:38px 34px 34px;border-radius:24px;background:rgba(255,255,255,.96);
  box-shadow:0 30px 70px rgba(5,10,40,.45),0 0 0 1px rgba(255,255,255,.4) inset;backdrop-filter:blur(12px);-webkit-backdrop-filter:blur(12px);
  animation:cardIn .7s cubic-bezier(.2,.8,.2,1) both}
@keyframes cardIn{from{opacity:0;transform:translateY(26px) scale(.97)}to{opacity:1;transform:none}}
.card.shake{animation:shake .45s ease}
@keyframes shake{20%,60%{transform:translateX(-8px)}40%,80%{transform:translateX(8px)}}

.logo{width:76px;height:76px;margin:0 auto 16px;border-radius:22px;background:#fff;display:flex;align-items:center;justify-content:center;
  box-shadow:0 12px 28px rgba(43,63,158,.25);animation:float 4s ease-in-out infinite}
.logo img{width:58px;height:auto}
@keyframes float{0%,100%{transform:translateY(0)}50%{transform:translateY(-6px)}}
h1{margin:0 0 26px;text-align:center;font-size:22px;font-weight:800;letter-spacing:-.3px}

/* Fields with a label that floats up when you type */
.field{position:relative;margin-bottom:16px;opacity:0;animation:rise .5s ease forwards}
.field:nth-of-type(1){animation-delay:.25s}
.field:nth-of-type(2){animation-delay:.35s}
@keyframes rise{from{opacity:0;transform:translateY(10px)}to{opacity:1;transform:none}}
.field input{width:100%;height:56px;padding:22px 48px 6px 46px;border:1.5px solid var(--line);border-radius:14px;background:#f7f9fd;font:inherit;font-size:15px;color:var(--ink);outline:0;transition:border-color .2s,box-shadow .2s,background .2s}
.field input:focus{border-color:var(--blue);background:#fff;box-shadow:0 0 0 4px rgba(43,63,158,.13)}
.field input.is-invalid{border-color:var(--red)}
.field label{position:absolute;left:46px;top:50%;transform:translateY(-50%);font-size:15px;color:var(--muted);pointer-events:none;transition:all .18s ease}
.field input:focus+label,.field input:not(:placeholder-shown)+label{top:15px;transform:none;font-size:11.5px;font-weight:600;color:var(--blue)}
.field>i{position:absolute;left:16px;top:50%;transform:translateY(-50%);font-size:17px;color:#a1abc4;transition:color .2s}
.field:focus-within>i{color:var(--blue)}
.toggle-password{position:absolute;right:8px;top:50%;transform:translateY(-50%);width:38px;height:38px;border:0;border-radius:10px;background:transparent;color:var(--muted);font-size:17px;cursor:pointer;display:flex;align-items:center;justify-content:center}
.toggle-password:hover{background:#eef1fb;color:var(--blue)}
.error-msg{display:flex;gap:6px;align-items:flex-start;color:var(--red);font-size:12.5px;line-height:1.45;margin:-6px 2px 14px}
.notice{display:flex;gap:8px;align-items:flex-start;background:#fdecee;color:#a3212f;border-radius:12px;padding:10px 12px;font-size:13px;margin-bottom:16px}

/* Button with a light sweep */
.btn-login{position:relative;overflow:hidden;width:100%;height:54px;margin-top:6px;border:0;border-radius:14px;background:linear-gradient(135deg,var(--blue),var(--blue-2));color:#fff;font:inherit;font-size:15.5px;font-weight:700;cursor:pointer;
  display:flex;align-items:center;justify-content:center;gap:8px;box-shadow:0 12px 26px rgba(43,63,158,.35);transition:transform .15s,box-shadow .15s;opacity:0;animation:rise .5s ease .45s forwards}
.btn-login::after{content:"";position:absolute;top:0;left:-60%;width:40%;height:100%;background:linear-gradient(100deg,transparent,rgba(255,255,255,.35),transparent);transform:skewX(-20deg);animation:sweep 3.2s ease-in-out 1.2s infinite}
@keyframes sweep{0%{left:-60%}45%,100%{left:130%}}
.btn-login:hover{transform:translateY(-2px);box-shadow:0 16px 32px rgba(43,63,158,.42)}
.btn-login:hover i{transform:translateX(3px)}
.btn-login i{transition:transform .2s}
.btn-login:disabled{opacity:.6;cursor:not-allowed;transform:none}
.btn-login .spin{width:18px;height:18px;border:2.5px solid rgba(255,255,255,.4);border-top-color:#fff;border-radius:50%;animation:spin .7s linear infinite}
@keyframes spin{to{transform:rotate(360deg)}}

@media(max-width:480px){
  .card{padding:32px 22px 26px;border-radius:22px}
  .field input{font-size:16px} /* iPhones don't zoom in on 16px fields */
  .chart{height:30vh}
}
@media(prefers-reduced-motion:reduce){*,*::before,*::after{animation:none!important;transition:none!important}.field,.btn-login{opacity:1}.chart path{stroke-dashoffset:0}.chart .area{opacity:1}}
</style>
</head>
<body>

<span class="blob b1"></span><span class="blob b2"></span><span class="blob b3"></span>
<svg class="chart" viewBox="0 0 1200 300" preserveAspectRatio="none" aria-hidden="true">
  <defs>
    <linearGradient id="lineGrad" x1="0" x2="1"><stop offset="0" stop-color="#7c8cff"/><stop offset="1" stop-color="#f4bd32"/></linearGradient>
    <linearGradient id="areaGrad" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#f4bd32" stop-opacity=".18"/><stop offset="1" stop-color="#f4bd32" stop-opacity="0"/></linearGradient>
  </defs>
  <path class="area" d="M0 250 L90 230 L170 245 L260 200 L340 215 L430 160 L520 180 L600 130 L690 150 L780 95 L860 120 L950 70 L1040 90 L1120 40 L1200 55 L1200 300 L0 300 Z"/>
  <path d="M0 250 L90 230 L170 245 L260 200 L340 215 L430 160 L520 180 L600 130 L690 150 L780 95 L860 120 L950 70 L1040 90 L1120 40 L1200 55"/>
</svg>

<main @class(['card', 'shake' => $errors->any()])>
  <div class="logo"><img src="{{ asset('images/logo-bull.png') }}" alt="Investment Point"></div>
  <h1>Admin Login</h1>

  <form method="POST" action="{{ route('admin.login.submit') }}" id="loginForm">
    @csrf
    @if(config('admin.desktop_only'))
      {{-- Filled by the script below: real screen size + mouse/trackpad, so phones in "Desktop site" mode are caught too --}}
      <input type="hidden" name="device" id="deviceInfo" value="">
      <div id="desktopOnlyMsg" class="notice" style="display:none"><i class="bi bi-laptop"></i> <span>The admin panel can only be used on a computer or laptop.</span></div>
    @endif

    <div class="field">
      <input type="text" id="username" name="username" value="{{ old('username') }}" placeholder=" " required autofocus autocomplete="username" autocapitalize="none" spellcheck="false" @class(['is-invalid' => $errors->has('username')])>
      <label for="username">Username</label>
      <i class="bi bi-person"></i>
    </div>
    @error('username')<div class="error-msg"><i class="bi bi-exclamation-circle"></i> <span>{{ $message }}</span></div>@enderror

    <div class="field">
      <input type="password" id="password" name="password" placeholder=" " required autocomplete="current-password" @class(['is-invalid' => $errors->has('password')])>
      <label for="password">Password</label>
      <i class="bi bi-lock"></i>
      <button type="button" class="toggle-password" aria-label="Show password" data-toggle-password><i class="bi bi-eye"></i></button>
    </div>
    @error('password')<div class="error-msg"><i class="bi bi-exclamation-circle"></i> <span>{{ $message }}</span></div>@enderror

    <button type="submit" class="btn-login" id="loginBtn">Sign In <i class="bi bi-arrow-right"></i></button>
  </form>
</main>

<script>
  document.querySelectorAll('[data-toggle-password]').forEach(function (button) {
    button.addEventListener('click', function () {
      const input = document.getElementById('password');
      const isPassword = input.type === 'password';
      input.type = isPassword ? 'text' : 'password';
      button.setAttribute('aria-label', isPassword ? 'Hide password' : 'Show password');
      button.innerHTML = isPassword ? '<i class="bi bi-eye-slash"></i>' : '<i class="bi bi-eye"></i>';
    });
  });

  // Spinner while signing in (and no double submit)
  document.getElementById('loginForm').addEventListener('submit', function () {
    const btn = document.getElementById('loginBtn');
    setTimeout(function () { btn.disabled = true; btn.innerHTML = '<span class="spin"></span>'; }, 0);
  });

  // Computer / laptop check (only while ADMIN_DESKTOP_ONLY is on): a mouse or trackpad and a big enough screen (the server checks this value too)
  (function () {
    const field = document.getElementById('deviceInfo');
    if (!field) return;
    const hasMouse = window.matchMedia('(any-pointer: fine)').matches;
    // Screen size from whichever the browser reports; 0 = unknown (privacy settings, hidden window): don't block on it
    const w = Math.max(screen.width || 0, window.outerWidth || 0), h = Math.max(screen.height || 0, window.outerHeight || 0);
    const bigScreen = !w || !h || (Math.max(w, h) >= 1000 && Math.min(w, h) >= 600);
    const iPadAsMac = /Macintosh/.test(navigator.userAgent) && navigator.maxTouchPoints > 1 && !hasMouse;
    const desktop = hasMouse && bigScreen && !iPadAsMac;
    field.value = (desktop ? 'desktop' : 'mobile') + '|' + screen.width + 'x' + screen.height;
    if (!desktop) {
      document.getElementById('desktopOnlyMsg').style.display = 'flex';
      document.querySelectorAll('#loginForm input:not([type=hidden]), #loginForm button').forEach(el => { el.disabled = true; });
    }
  })();
</script>

</body>
</html>
