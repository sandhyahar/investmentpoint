{{-- New insurance enquiry (App\Mail\InsuranceEnquiryMail). Inline styles only: email apps ignore <style> blocks. --}}
@php $e = $enquiry; @endphp
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>New insurance enquiry</title>
</head>
<body style="margin:0;padding:0;background:#eef2f8;font-family:Arial,Helvetica,sans-serif;color:#1f3448">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#eef2f8;padding:24px 12px">
  <tr><td align="center">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;background:#ffffff;border-radius:12px;overflow:hidden">
      <tr><td style="background:#141f55;padding:18px 24px">
        <a href="{{ url('/') }}" style="color:#ffffff;font-size:20px;font-weight:bold;text-decoration:none">Investment Point</a>
        <div style="color:#f4bd32;font-size:11px;letter-spacing:1px;margin-top:2px">NEW INSURANCE ENQUIRY</div>
      </td></tr>

      <tr><td style="padding:24px">
        <div style="display:inline-block;background:{{ $e->kind === 'adviser' ? '#fff3d6' : '#e8f0ff' }};color:{{ $e->kind === 'adviser' ? '#8a5a00' : '#2b3f9e' }};font-size:12px;font-weight:bold;padding:5px 12px;border-radius:20px">{{ $what }} &middot; {{ $insurance }}</div>
        <h1 style="font-size:22px;line-height:1.35;color:#141f55;margin:14px 0 4px">{{ $e->name }}</h1>
        <div style="font-size:13px;color:#5f6b85">Sent {{ $e->created_at->timezone('Asia/Kolkata')->format('d M Y, h:i A') }} from the Insurance page</div>

        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-top:18px;font-size:15px;line-height:1.6;border-collapse:collapse">
          <tr><td style="padding:8px 0;color:#5f6b85;width:120px;border-top:1px solid #e3e8f2">Mobile</td>
            <td style="padding:8px 0;border-top:1px solid #e3e8f2"><a href="tel:+91{{ $e->contact }}" style="color:#141f55;font-weight:bold;text-decoration:none">{{ $e->contact }}</a>
              &nbsp;&middot;&nbsp;<a href="https://wa.me/91{{ $e->contact }}" style="color:#128c4b;text-decoration:none">WhatsApp</a></td></tr>
          @if($e->age)
          <tr><td style="padding:8px 0;color:#5f6b85;border-top:1px solid #e3e8f2">Age</td><td style="padding:8px 0;border-top:1px solid #e3e8f2">{{ $e->age }} years</td></tr>
          @endif
          @if($e->products)
          <tr><td style="padding:8px 0;color:#5f6b85;border-top:1px solid #e3e8f2;vertical-align:top">Plans</td><td style="padding:8px 0;border-top:1px solid #e3e8f2"><b>{{ implode(', ', $e->products) }}</b></td></tr>
          @endif
          <tr><td style="padding:8px 0;color:#5f6b85;border-top:1px solid #e3e8f2;vertical-align:top">Address</td>
            <td style="padding:8px 0;border-top:1px solid #e3e8f2">{!! nl2br(e($e->address)) !!}@if($e->city)<br><b>{{ $e->city }}</b>{{ $e->state ? ', ' . $e->state : '' }}{{ $e->pincode ? ' - ' . $e->pincode : '' }}@endif</td></tr>
        </table>

        <p style="margin:20px 0 0"><a href="{{ $listUrl }}" style="display:inline-block;background:#2b3f9e;color:#ffffff;font-weight:bold;font-size:14px;text-decoration:none;padding:11px 18px;border-radius:8px">Open Insurance Enquiries &rarr;</a></p>
      </td></tr>

      <tr><td style="background:#f5f7fc;padding:14px 24px;font-size:12px;line-height:1.6;color:#5f6b85">
        Sent by <a href="{{ url('/') }}" style="color:#2b3f9e">investmentpoint.in</a> because someone sent the form on the Insurance page. The address it goes to is set in Admin &rarr; Services &rarr; Insurance Products.
      </td></tr>
    </table>
  </td></tr>
</table>
</body>
</html>
