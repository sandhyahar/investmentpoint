{{-- Blog approved email (App\Mail\BlogApprovedMail). Inline styles only: email apps ignore <style> blocks. --}}
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>{{ $blog->title }}</title>
</head>
<body style="margin:0;padding:0;background:#eef2f8;font-family:Arial,Helvetica,sans-serif;color:#1f3448">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#eef2f8;padding:24px 12px">
  <tr><td align="center">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:640px;background:#ffffff;border-radius:12px;overflow:hidden">
      <tr><td style="background:#141f55;padding:18px 24px">
        <a href="{{ url('/') }}" style="color:#ffffff;font-size:20px;font-weight:bold;text-decoration:none">Investment Point</a>
        <div style="color:#f4bd32;font-size:11px;letter-spacing:1px;margin-top:2px">NEWS &bull; MARKETS &bull; INSIGHTS</div>
      </td></tr>

      <tr><td style="padding:24px 24px 0">
        <div style="background:#e8f7ef;border:1px solid #bbe8cf;border-radius:10px;padding:14px 16px;font-size:15px;line-height:1.6;color:#0a6a40">
          <b>Congratulations, {{ $blog->writer_name }}!</b><br>
          Your blog has been approved and is now published on Investment Point.
        </div>
        <p style="margin:16px 0 0"><a href="{{ $blogUrl }}" style="display:inline-block;background:#2b3f9e;color:#ffffff;font-weight:bold;font-size:14px;text-decoration:none;padding:12px 20px;border-radius:8px">View your blog &rarr;</a></p>
        <p style="font-size:12px;color:#5f6b85;margin:8px 0 0">Share this link with your readers: <a href="{{ $blogUrl }}" style="color:#2b3f9e">{{ $blogUrl }}</a></p>
      </td></tr>

      <tr><td style="padding:24px">
        <div style="display:inline-block;background:#eaeefb;color:#2b3f9e;font-size:11px;font-weight:bold;letter-spacing:.5px;text-transform:uppercase;padding:4px 10px;border-radius:20px">Expert's View &middot; Blog</div>
        <h1 style="font-size:24px;line-height:1.35;color:#141f55;margin:12px 0 4px">{{ $blog->title }}</h1>
        <div style="font-size:13px;color:#5f6b85;margin:0 0 16px">
          By {{ $blog->writer_name }}@if($blog->author_designation) &middot; {{ $blog->author_designation }}@endif
          @if($blog->approved_at) &middot; {{ $blog->approved_at->timezone('Asia/Kolkata')->format('d M Y') }}@endif
        </div>

        <div style="font-size:15px;line-height:1.7;color:#26374b">{!! $contentHtml !!}</div>
      </td></tr>

      <tr><td style="background:#f5f7fc;padding:16px 24px;font-size:12px;line-height:1.6;color:#5f6b85">
        You are receiving this because you submitted this blog on <a href="{{ url('/') }}" style="color:#2b3f9e">investmentpoint.in</a>. Reply to this email if you have any questions.
        <br>For educational purposes only. Not a recommendation to buy, sell or hold any security. Investments in the securities market are subject to market risks.
      </td></tr>
    </table>
  </td></tr>
</table>
</body>
</html>
