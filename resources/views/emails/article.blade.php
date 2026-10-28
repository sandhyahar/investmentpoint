{{-- Article email (App\Mail\ArticleMail). Inline styles only: email apps ignore <style> blocks. --}}
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>{{ $article['title'] }}</title>
</head>
<body style="margin:0;padding:0;background:#eef2f8;font-family:Arial,Helvetica,sans-serif;color:#1f3448">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#eef2f8;padding:24px 12px">
  <tr><td align="center">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:640px;background:#ffffff;border-radius:12px;overflow:hidden">
      <tr><td style="background:#141f55;padding:18px 24px">
        <a href="{{ $article['source_url'] }}" style="color:#ffffff;font-size:20px;font-weight:bold;text-decoration:none">Investment Point</a>
        <div style="color:#f4bd32;font-size:11px;letter-spacing:1px;margin-top:2px">NEWS &bull; MARKETS &bull; INSIGHTS</div>
      </td></tr>

      @if(!empty($article['image_url']))
      {{-- Our own uploaded picture travels inside the email (shows even when the site isn't reachable or images are blocked) --}}
      <tr><td><img src="{{ !empty($imagePath) && isset($message) ? $message->embed($imagePath) : $article['image_url'] }}" alt="{{ $article['title'] }}" width="640" style="display:block;width:100%;max-width:640px;height:auto;border:0"></td></tr>
      @endif

      <tr><td style="padding:24px">
        <div style="display:inline-block;background:#eaeefb;color:#2b3f9e;font-size:11px;font-weight:bold;letter-spacing:.5px;text-transform:uppercase;padding:4px 10px;border-radius:20px">{{ $article['category'] }}</div>
        <h1 style="font-size:24px;line-height:1.35;color:#141f55;margin:12px 0 10px">{{ $article['title'] }}</h1>

        @if(!empty($article['cmp']) || !empty($article['support']) || !empty($article['resistance']))
        <table role="presentation" cellpadding="0" cellspacing="0" style="margin:0 0 16px">
          <tr>
            @if(!empty($article['cmp']))<td style="background:#eef1f8;color:#141f55;font-size:14px;padding:8px 14px;border-radius:8px">CMP <b>₹{{ $article['cmp'] }}</b></td><td width="8"></td>@endif
            @if(!empty($article['support']))<td style="background:#e8f7ef;color:#0a7a4a;font-size:14px;padding:8px 14px;border-radius:8px">Support <b>₹{{ $article['support'] }}</b></td><td width="8"></td>@endif
            @if(!empty($article['resistance']))<td style="background:#fdecec;color:#c0392b;font-size:14px;padding:8px 14px;border-radius:8px">Resistance <b>₹{{ $article['resistance'] }}</b></td>@endif
          </tr>
        </table>
        @endif

        @if(!empty($article['summary']))
        <p style="font-size:16px;line-height:1.6;color:#43546a;font-weight:bold;margin:0 0 16px">{{ $article['summary'] }}</p>
        @endif

        {{-- Already cleaned by App\Support\Html::clean (the same HTML the website shows) --}}
        <div style="font-size:15px;line-height:1.7;color:#26374b">{!! $article['content_html'] !!}</div>

        @if(!empty($files))
          <p style="margin:20px 0 0;padding:10px 14px;background:#f5f7fc;border-radius:8px;font-size:13px;line-height:1.6;color:#43546a">
            <b>&#128206; Attached {{ count($files) === 1 ? 'file' : 'files' }}:</b> {{ collect($files)->pluck('name')->implode(', ') }}
          </p>
        @endif

        <p style="margin:24px 0 0"><a href="{{ $article['article_url'] }}" style="display:inline-block;background:#2b3f9e;color:#ffffff;font-weight:bold;font-size:14px;text-decoration:none;padding:12px 20px;border-radius:8px">Read on Investment Point &rarr;</a></p>
      </td></tr>

      <tr><td style="background:#f5f7fc;padding:16px 24px;font-size:12px;line-height:1.6;color:#5f6b85">
        @if($forChannel)
          Submitted for publication by Investment Point. Please credit <a href="{{ $article['source_url'] }}" style="color:#2b3f9e">investmentpoint.in</a> and link to the original article.
        @else
          You are receiving this because you are on the Investment Point mailing list. Reply with "unsubscribe" to stop receiving these emails.
        @endif
        <br>For educational purposes only. Not a recommendation to buy, sell or hold any security. Investments in the securities market are subject to market risks.
      </td></tr>
    </table>
  </td></tr>
</table>
</body>
</html>
