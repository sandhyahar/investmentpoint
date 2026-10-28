{{-- Daily e-paper: where an upload goes (App\Services\EpaperByEmail); $blocked = what is missing, or null --}}
@if($edition !== 'weekly')
  @if($blocked)
    <div class="alert alert-warning py-2" style="font-size:13px;border-radius:10px;max-width:820px"><i class="bi bi-exclamation-triangle-fill me-1"></i> {{ $blocked }} Until then no PDF can be uploaded here.</div>
  @else
    <div class="alert alert-info py-2" style="font-size:13px;border-radius:10px;max-width:820px"><i class="bi bi-envelope-paper me-1"></i> Each PDF goes <b>straight to the {{ \App\Models\DigitalNewspaper::EDITIONS[$edition] }} Blogger blog</b> (sent by email, so Blogger keeps the pictures like a post made by hand), dated by its edition date: newest date first on the blog. Blogger blocks emails that come too quickly, so the website sends the editions <b>one by one, about a minute apart, by itself</b>, and <b>at most {{ \App\Services\EpaperByEmail::DAILY_LIMIT }} a day</b> (all papers together; more PDFs wait and go on the next days, oldest first): after uploading you can close this page. Each edition shows on the website from Blogger once its post is made; its pages are then removed from the website server.
      <div class="mt-1"><b>If Google blocks the email</b> (the edition is not on the blog a few minutes later): make the post in Blogger yourself, then click <b>"Get all editions from Blogger"</b> on the {{ \App\Models\DigitalNewspaper::EDITIONS[$edition] }} E-Paper list.</div></div>
  @endif
@endif
