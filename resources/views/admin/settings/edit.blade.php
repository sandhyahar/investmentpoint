@extends('admin.layouts.app')

@section('page-title', 'Site Settings')

@section('content')
{{-- Monthly subscription price --}}
<div class="form-card mb-4" style="max-width:760px" id="subscription-price">
  <h2 style="font-size:16px;font-weight:800;color:#10253f;margin:0 0 4px">Subscription Prices</h2>
  <p class="text-muted" style="font-size:13px">The price of each plan on the Subscription page. Shown there and charged at payment (Razorpay). Whole rupees only.</p>

  <form action="{{ route('admin.settings.subscription-price') }}" method="POST">
    @csrf
    <div class="row g-3">
      @foreach($plans as $key => $plan)
      <div class="col-md-6">
        <label class="form-label" for="price_{{ $key }}">{{ $plan['name'] }} *</label>
        <div class="input-group">
          <span class="input-group-text" style="font-weight:700">₹</span>
          <input type="number" class="form-control @error('price_' . $key) is-invalid @enderror" id="price_{{ $key }}" name="price_{{ $key }}" value="{{ old('price_' . $key, $plan['price']) }}" min="1" max="1000000" step="1" required style="font-weight:700">
          <span class="input-group-text">/ {{ $plan['period'] }}</span>
          @error('price_' . $key)<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="form-text">Now: <b>₹{{ number_format($plan['price']) }} per {{ $plan['period'] }}</b> · {{ $plan['deliver'] === 'post' ? 'printed paper by post' : 'on WhatsApp + member login' }}</div>
      </div>
      @endforeach
    </div>
    <button type="submit" class="btn btn-primary mt-3" style="border-radius:8px;font-weight:600"><i class="bi bi-check-lg me-1"></i> Save Prices</button>
  </form>

  <div class="alert alert-light border mt-3 mb-0" style="font-size:12px">
    <b>Good to know:</b> the new price applies to new subscriptions from now on. Members who already paid keep the price and dates they paid for.
    Someone who had already reached the payment page before you changed it still pays the price shown to them.
  </div>
</div>

<div class="form-card" style="max-width:760px">
  <h2 style="font-size:16px;font-weight:800;color:#10253f;margin:0 0 4px">Website Logo</h2>
  <p class="text-muted" style="font-size:13px">Shown in the admin sidebar and admin login page.</p>

  <div class="mb-4">
    <label class="d-block mb-2">Current logo {{ $hasCustomLogo ? '' : '(default)' }}</label>
    <div style="display:flex;flex-wrap:wrap;gap:14px;align-items:center">
      <div style="background:#fff;border:1px solid #e2e9f0;border-radius:10px;padding:14px 18px">
        <img id="logoPreview" src="{{ $logoUrl }}" alt="Current logo" style="height:56px;width:auto;max-width:100%;display:block">
      </div>
      <div style="background:linear-gradient(135deg,#141f55,#2b3f9e);border-radius:10px;padding:14px 18px">
        <img id="logoPreviewDark" src="{{ $logoUrl }}" alt="" style="height:40px;width:auto;display:block;filter:grayscale(1) invert(1) brightness(1.8) contrast(1.2);mix-blend-mode:screen">
        <small style="display:block;color:#c8d0f0;font-size:10px;margin-top:6px;text-align:center">Admin sidebar (auto white)</small>
      </div>
    </div>
  </div>

  <form action="{{ route('admin.settings.update') }}" method="POST" enctype="multipart/form-data">
    @csrf
    <div class="mb-3">
      <label class="form-label" for="logo">Upload new logo *</label>
      <input type="file" class="form-control @error('logo') is-invalid @enderror" id="logo" name="logo" accept="image/png,image/jpeg,image/webp" required>
      <small class="text-muted">PNG, JPG or WEBP · max 2MB · a wide logo on a white background works best (about 900 × 185 px). Crop extra white space before uploading. A white version for dark areas is made automatically.</small>
      @error('logo')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <button type="submit" class="btn btn-primary" style="border-radius:8px;font-weight:600"><i class="bi bi-upload me-1"></i> Save Logo</button>
  </form>

  @if($hasCustomLogo)
  <form action="{{ route('admin.settings.logo.reset') }}" method="POST" class="mt-3" onsubmit="return confirm('Remove the uploaded logo and go back to the default logo?')">
    @csrf @method('DELETE')
    <button type="submit" class="btn btn-sm btn-outline-secondary" style="border-radius:8px"><i class="bi bi-arrow-counterclockwise me-1"></i> Reset to default logo</button>
  </form>
  @endif
</div>

<div class="form-card mt-4" style="max-width:760px" id="gemini-ai">
  <h2 style="font-size:16px;font-weight:800;color:#10253f;margin:0 0 4px"><i class="bi bi-google me-1" style="color:#4285f4"></i> Gemini AI (articles, titles and translations)</h2>
  <p class="text-muted" style="font-size:13px">The main AI of the website. <b>Gemini 3.1 Flash-Lite</b> writes the articles, SEO titles and translations (about ₹0.25 an article; free within Google's free-tier limits). If Gemini fails, Groq writes the text. The "Generate with AI" pictures are <b>not</b> made by Gemini (it needs billing for pictures) but by free AI services: Cloudflare (about 100 a day), then Pollinations, then Hugging Face.</p>

  @if($geminiConnected)
    <div class="alert alert-success py-2" style="font-size:13px;border-radius:10px"><i class="bi bi-check-circle-fill me-1"></i> Connected. Key ending in <b>…{{ $geminiKeyEnd }}</b>, model <code>{{ $geminiModel }}</code>.</div>
  @endif

  <form action="{{ route('admin.settings.gemini') }}" method="POST" autocomplete="off">
    @csrf
    <div class="mb-3">
      <label class="form-label" for="gemini_api_key">{{ $geminiConnected ? 'Replace the Gemini API key (leave empty to keep it)' : 'Gemini API key' }}</label>
      <input type="password" class="form-control @error('gemini_api_key') is-invalid @enderror" id="gemini_api_key" name="gemini_api_key" placeholder="AQ.... or AIza..." autocomplete="new-password">
      @error('gemini_api_key')<div class="invalid-feedback">{{ $message }}</div>@enderror
      <div class="form-text">
        1. Open <a href="https://aistudio.google.com/apikey" target="_blank" rel="noopener">aistudio.google.com/apikey</a> and sign in with your Google account.<br>
        2. Click <b>Create API key</b> and copy it (it starts with <code>AQ.</code> or <code>AIza</code>).<br>
        3. Paste it here and click Save. The key is tested first, and saved only if it works.
      </div>
    </div>
    <div class="mb-3" style="max-width:260px">
      <label class="form-label" for="ai_image_daily_limit">AI pictures per day (limit)</label>
      <input type="number" min="0" max="1000" class="form-control @error('ai_image_daily_limit') is-invalid @enderror" id="ai_image_daily_limit" name="ai_image_daily_limit" value="{{ old('ai_image_daily_limit', $aiImageDailyLimit) }}">
      @error('ai_image_daily_limit')<div class="invalid-feedback">{{ $message }}</div>@enderror
      <div class="form-text">"Generate with AI" stops for the day after this many pictures, so the free daily allowance lasts (auto-published news uses it too).</div>
    </div>
    <button type="submit" class="btn btn-primary" style="border-radius:8px;font-weight:600"><i class="bi bi-check-lg me-1"></i> Save</button>
    @if($geminiConnected)
      <button type="submit" name="remove" value="1" class="btn btn-outline-danger ms-2" style="border-radius:8px;font-weight:600" formnovalidate onclick="return confirm('Remove the Gemini key?')"><i class="bi bi-trash me-1"></i> Remove key</button>
    @endif
  </form>
</div>

<div class="form-card mt-4" style="max-width:760px" id="free-ai">
  <h2 style="font-size:16px;font-weight:800;color:#10253f;margin:0 0 4px"><i class="bi bi-stars me-1" style="color:#2b3f9e"></i> Free AI backup for articles (Groq)</h2>
  <p class="text-muted" style="font-size:13px">A free backup. When Gemini is not set up, or its limit is reached, the website writes articles, titles and translations with Groq. The free Groq plan needs no payment.</p>

  @if($groqConnected)
    <div class="alert alert-success py-2" style="font-size:13px;border-radius:10px"><i class="bi bi-check-circle-fill me-1"></i> Connected. Key ending in <b>…{{ $groqKeyEnd }}</b>, model <code>{{ $groqModel }}</code>.</div>
  @endif

  <form action="{{ route('admin.settings.ai') }}" method="POST" autocomplete="off">
    @csrf
    <div class="mb-3">
      <label class="form-label" for="groq_api_key">{{ $groqConnected ? 'Replace the Groq API key' : 'Groq API key' }}</label>
      <input type="password" class="form-control @error('groq_api_key') is-invalid @enderror" id="groq_api_key" name="groq_api_key" placeholder="gsk_..." autocomplete="new-password">
      @error('groq_api_key')<div class="invalid-feedback">{{ $message }}</div>@enderror
      <div class="form-text">
        1. Open <a href="https://console.groq.com" target="_blank" rel="noopener">console.groq.com</a> and sign up free (you can use your Google account).<br>
        2. Open <b>API Keys</b> → <b>Create API Key</b>, give it any name, and copy the key (it starts with <code>gsk_</code> and is shown only once).<br>
        3. Paste it here and click Save. The key is tested first, and saved only if it works.
      </div>
    </div>
    <button type="submit" class="btn btn-primary" style="border-radius:8px;font-weight:600"><i class="bi bi-check-lg me-1"></i> Save and test the key</button>
    @if($groqConnected)
      <button type="submit" name="remove" value="1" class="btn btn-outline-danger ms-2" style="border-radius:8px;font-weight:600" formnovalidate onclick="return confirm('Remove the Groq key?')"><i class="bi bi-trash me-1"></i> Remove key</button>
    @endif
  </form>
</div>
<div class="form-card mt-4" style="max-width:760px" id="google">
  <h2 style="font-size:16px;font-weight:800;color:#10253f;margin:0 0 4px"><i class="bi bi-google me-1" style="color:#4285f4"></i> Google (SEO)</h2>
  <p class="text-muted" style="font-size:13px">Connect the website to Google Search Console (how the site appears in Google search) and Google Analytics (visitors). Both are free. Paste the codes here and save; nothing else is needed.</p>

  <form action="{{ route('admin.settings.google') }}" method="POST">
    @csrf
    <div class="mb-3">
      <label class="form-label" for="google_site_verification">Search Console verification code</label>
      <input type="text" class="form-control @error('google_site_verification') is-invalid @enderror" id="google_site_verification" name="google_site_verification" value="{{ old('google_site_verification', $googleVerification) }}" placeholder='&lt;meta name="google-site-verification" content="..." /&gt;' autocomplete="off">
      @error('google_site_verification')<div class="invalid-feedback">{{ $message }}</div>@enderror
      <div class="form-text">
        1. Open <a href="https://search.google.com/search-console" target="_blank" rel="noopener">search.google.com/search-console</a> → Add property → <b>URL prefix</b> → <code>{{ config('app.public_url') }}</code><br>
        2. Choose <b>HTML tag</b>, copy the tag and paste it here (the whole tag is fine), then Save.<br>
        3. Go back to Search Console and click <b>Verify</b>, then add the sitemap <code>{{ config('app.public_url') }}/sitemap.xml</code> under Sitemaps.
        @if(!$googleVerification && $googleFromEnv['verification'])<br><span class="text-success">A code is already set in the server's .env file.</span>@endif
      </div>
    </div>
    <div class="mb-3">
      <label class="form-label" for="google_analytics_id">Google Analytics Measurement ID</label>
      <input type="text" class="form-control @error('google_analytics_id') is-invalid @enderror" id="google_analytics_id" name="google_analytics_id" value="{{ old('google_analytics_id', $googleAnalyticsId) }}" placeholder="G-XXXXXXXXXX" autocomplete="off" style="max-width:260px;text-transform:uppercase">
      @error('google_analytics_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
      <div class="form-text">
        Open <a href="https://analytics.google.com" target="_blank" rel="noopener">analytics.google.com</a> → Admin → Create → Property → add a <b>Web</b> stream for <code>{{ config('app.public_url') }}</code> → copy the Measurement ID (starts with <code>G-</code>).
        @if(!$googleAnalyticsId && $googleFromEnv['analytics'])<br><span class="text-success">An ID is already set in the server's .env file.</span>@endif
      </div>
    </div>
    <button type="submit" class="btn btn-primary" style="border-radius:8px;font-weight:600"><i class="bi bi-check-lg me-1"></i> Save Google Settings</button>
  </form>
</div>

{{-- Website Email: the Gmail App Password (SettingController::updateMail) --}}
<div class="form-card mt-4" style="max-width:760px" id="website-email">
  <h2 style="font-size:16px;font-weight:800;color:#10253f;margin:0 0 4px"><i class="bi bi-envelope-fill me-1" style="color:#ea4335"></i> Website Email (Gmail)</h2>
  <p class="text-muted" style="font-size:13px">The website sends its emails (receipts, notices, articles) from <b>{{ $mail['account'] }}</b>. Gmail needs an <b>App Password</b> for this, not your normal Gmail password. If emails stop working, make a new App Password and paste it here.</p>

  @if($mail['saved'])
    <div class="alert alert-success py-2" style="font-size:13px;border-radius:10px"><i class="bi bi-check-circle-fill me-1"></i> An App Password is saved here{{ $mail['savedAt'] ? ' (' . $mail['savedAt']->format('d M Y, h:i A') . ')' : '' }} and was accepted by Gmail.</div>
  @endif

  <form action="{{ route('admin.settings.mail') }}" method="POST" autocomplete="off">
    @csrf
    <div class="mb-3">
      <label class="form-label" for="mail_password">{{ $mail['saved'] ? 'New Gmail App Password' : 'Gmail App Password' }}</label>
      <div class="input-group" style="max-width:360px">
        <input type="password" class="form-control @error('mail_password') is-invalid @enderror" id="mail_password" name="mail_password" placeholder="abcd efgh ijkl mnop" autocomplete="new-password">
        <button type="button" class="btn btn-outline-secondary" data-toggle-password="mail_password" aria-label="Show password" title="Show password"><i class="bi bi-eye"></i></button>
        @error('mail_password')<div class="invalid-feedback">{{ $message }}</div>@enderror
      </div>
      <div class="form-text">
        1. Open <a href="https://myaccount.google.com/apppasswords" target="_blank" rel="noopener">myaccount.google.com/apppasswords</a> signed in as <b>{{ $mail['account'] }}</b> (2-Step Verification must be on).<br>
        2. Type a name (e.g. <i>Investment Point website</i>), click <b>Create</b> and copy the 16 letters. The spaces don't matter.<br>
        3. Paste it here and click Save. Gmail checks it first; it is saved only if it works, then a test email is sent to {{ config('mail.from.address') }}.
      </div>
    </div>
    <button type="submit" class="btn btn-primary" style="border-radius:8px;font-weight:600"><i class="bi bi-check-lg me-1"></i> Save and test</button>
    @if($mail['saved'])
      <button type="submit" name="remove" value="1" class="btn btn-outline-danger ms-2" style="border-radius:8px;font-weight:600" formnovalidate onclick="return confirm('Remove the saved email password? The website goes back to the one in the server settings.')"><i class="bi bi-trash me-1"></i> Remove</button>
    @endif
  </form>
</div>

{{-- Blogger auto-post (App\Services\BloggerPublisher) --}}
<div class="form-card mt-4" style="max-width:760px" id="blogger">
  <h2 style="font-size:16px;font-weight:800;color:#10253f;margin:0 0 4px"><i class="bi bi-rss-fill me-1" style="color:#f57d00"></i> Blogger (auto-post E-Paper &amp; Media Articles)</h2>
  <p class="text-muted" style="font-size:13px">Every E-Paper edition (Gujarati, Hindi, English, Weekly) and Media Article you upload here is also posted to its Blogger blog automatically, within a few minutes, with the same title and the page pictures. Only uploads made after connecting are posted. Upload in one place only (here), or the edition shows twice.</p>

  @if($blogger['connected'])
    <div class="alert alert-success py-2" style="font-size:13px;border-radius:10px"><i class="bi bi-check-circle-fill me-1"></i> Connected{{ $blogger['connectedAt'] ? ' since ' . $blogger['connectedAt']->format('d M Y, h:i A') : '' }}. New uploads go to:
      <ul class="mb-0 mt-1 ps-3">
        @foreach($blogger['sections'] as $key => $section)
          <li>{{ $section['label'] }} →
            @if(isset($blogger['blogs'][$key]))<a href="{{ $blogger['blogs'][$key]['url'] }}" target="_blank" rel="noopener">{{ $blogger['blogs'][$key]['name'] }}</a>
            @else<span class="text-danger">not found for this account ({{ $section['blog'] }})</span>@endif
          </li>
        @endforeach
      </ul>
    </div>

    {{-- Daily E-Paper by email (App\Services\EpaperByEmail): Blogger keeps the pictures --}}
    <form action="{{ route('admin.settings.blogger.emails') }}" method="POST" autocomplete="off" class="mb-3" id="blogger-emails">
      @csrf
      <h3 style="font-size:14px;font-weight:800;color:#10253f;margin:0 0 4px">Daily E-Paper: "Post using email" addresses</h3>
      <p class="form-text mt-0">Daily Gujarati / Hindi / English PDFs are emailed to their blog, so Blogger keeps the pictures like a post you make by hand (nothing on the website server). For each blog: open it in Blogger → <b>Settings</b> → <b>Email</b> → <b>Post using email</b>, choose <b>Publish email immediately</b>, type a secret word, and copy the whole address (…@blogger.com) here. Keep it secret: anyone with it can post to the blog.</p>
      @foreach($blogger['emails'] as $key => $email)
        <div class="mb-2">
          <label class="form-label mb-1" for="blogger_email_{{ $key }}">{{ \App\Models\DigitalNewspaper::EDITIONS[$key] }} blog {!! $email ? '<span class="text-success">(saved: ' . e($email) . '; leave empty to keep it)</span>' : '<span class="text-danger">(not saved)</span>' !!}</label>
          <input type="email" class="form-control @error('blogger_email_' . $key) is-invalid @enderror" id="blogger_email_{{ $key }}" name="blogger_email_{{ $key }}" placeholder="yourname.secretword@blogger.com" autocomplete="off">
          @error('blogger_email_' . $key)<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
      @endforeach
      <button type="submit" class="btn btn-primary btn-sm mt-1" style="border-radius:8px;font-weight:600"><i class="bi bi-check-lg me-1"></i> Save addresses</button>
    </form>
  @endif
  @if($blogger['error'])
    <div class="alert alert-warning py-2" style="font-size:13px;border-radius:10px"><i class="bi bi-exclamation-triangle-fill me-1"></i> {{ $blogger['error'] }}</div>
  @endif

  <form action="{{ route('admin.settings.blogger') }}" method="POST" autocomplete="off">
    @csrf
    <div class="mb-3">
      <label class="form-label" for="blogger_client_id">Google Client ID</label>
      <input type="text" class="form-control @error('blogger_client_id') is-invalid @enderror" id="blogger_client_id" name="blogger_client_id" value="{{ old('blogger_client_id', $blogger['clientId']) }}" placeholder="1234567890-abc123.apps.googleusercontent.com">
      @error('blogger_client_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="mb-3">
      <label class="form-label" for="blogger_client_secret">{{ $blogger['hasSecret'] ? 'Google Client Secret (saved; leave empty to keep it)' : 'Google Client Secret' }}</label>
      <input type="password" class="form-control @error('blogger_client_secret') is-invalid @enderror" id="blogger_client_secret" name="blogger_client_secret" placeholder="GOCSPX-..." autocomplete="new-password">
      @error('blogger_client_secret')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="form-text mb-3">
      One-time setup, signed in to Google as <b>{{ config('blogger.account') }}</b>:<br>
      1. Open <a href="https://console.cloud.google.com/apis/library/blogger.googleapis.com" target="_blank" rel="noopener">Blogger API v3</a> in Google Cloud (create a project if asked) and click <b>Enable</b>.<br>
      2. Open <a href="https://console.cloud.google.com/auth/overview" target="_blank" rel="noopener">Google Auth Platform</a> → Get started: app name <i>Investment Point</i>, your email, audience <b>External</b>. Then <b>Audience</b> → <b>Publish app</b> (in "Testing" the access stops after 7 days).<br>
      3. <b>Clients</b> → Create client → <b>Web application</b> → under <i>Authorised redirect URIs</i> add exactly:<br>
      <code style="user-select:all">{{ $blogger['redirect'] }}</code><br>
      4. Copy the <b>Client ID</b> and <b>Client Secret</b> here, Save, then click <b>Connect Blogger</b>. On Google's page choose {{ config('blogger.account') }}; if it says "Google hasn't verified this app", click <i>Advanced</i> → <i>Go to Investment Point</i> (it is your own app) and Allow.
    </div>
    <button type="submit" class="btn btn-primary" style="border-radius:8px;font-weight:600"><i class="bi bi-check-lg me-1"></i> Save</button>
    @if($blogger['clientId'] && $blogger['hasSecret'])
      <a href="{{ route('admin.settings.blogger.connect') }}" class="btn btn-success ms-2" style="border-radius:8px;font-weight:600"><i class="bi bi-google me-1"></i> {{ $blogger['connected'] ? 'Connect again' : 'Connect Blogger' }}</a>
    @endif
  </form>
  @if($blogger['connected'])
  <form action="{{ route('admin.settings.blogger.disconnect') }}" method="POST" class="mt-3" onsubmit="return confirm('Stop posting to Blogger?')">
    @csrf @method('DELETE')
    <button type="submit" class="btn btn-sm btn-outline-danger" style="border-radius:8px"><i class="bi bi-x-circle me-1"></i> Disconnect</button>
  </form>
  @endif
</div>


<div class="form-card mt-4" style="max-width:760px">
  <h2 style="font-size:16px;font-weight:800;color:#10253f;margin:0 0 4px">Social Media Links</h2>
  <p class="text-muted" style="font-size:13px">Shown as icons in the website's top bar. Leave a box empty to hide that icon.</p>

  <form action="{{ route('admin.settings.social') }}" method="POST">
    @csrf
    <div class="row g-3">
      @foreach($social as $key => $item)
        <div class="col-md-6">
          <label class="form-label" for="social_{{ $key }}"><i class="bi {{ $item['icon'] }} me-1" style="color:{{ $item['color'] }}"></i> {{ $item['label'] }}</label>
          <input type="{{ $key === 'email' ? 'email' : 'url' }}" class="form-control @error('social_' . $key) is-invalid @enderror" id="social_{{ $key }}" name="social_{{ $key }}" value="{{ old('social_' . $key, $item['value']) }}" placeholder="{{ $item['placeholder'] }}">
          @error('social_' . $key)<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
      @endforeach
    </div>
    <button type="submit" class="btn btn-primary mt-3" style="border-radius:8px;font-weight:600"><i class="bi bi-check-lg me-1"></i> Save Links</button>
  </form>
</div>

{{-- Live headlines are disabled: the homepage "Latest News" box now comes from Admin -> Latest News.
     Remove this Blade comment (and restore the live block in HomeController::latestNews) to bring the choice back.
<div class="form-card mt-4" style="max-width:760px">
  <h2 style="font-size:16px;font-weight:800;color:#10253f;margin:0 0 4px">Homepage Latest News</h2>
  <p class="text-muted" style="font-size:13px">Choose what the "Latest News" box on the homepage shows. Live headlines come from Economic Times and LiveMint market news, refresh every {{ config('news.cache_minutes', 5) }} minutes, and open on the publisher's website.</p>

  <form action="{{ route('admin.settings.news-source') }}" method="POST">
    @csrf
    @foreach([
      'both'  => ['Admin news + live headlines', 'Your news (Market Trend) mixed with the top ' . config('news.limit', 10) . ' live headlines, newest first.'],
      'live'  => ['Live headlines only', 'Only the top ' . config('news.limit', 10) . ' live market headlines.'],
      'admin' => ['Admin news only', 'Only the news you add in Market Trend.'],
    ] as $value => [$label, $help])
      <label class="d-flex gap-2 align-items-start p-2 mb-2 border rounded" style="cursor:pointer">
        <input type="radio" class="form-check-input mt-1" name="news_source" value="{{ $value }}" {{ $newsSource === $value ? 'checked' : '' }}>
        <span><b style="font-size:14px">{{ $label }}</b><br><small class="text-muted">{{ $help }}</small></span>
      </label>
    @endforeach
    @error('news_source')<div class="text-danger small mb-2">{{ $message }}</div>@enderror
    <button type="submit" class="btn btn-primary mt-2" style="border-radius:8px;font-weight:600"><i class="bi bi-check-lg me-1"></i> Save</button>
  </form>
</div>
--}}
@endsection

@section('scripts')
<script>
// Live preview of the chosen file before saving
document.getElementById('logo').addEventListener('change', function () {
  const file = this.files && this.files[0];
  if (!file) return;
  const url = URL.createObjectURL(file);
  document.getElementById('logoPreview').src = url;
  document.getElementById('logoPreviewDark').src = url;
});
// Eye buttons: show / hide what is typed in a password box
document.querySelectorAll('[data-toggle-password]').forEach(function (button) {
  button.addEventListener('click', function () {
    const input = document.getElementById(button.dataset.togglePassword);
    const show = input.type === 'password';
    input.type = show ? 'text' : 'password';
    button.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
    button.title = show ? 'Hide password' : 'Show password';
    button.innerHTML = show ? '<i class="bi bi-eye-slash"></i>' : '<i class="bi bi-eye"></i>';
  });
});
</script>
@endsection
