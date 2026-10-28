<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex, nofollow">
<title>Computer only | Investment Point</title>
@include('partials.favicon')
<style>
*{box-sizing:border-box}
body{margin:0;font-family:'Segoe UI',Arial,sans-serif;background:linear-gradient(135deg,#141f55 0%,#2b3f9e 50%,#141f55 100%);min-height:100vh;display:flex;align-items:center;justify-content:center;padding:16px}
.card{background:#fff;border-radius:16px;box-shadow:0 20px 60px rgba(0,0,0,.3);padding:34px 26px;width:100%;max-width:400px;text-align:center}
.icon{font-size:46px;line-height:1;margin-bottom:12px}
h1{font-size:20px;font-weight:800;color:#10253f;margin:0 0 10px}
p{font-size:14px;color:#5f6b85;line-height:1.6;margin:0 0 18px}
a{display:inline-block;background:#2b3f9e;color:#fff;text-decoration:none;font-weight:700;font-size:14px;padding:11px 22px;border-radius:10px}
</style>
</head>
<body>
  <div class="card">
    <div class="icon">💻</div>
    <h1>Admin panel: computer only</h1>
    <p>For security, the admin panel can only be used on a computer or laptop. Signing in or making changes from a phone or tablet is not allowed.</p>
    <a href="{{ url('/') }}">Go to the website</a>
  </div>
</body>
</html>
