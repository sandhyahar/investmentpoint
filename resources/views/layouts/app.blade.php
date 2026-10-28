<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<meta name="csrf-token" content="{{ csrf_token() }}">
@php
    // SEO: pages can set @section('meta_description') and @section('og_image'); otherwise site defaults are used
    // Inline @section values arrive HTML-escaped: decode once here, escape once when printed below
    $seoText = fn ($s) => trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags((string) $s), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
    $seoTitle = $seoText($__env->yieldContent('title', 'Investment Point | Stock Market News, Trends & Technical Analysis'));
    $seoDescription = \Illuminate\Support\Str::limit($seoText($__env->yieldContent('meta_description', config('seo.default_description'))), 160);
    $seoImage = html_entity_decode($__env->yieldContent('og_image')) ?: asset(config('seo.default_image'));
@endphp
<meta name="description" content="{{ $seoDescription }}">
@hasSection('meta_keywords')<meta name="keywords" content="{{ $seoText($__env->yieldContent('meta_keywords')) }}">@endif
<title>{{ $seoTitle }}</title>
@include('partials.favicon')
<link rel="canonical" href="{{ url()->current() }}">
<meta name="robots" content="@yield('meta_robots', 'index, follow, max-image-preview:large')">
<meta property="og:site_name" content="{{ config('seo.site_name') }}">
<meta property="og:type" content="{{ $__env->hasSection('og_image') ? 'article' : 'website' }}">
<meta property="og:title" content="{{ $seoTitle }}">
<meta property="og:description" content="{{ $seoDescription }}">
<meta property="og:url" content="{{ url()->current() }}">
<meta property="og:image" content="{{ $seoImage }}">
<meta property="og:locale" content="en_IN">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="{{ $seoTitle }}">
<meta name="twitter:description" content="{{ $seoDescription }}">
<meta name="twitter:image" content="{{ $seoImage }}">
@if(\App\Models\Setting::googleVerification())<meta name="google-site-verification" content="{{ \App\Models\Setting::googleVerification() }}">@endif
@php
    // Language chosen with the switcher (Google Translate cookie), e.g. "/en/gu"
    $siteLang = preg_match('#^/en/(hi|gu)$#', (string) request()->cookie('googtrans'), $langMatch) ? $langMatch[1] : 'en';
@endphp
@if($siteLang !== 'en')
{{-- Translated visit: start downloading Google Translate right away, in parallel with the page --}}
<link rel="preconnect" href="https://translate.google.com">
<link rel="preconnect" href="https://translate.googleapis.com">
<link rel="preconnect" href="https://translate-pa.googleapis.com">
<link rel="preconnect" href="https://www.gstatic.com">
<script>
window.__gtLoading = true;
window.googleTranslateInit = function () {
  function start() { new google.translate.TranslateElement({ pageLanguage: 'en', includedLanguages: 'en,hi,gu', autoDisplay: false }, 'google_translate_element'); }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start); else start();
};
</script>
<script async src="https://translate.google.com/translate_a/element.js?cb=googleTranslateInit"></script>
@endif
<script type="application/ld+json">{!! json_encode([
    '@' . 'context' => 'https://schema.org', // written in two parts: Blade reads the whole word as one of its own directives
    '@type' => 'NewsMediaOrganization',
    'name' => config('seo.site_name'),
    'url' => url('/'),
    'logo' => asset(config('seo.default_image')),
    'sameAs' => array_values(array_filter(array_column(\App\Models\Setting::socialLinks(), 'url'), fn ($u) => str_starts_with($u, 'http'))),
    'address' => ['@type' => 'PostalAddress', 'addressLocality' => 'Jamnagar', 'addressRegion' => 'Gujarat', 'addressCountry' => 'IN'],
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) !!}</script>
@php
    $gaId = \App\Models\Setting::googleAnalyticsId();
    $adsId = config('seo.google_ads_id');
@endphp
@if($gaId || $adsId)
{{-- Google Analytics / Google Ads (loads async, doesn't slow the page). Analytics ID: Admin -> Settings -> Google (or .env) --}}
<script async src="https://www.googletagmanager.com/gtag/js?id={{ $gaId ?: $adsId }}"></script>
<script>
window.dataLayer = window.dataLayer || [];
function gtag(){dataLayer.push(arguments);}
gtag('js', new Date());
@if($gaId) gtag('config', @json($gaId)); @endif
@if($adsId) gtag('config', @json($adsId)); @endif
// Subscription interest: "Subscribe" clicks, and calls / WhatsApp / e-mail (a lead = conversion for Google Ads)
document.addEventListener('click', function (e) {
  var a = e.target.closest('a[href]');
  if (!a) return;
  var href = a.getAttribute('href');
  if (a.href.indexOf(@json(route('subscription'))) === 0) {
    gtag('event', 'subscribe_click', { link_text: (a.textContent || '').trim().slice(0, 60) });
  } else if (/^(tel:|mailto:)|wa\.me|whatsapp/i.test(href)) {
    gtag('event', 'generate_lead', { method: /^tel:/i.test(href) ? 'call' : (/^mailto:/i.test(href) ? 'email' : 'whatsapp') });
    @if($adsId && config('seo.google_ads_lead_label'))
    gtag('event', 'conversion', { send_to: @json($adsId . '/' . config('seo.google_ads_lead_label')) });
    @endif
  }
});
</script>
@endif
{{-- Dark mode: the reader's choice (🌙 button in the header) is applied before anything is drawn, so no white flash --}}
<link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
{{-- Icons and the Inter font don't hold up the first paint: they load alongside and swap in --}}
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet" media="print" onload="this.media='all'">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet" media="print" onload="this.media='all'">
<noscript>
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
</noscript>
<style>
:root{
  --navy:#141f55; --navy2:#1b2a6b; --blue:#2b3f9e; --blue2:#eaeefb; --cyan:#4a63d8;
  --gold:#f4bd32; --green:#0a9f63; --red:#e5484d; --text:#141f3d; --muted:#5f6b85;
  --line:#e3e7f2; --bg:#f1f3fa; --white:#fff; --radius:12px;
  --shadow:0 6px 20px rgba(27,42,107,.08); --premium-grad:linear-gradient(135deg,#141f55,#24378f);
  padding-top:env(safe-area-inset-top,0px);
}
*{box-sizing:border-box}
html{scroll-behavior:smooth}
body{margin:0;font-family:'Segoe UI',Arial,Helvetica,sans-serif;color:var(--text);background:var(--bg);line-height:1.5;font-size:15px}
img{max-width:100%;display:block}
a{text-decoration:none;color:inherit}
.container{width:100%;max-width:none;padding-left:20px;padding-right:20px}

/* TOP BAR */
.top{background:var(--premium-grad);color:#d6dcf5;font-size:12px}
.topin{min-height:34px;display:flex;justify-content:flex-end;align-items:center}
.top a{color:#d6dcf5;margin-right:16px;opacity:.9}
.top a:hover{opacity:1;color:#fff}

/* HEADER */
/* body>header: the site's top bar only, not every <header> on a page (an author card is one too; with this
   rule it took z-index 999 and covered the open Media menu) */
body>header{width:100%;background:#fff;border-bottom:3px solid var(--blue);box-shadow:0 4px 18px rgba(27,42,107,.08);position:sticky;top:0;z-index:999;padding-top:env(safe-area-inset-top,0px)}
.head{min-height:68px;display:flex;align-items:center;gap:18px}
.brand{display:flex;align-items:center;flex-shrink:0}
.brand img{display:block;height:96px;width:auto;max-width:560px;object-fit:contain}
nav{display:flex;align-items:center;gap:16px;flex:1;font-size:13px;font-weight:700}
nav a{color:var(--text);white-space:nowrap;padding:6px 0;border-bottom:2px solid transparent}
nav a:hover,nav a.active{color:var(--blue);border-color:var(--blue)}
.nav-dropdown{position:relative}
.nav-dropdown>a{display:flex;align-items:center;gap:4px}
.news-menu,.media-menu{position:absolute;top:calc(100% + 10px);left:-10px;width:200px;background:#fff;border:1px solid var(--line);border-radius:10px;box-shadow:0 12px 28px rgba(16,37,63,.15);padding:6px;opacity:0;visibility:hidden;transform:translateY(6px);transition:.15s ease;z-index:999}
/* Open while the mouse is over it, or after a click (.open, closed again by a click anywhere else); keyboard users
   get it with Tab (:focus-visible), but a mouse click's focus does not keep it open */
.nav-dropdown:hover .news-menu,.nav-dropdown:hover .media-menu,.nav-dropdown:has(:focus-visible) .news-menu,.nav-dropdown:has(:focus-visible) .media-menu{opacity:1;visibility:visible;transform:translateY(0)}
.nav-dropdown.closing .news-menu,.nav-dropdown.closing .media-menu{opacity:0!important;visibility:hidden!important}
.menu{display:flex;align-items:center;gap:18px;flex:1;min-width:0}
.menu-toggle{display:none;margin-left:auto;width:44px;height:44px;border:1px solid var(--line);border-radius:10px;background:#fff;cursor:pointer;flex-direction:column;align-items:center;justify-content:center;gap:5px}
.menu-toggle span{display:block;width:20px;height:2px;border-radius:2px;background:var(--navy);transition:transform .2s ease,opacity .2s ease}
.menu-toggle[aria-expanded="true"] span:nth-child(1){transform:translateY(7px) rotate(45deg)}
.menu-toggle[aria-expanded="true"] span:nth-child(2){opacity:0}
.menu-toggle[aria-expanded="true"] span:nth-child(3){transform:translateY(-7px) rotate(-45deg)}
.news-menu a,.media-menu a{display:block;padding:9px 11px;border-radius:6px;font-size:12px;font-weight:600}
.news-menu a:hover,.media-menu a:hover{background:var(--blue2);color:var(--blue)}
.header-right{display:flex;align-items:center;gap:8px}
.language-select{height:36px;padding:0 10px;border:1px solid var(--line);border-radius:8px;background:#fff;font-size:12px;font-weight:700}
.header-subscription{background:var(--gold);color:#141f55;padding:9px 14px;border-radius:8px;font-size:12px;font-weight:800}
.header-subscription:hover{background:#e5aa18}

/* TICKER */
.ticker{background:linear-gradient(180deg,#fff,#f5f7fd);border-top:1px solid var(--line);border-bottom:1px solid var(--line);box-shadow:0 2px 10px rgba(20,31,85,.04)}
.tickerin{display:grid;grid-template-columns:repeat({{ max(1, (int) config('market.ticker_count', 4)) }},minmax(0,1fr));width:100%}
.quote{padding:12px 16px;border-right:1px solid var(--line);display:flex;justify-content:space-between;align-items:center;gap:10px;color:var(--text);min-height:70px}
.quote:last-child{border-right:0}
/* Name: small blue label, with room before the price */
.quote small{display:inline-block;margin-bottom:6px;padding:2px 8px;border-radius:5px;background:#eef1fb;color:var(--blue);font-size:11px;font-weight:800;letter-spacing:.7px;text-transform:uppercase;line-height:1.4;white-space:nowrap}
.quote strong{display:block;font-size:17px;line-height:1.2;font-weight:800;color:#0d1b2a;letter-spacing:.2px}
.quote span{font-size:13px;font-weight:700;white-space:nowrap;display:inline-flex;align-items:center}
/* The day's change (points) as a green / red pill */
.quote .stock-change,.quote>span.flat{padding:4px 9px;border-radius:20px;font-weight:800}
.quote .stock-change.up{background:#e3f7ee;color:#08794c}
.quote .stock-change.down{background:#fdecec;color:#c42b30}
.quote .stock-change.flat,.quote>span.flat{background:#f1f3f8;color:var(--muted)}
.quote[role=button]{cursor:pointer;transition:background .15s}
.quote[role=button]:hover,.quote[role=button]:focus-visible{background:#eef1fb;outline:0}
/* Ticker popup: today's gain / loss */
.qm{border:0;border-radius:16px;padding:20px 22px;box-shadow:0 20px 50px rgba(20,31,85,.25)}
.qm-head{display:flex;justify-content:space-between;align-items:flex-start;gap:12px}
.qm-head small{color:var(--muted);font-size:11px;font-weight:700;letter-spacing:.4px;text-transform:uppercase}
.qm-head h5{margin:2px 0 0;font-size:20px;font-weight:800;color:var(--navy)}
.qm-price{display:flex;align-items:center;gap:12px;flex-wrap:wrap;margin:14px 0}
#qmPrice{font-size:30px;font-weight:900;color:#0d1b2a;letter-spacing:-.5px}
.qm-badge{font-size:14px;font-weight:800;padding:4px 10px;border-radius:20px}
.qm-result{display:flex;align-items:center;gap:12px;padding:12px 14px;border-radius:12px;margin-bottom:14px}
.qm-result i{font-size:26px}
.qm-result b{display:block;font-size:15px}
.qm-result span{font-size:13px}
.qm-badge.up,.qm-result.up{background:#e3f7ee;color:#08794c}
.qm-badge.down,.qm-result.down{background:#fdecec;color:#c42b30}
.qm-badge.flat,.qm-result.flat{background:#eef1f6;color:#5f6b85}
.qm-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:10px}
.qm-grid div{background:#f6f8fc;border:1px solid var(--line);border-radius:10px;padding:10px 12px;min-width:0}
.qm-grid small{display:block;color:var(--muted);font-size:11px;font-weight:600}
.qm-grid b{font-size:15px;font-weight:800;color:#0d1b2a;overflow-wrap:anywhere}
.qm-grid b.up{color:#08794c}.qm-grid b.down{color:#c42b30}
.qm-bar{height:6px;background:#eef1f6;border-radius:6px;margin:14px 0 10px;overflow:hidden}
.qm-bar div{height:100%;width:0;border-radius:6px;transition:width .4s ease}
.qm-foot{margin:0;font-size:12px;color:var(--muted);display:flex;align-items:center;gap:6px}
.qm-dot{width:8px;height:8px;border-radius:50%;background:#9aa3b5;display:inline-block}
.qm-dot.open{background:#0a9f63;box-shadow:0 0 0 3px rgba(10,159,99,.2)}
@media(max-width:420px){.qm-grid{grid-template-columns:1fr 1fr}.qm-grid div:last-child{grid-column:1/-1}#qmPrice{font-size:26px}}
.up{color:#0a9f63}
.down{color:#e5484d}
.flat{color:#64748b}
.stock-arrow{font-size:12px;margin-right:4px;display:inline-block;vertical-align:baseline}

main{padding:8px 0 0}
.section{margin:14px 0}
.title{display:flex;justify-content:space-between;align-items:end;margin-bottom:12px}
.title h2{margin:0;font-size:20px;font-weight:800}
.title h2:before{content:"";display:inline-block;width:5px;height:20px;background:var(--blue);border-radius:5px;margin-right:8px;vertical-align:-3px}
.title a{font-size:12px;color:var(--blue);font-weight:700}
.btn-custom{border:1px solid var(--line);background:#fff;padding:9px 13px;border-radius:8px;font-weight:700;font-size:12px;display:inline-block}
.btn-custom.primary{background:var(--blue);color:#fff;border-color:var(--blue)}
.btn-custom.gold{background:var(--gold);color:#141f55;border-color:var(--gold)}

/* Article text written in the admin (partials.article-body, legal pages): justified on every page, older articles too;
   a paragraph aligned by hand (centre, left ...) keeps its own alignment */
.np-justify p,.np-justify li{text-align:justify}

/* HERO */
.hero{display:grid;grid-template-columns:1.6fr .9fr;gap:16px;align-items:stretch;height:clamp(320px,36vw,430px)}
.lead{border-radius:var(--radius);overflow:hidden;position:relative;background:#141f55;height:100%;min-height:0}
.lead img{width:100%;height:100%;object-fit:cover;opacity:.7;transition:transform .4s ease}
.lead:hover img{transform:scale(1.03)}
/* Desktop: the banner box has the Market Trend picture's own 16:9 shape, so nothing of it is cut at the top or bottom;
   the Business News list beside it takes the same height (contain:size: its long list never makes the row taller) */
@media(min-width:901px){
  .hero{height:auto}
  .lead{height:auto;aspect-ratio:16/9}
  .hero>.latest{height:auto;contain:size}
}
/* Clickable picture: a link over the whole card (the text + button sit above it) */
.lead-link{position:absolute;inset:0;z-index:1}
.lead .leadtext{pointer-events:none}
.lead .leadtext a{pointer-events:auto}
.lead h1 a{color:inherit;text-decoration:none}
.lead h1 a:hover{text-decoration:underline}
.lead:after{content:"";position:absolute;inset:0;background:linear-gradient(transparent 30%,rgba(20,31,85,.96))}
.leadtext{position:absolute;left:22px;right:22px;bottom:20px;color:#fff;z-index:2}
.tag{display:inline-block;background:var(--blue);color:#fff;border-radius:5px;padding:4px 8px;font-size:10.5px;font-weight:800;text-transform:uppercase;letter-spacing:.4px}
.lead h1{font-size:24px;line-height:1.2;margin:8px 0}
/* Home banner: the section box, the same size as the button under it (.btn-custom) */
.lead-tags{display:flex;flex-wrap:wrap;gap:8px;align-items:center}
.lead-tags .tag{padding:9px 13px;border-radius:8px;font-size:12px;font-weight:700;line-height:1.5;border:1px solid var(--blue);display:inline-flex;align-items:center;gap:6px}
.lead-meta{display:inline-flex;flex-wrap:wrap;align-items:center;gap:4px 14px;margin-left:6px;font-size:13px;font-weight:700;color:#fff;text-shadow:0 1px 3px rgba(0,0,0,.55)}
.lead-meta .bi{color:#ffd36a;margin-right:2px}
.hero-description{font-size:12px;line-height:1.45;color:#d7e4ef;margin:0 0 6px;max-width:760px}
.meta{font-size:11px;color:#8a98a8}
.lead .meta{color:#cfdcea}

.latest,.card{background:#fff;border:1px solid var(--line);border-radius:var(--radius);box-shadow:var(--shadow)}
.latest{padding:12px;display:flex;flex-direction:column;height:100%;max-height:none;min-height:0;box-sizing:border-box}
.latest h3{margin:0 0 6px;font-size:16px;font-weight:800;flex-shrink:0}
.latest-news-scroll{overflow-y:auto;padding-right:5px;scrollbar-width:thin;flex:1;min-height:0}
.news-item{padding:10px 0;border-bottom:1px solid var(--line);display:grid;grid-template-columns:86px 1fr;gap:11px}
.news-item:last-child{border:0;padding-bottom:0}
/* The picture fills the whole box (its width and height): no empty bands above and below */
.thumb{width:86px;height:72px;border-radius:7px;object-fit:cover;background:#eef1f8;display:block}
.news-item{cursor:pointer}
.news-item h4{font-size:14px;margin:0 0 6px;line-height:1.35;font-weight:700}
.news-item h4 a{color:var(--text)}
.news-item:hover h4 a{color:var(--blue)}
.read-full{display:inline-block;margin-top:5px;color:#2b3f9e;font-size:10px;font-weight:800;text-decoration:none}
.read-full:hover{text-decoration:underline}
.news-item .article-date{font-size:11.5px;color:var(--muted);display:block;margin-bottom:5px}
.news-item .article-author{font-weight:700;color:var(--blue)}
.news-item .article-author::after{content:"·";color:var(--muted);font-weight:400;margin:0 6px}
.news-item .excerpt{font-size:11px;line-height:1.45;color:var(--muted);margin:0;display:-webkit-box;-webkit-line-clamp:7;-webkit-box-orient:vertical;overflow:hidden}

@media(max-width:900px){
  .hero{height:auto;grid-template-columns:1fr}
  /* Tablets & phones: the banner picture is shown whole, in its own shape, with the headline under it
     (over the picture it would hide most of a small image, and a fixed-height box cuts the picture) */
  .lead{height:auto;display:block}
  .lead img{width:100%;height:auto;max-height:560px;object-fit:contain;opacity:1;display:block;-webkit-transform:translateZ(0)}
  .lead:after{display:none}
  .lead:hover img{transform:none}
  .leadtext{position:relative;left:auto;right:auto;bottom:auto;padding:14px 18px 18px}
  .latest{height:330px}
}

/* MARKET TRENDS */
.trends{display:grid;grid-template-columns:minmax(0,1.35fr) minmax(0,.8fr) minmax(0,.8fr);gap:14px;align-items:stretch}
.trends>.card{height:auto;min-height:0;align-self:stretch}
.trendmain{padding:0;overflow:hidden;display:flex;flex-direction:column}
.trendvisual{position:relative;height:118px;flex:none;background:linear-gradient(135deg,#141f55,#2b3f9e);overflow:hidden}
.trendvisual img{width:100%;height:100%;object-fit:cover;opacity:.45}
.trendvisual:after{content:"";position:absolute;inset:0;background:linear-gradient(90deg,rgba(7,29,53,.8),rgba(7,29,53,.1))}
.trendvisualtext{position:absolute;z-index:2;left:16px;bottom:12px;color:#fff;font-weight:800;font-size:16px}
.trendcontent{padding:12px 14px}
.trendbig{font-size:20px;font-weight:800}
.progress-bar-custom{height:6px;background:#edf2f6;border-radius:8px;margin:8px 0}
.progress-bar-custom i{display:block;width:72%;height:100%;background:var(--green);border-radius:8px}
.list{padding:15px;height:auto;min-height:0;align-self:stretch;box-sizing:border-box}
.list h3{margin:0 0 6px;font-size:15px;font-weight:800}
.stock{display:flex;justify-content:space-between;padding:7px 0;border-bottom:1px solid var(--line);font-size:12px}
.stock:last-child{border:0}

/* TECHNICAL ANALYSIS */
.analysis{display:grid;grid-template-columns:repeat(4,1fr);gap:14px}
.analysis article{overflow:hidden;display:flex;flex-direction:column}
.analysis img{width:100%;height:auto;max-height:300px;object-fit:contain;background:#e9edf6}
.body{padding:12px;flex:1}
.body h3{font-size:13px;line-height:1.35;margin:5px 0;font-weight:800}
.level{font-size:10px;color:var(--blue);background:var(--blue2);padding:4px 7px;border-radius:5px;margin-right:4px;display:inline-block;font-weight:700}
.analysis .body{display:flex;flex-direction:column;align-items:flex-start}
.analysis h3 a:hover{color:var(--blue)}
.ta-levels{display:flex;flex-wrap:wrap;gap:8px;margin:10px 0 14px}
.ta-levels .level{margin:0;font-size:13px;padding:8px 13px;border-radius:8px;color:#fff}
/* Support = red, Resistance = blue */
.ta-levels .level.support{background:#dc2626}
.ta-levels .level.resistance{background:#2b3f9e}
.ta-levels .level.cmp{background:#141f55}
.ta-summary{font-size:11px;line-height:1.5;color:var(--muted);margin:6px 0 12px}
.ta-read{margin-top:auto;color:var(--blue);font-size:11px;font-weight:700}
.ta-read:hover{text-decoration:underline}

/* YOUTUBE / EXPERTS / PREMIUM */
.columns{display:grid;grid-template-columns:1.05fr 1.05fr .75fr;gap:14px;align-items:stretch}
.columns>.column{height:100%;display:flex;flex-direction:column}
.column{padding:15px}
.column h3{margin:0 0 10px;font-size:15px;font-weight:800}
.expert-scroll{height:360px;overflow-y:auto;padding-right:5px}
.expert{display:flex;gap:9px;padding:10px 0;border-bottom:1px solid var(--line)}
.expert:last-child{border:0}
.avatar{width:40px;height:40px;border-radius:50%;object-fit:cover;flex-shrink:0}
.expert b{font-size:12px}
.expert p{margin:2px 0;color:var(--muted);font-size:10px}
.video-scroll{max-height:360px;overflow-y:auto;padding-right:5px}
.video{position:relative;overflow:hidden;background:#fff;border-radius:8px;margin-bottom:9px}
.video:last-child{margin-bottom:0}
.video img{aspect-ratio:16/9;width:100%;object-fit:cover}
.yt-frame{position:relative;display:block;width:100%;aspect-ratio:16/9;padding:0;border:0;background:#000;overflow:hidden;cursor:pointer;border-radius:8px 8px 0 0}
.yt-frame img{width:100%;height:100%;object-fit:cover;transition:transform .25s ease,filter .25s ease}
.yt-frame:hover img{transform:scale(1.03);filter:brightness(.8)}
.yt-frame .play{left:50%;top:50%;bottom:auto;transform:translate(-50%,-50%);width:48px;height:48px;font-size:18px}
.yt-frame:hover .play{transform:translate(-50%,-50%) scale(1.1)}
.yt-frame iframe{position:absolute;inset:0;width:100%;height:100%;border:0;pointer-events:none}
.yt-overlay{position:absolute;inset:0;z-index:2;display:flex;align-items:flex-end;justify-content:flex-end;padding:10px;background:linear-gradient(transparent 60%,rgba(0,0,0,.45));opacity:0;transition:opacity .2s ease}
.yt-live:hover .yt-overlay,.yt-overlay:focus-visible{opacity:1}
.yt-overlay span{background:#e31d3b;color:#fff;font-size:11px;font-weight:800;padding:6px 10px;border-radius:6px}
.video .body a:hover b{color:var(--blue)}
.yt-open{color:var(--blue);font-weight:700}
.play{transition:transform .2s ease;color:#fff;background:#e31d3b;border-radius:50%;width:30px;height:30px;display:flex;align-items:center;justify-content:center;position:absolute;left:10px;bottom:44px;box-shadow:0 4px 12px rgba(0,0,0,.25)}

.premium{background:var(--premium-grad);color:#fff;padding:20px;height:100%;border-radius:var(--radius);display:flex;flex-direction:column}
.premium div.badge-custom{color:#6ee7b7;font-size:10px;font-weight:800;letter-spacing:.5px}
.premium h2{font-size:19px;color:#ffd36a;margin:6px 0;font-weight:800}
.premium p{font-size:12px;color:#c9d7e4}
.premium li{font-size:11px;margin:6px 0}
.premium .premium-list{padding-left:18px;margin:4px 0 10px}
.premium .premium-list li{font-size:13.5px;font-weight:600;margin:9px 0;color:#fff}
.premium .premium-list li.has-icon{list-style:none;margin-left:-18px}
.premium-note{border-top:1px solid rgba(255,255,255,.18);padding-top:10px;margin-bottom:14px;font-size:13px;font-weight:700;color:#ffd36a;display:flex;flex-direction:column;gap:6px}
.premium .btn-custom{margin-top:auto}
.premium-terms{background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.16);border-radius:10px;padding:10px 12px;margin-bottom:14px}
.premium-terms b{display:block;font-size:12.5px;color:#fff;margin-bottom:4px}
.premium .premium-terms p{font-size:11.5px;line-height:1.55;color:#c9d7e4;margin:0}
.premium-terms a{color:#ffd36a;font-weight:700}



/* ===== RESPONSIVE ===== */
/* Safety net against stray sideways scroll; "clip" (unlike "hidden") keeps the sticky header working */
body{overflow-x:clip}
/* Short pages: push the footer to the bottom so no grey strip shows under it */
body{min-height:100vh;display:flex;flex-direction:column}
body>*{flex-shrink:0}
footer,.socialbar{margin-top:auto}
.socialbar+footer{margin-top:0}

/* Social links row above the footer: icon + name, always one line (swipe on small phones) */
.socialbar{background:#fff;border-top:1px solid var(--line);padding:14px 0}
.social-row{display:flex;justify-content:center;gap:30px;flex-wrap:nowrap;overflow-x:auto;scrollbar-width:none}
.social-row::-webkit-scrollbar{display:none}
.social-row a{display:inline-flex;align-items:center;gap:7px;flex-shrink:0;white-space:nowrap;font-size:13px;font-weight:700;color:#1f3f7a}
.social-row a i{font-size:13px}
.social-row a:hover{color:var(--blue)}
/* Phones: all links visible on two centred rows (no sideways scrolling) */
@media(max-width:700px){.social-row{flex-wrap:wrap;justify-content:center;gap:10px 16px;overflow:visible}.social-row a{font-size:12.5px}}

/* Footer: just the important links on dark navy */
footer{width:100%;background:#0a1a33}
.foot-cols{display:grid;grid-template-columns:repeat(2,minmax(0,280px));justify-content:center;gap:24px 80px;padding:30px 0 28px}
.foot-col h3{font-size:14px;font-weight:700;color:#fff;margin:0 0 12px;padding-bottom:10px;border-bottom:1px solid rgba(255,255,255,.18)}
.foot-col a{display:block;font-size:13px;color:#c9d3e6;margin:8px 0}
.foot-col a:hover{color:#fff}
@media(max-width:600px){.foot-cols{grid-template-columns:1fr 1fr;gap:20px}}
.foot-note{max-width:980px;margin:0 auto;padding:14px 0;border-top:1px solid rgba(255,255,255,.12);font-size:12.5px;line-height:1.7;color:#c9d3e6;text-align:center}
.foot-note a{color:#fff;font-weight:700;text-decoration:underline}
/* Phones: the note as an even block (both edges straight, last line centred) instead of ragged centred lines */
@media(max-width:600px){
  .foot-note{text-align:justify;text-align-last:center;-webkit-hyphens:auto;hyphens:auto;font-size:12px;line-height:1.65;padding:14px 4px;word-spacing:-.2px}
  /* The web address on its own centred line, so the line before it is not stretched */
  .foot-note a{display:block;white-space:nowrap;text-align:center;margin-top:2px}
  .foot-copy{font-size:12px;line-height:1.6;padding:12px 4px 16px}
}
.foot-copy{text-align:center;font-size:12.5px;color:#9fb0cc;padding:14px 0 16px;border-top:1px solid rgba(255,255,255,.12)}/* Tablet & small laptop: collapse the header into a ☰ menu */
@media(max-width:1100px){
  .head{position:relative;min-height:64px}
  .menu-toggle{display:flex}
  .menu{display:none;position:absolute;top:100%;left:-20px;right:-20px;flex-direction:column;align-items:stretch;gap:0;background:#fff;border-top:1px solid var(--line);box-shadow:0 18px 30px rgba(27,42,107,.16);padding:8px 20px 18px;max-height:calc(100vh - 110px);overflow-y:auto}
  .menu.open{display:flex}
  nav{flex-direction:column;align-items:stretch;gap:0;font-size:14px}
  nav>a,.nav-dropdown>a{padding:13px 4px;border-bottom:1px solid var(--line)!important;display:flex;justify-content:space-between}
  nav a.active{color:var(--blue)}
  .news-menu,.media-menu{position:static;width:auto;opacity:1;visibility:visible;transform:none;box-shadow:none;border:0;border-radius:0;padding:4px 0 8px 12px;display:none;background:var(--bg)}
  .nav-dropdown.open .news-menu,.nav-dropdown.open .media-menu{display:grid;grid-template-columns:1fr 1fr}
  .news-menu a,.media-menu a{font-size:13px;padding:9px 10px}
  .header-right{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-top:14px}
  .language-select{height:42px;grid-column:1/-1;font-size:14px}
  .header-subscription{text-align:center;padding:12px 10px;font-size:13px}
  .header-subscription:last-child{grid-column:1/-1}
  .hero{grid-template-columns:1fr;height:auto}
  .latest{height:340px}
  .analysis{grid-template-columns:repeat(2,1fr)}
  .trends{grid-template-columns:1fr 1fr}
  .trends>.trendmain{grid-column:1/-1}
  .columns{grid-template-columns:1fr 1fr}
  .columns>.premium-column{grid-column:1/-1}
}

/* Small tablet */
@media(max-width:900px){
  .top .topin{justify-content:center;flex-wrap:wrap;column-gap:18px;row-gap:4px;padding:5px 0}
}

/* Phones */
@media(max-width:600px){
  .top{display:none}
  .container{padding-left:14px;padding-right:14px}
  .menu{left:-14px;right:-14px;padding:6px 14px 16px}
  .nav-dropdown.open .media-menu{grid-template-columns:1fr}
  .brand img{height:72px;max-width:360px}
  .lead h1{font-size:20px}
  .leadtext{padding:12px 14px 16px}
  .latest{height:auto;max-height:380px}
  .title h2{font-size:18px}
  .trends,.analysis,.columns{grid-template-columns:1fr}
  .expert-scroll,.video-scroll{height:auto;max-height:360px}
}

/* ===== DESIGN REFRESH: one font, consistent colours, brand social icons ===== */
body{font-family:'Inter','Segoe UI',system-ui,-apple-system,Roboto,Arial,sans-serif;-webkit-font-smoothing:antialiased}
:focus-visible{outline:3px solid rgba(43,63,158,.45);outline-offset:2px;border-radius:6px}
a,button{transition:color .15s ease,background-color .15s ease,border-color .15s ease,box-shadow .15s ease,transform .15s ease}

/* Header: logo scales with the screen, and never pushes the ☰ button off a phone screen */
.head{gap:20px}
.brand{min-width:0;flex-shrink:1}
.brand{gap:10px;line-height:1;color:var(--navy)}
.brand:hover{color:var(--navy)}
/* Original logo artwork (bull + "Investment Point"), with a small tagline as text (RNI no. is in the top bar and footer) */
.brand img.brand-icon{height:clamp(46px,4.4vw,60px);width:auto;max-width:none;flex-shrink:0}
.brand-text{display:flex;flex-direction:column;gap:3px;min-width:0}
.brand img.brand-name{height:clamp(22px,2.1vw,29px);width:auto;max-width:100%}
.brand-tag{margin-top:7px;font-size:clamp(9.5px,.9vw,11.5px);font-weight:800;letter-spacing:1.6px;text-transform:uppercase;color:#1f2a44;white-space:nowrap}
.header-btn{display:inline-flex;align-items:center;gap:6px;padding:9px 14px;border-radius:8px;font-size:12px;font-weight:800;border:1.5px solid transparent;white-space:nowrap}
.header-btn.gold{background:var(--gold);color:#141f55}
.header-btn.gold:hover{background:#e5aa18}
.header-btn.outline{border-color:var(--blue);color:var(--blue);background:#fff}
.header-btn.outline:hover{background:var(--blue2)}
.header-btn.blue{background:var(--blue);color:#fff}
.header-btn.blue:hover{background:var(--navy)}
.header-btn:hover{transform:translateY(-1px)}

/* Header polish: roomy nav, small chevrons, matching 40px controls on the right */
.head{min-height:78px;gap:28px}
.menu{gap:24px}
nav{gap:26px;font-size:14px;font-weight:600}
nav a{color:#27304a;padding:8px 0}
.nav-dropdown>a i{font-size:10px;transition:transform .15s ease}
.nav-dropdown:hover>a i,.nav-dropdown.open>a i{transform:rotate(180deg)}
.nav-dropdown.open .news-menu,.nav-dropdown.open .media-menu{opacity:1;visibility:visible;transform:translateY(0)}
.news-menu,.media-menu{top:calc(100% + 14px);min-width:200px;width:auto;padding:8px}
.news-menu:before,.media-menu:before{content:"";position:absolute;left:0;right:0;top:-16px;height:16px}
.news-menu a,.media-menu a{display:flex;align-items:center;gap:8px;font-size:13px;border:0;white-space:nowrap}
.news-menu a i{color:var(--blue);font-size:14px}
/* Services: Stock Market, Insurance */
.services-menu a i{color:var(--blue);font-size:14px}
.header-right{margin-left:auto;gap:10px}
.header-btn{height:40px;padding:0 16px;font-size:13px;border-radius:10px}
.header-btn.gold{box-shadow:0 4px 12px rgba(244,189,50,.35)}
.lang-pill{position:relative;display:inline-flex;align-items:center;height:40px;border:1px solid var(--line);border-radius:10px;background:#fff;color:var(--navy);cursor:pointer;margin:0}
.lang-pill:hover{border-color:var(--blue)}
.lang-pill i{position:absolute;pointer-events:none;color:var(--blue)}
.lang-pill i:first-child{left:12px;font-size:14px}
.lang-pill i:last-child{right:11px;font-size:10px}
.lang-pill .language-select{appearance:none;-webkit-appearance:none;height:100%;border:0;background:transparent;padding:0 30px 0 34px;font-size:13px;font-weight:600;color:var(--navy);cursor:pointer;outline:0}

/* Top bar social icons */
.topin{justify-content:space-between;gap:12px}
.top-social{display:flex;gap:6px}
.top-social a{width:26px;height:26px;margin:0;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;background:rgba(255,255,255,.1);color:#fff;font-size:12px;opacity:1}
.top-social a:hover{background:var(--c);color:#fff;transform:translateY(-1px)}



/* Cards: subtle lift on hover */
.card,.analysis article,.np-card,.video{transition:transform .18s ease,box-shadow .18s ease}
.analysis article:hover,.card.list:hover{transform:translateY(-2px);box-shadow:0 12px 28px rgba(27,42,107,.12)}
.btn-custom:hover{transform:translateY(-1px);box-shadow:0 6px 14px rgba(27,42,107,.15)}
.btn-custom.primary:hover{background:var(--navy);border-color:var(--navy)}
.btn-custom.gold:hover{background:#e5aa18;border-color:#e5aa18}

@media(max-width:1100px){
  .head{min-height:70px}
  nav{gap:0}
  nav>a,.nav-dropdown>a{padding:13px 4px}
  .nav-dropdown:hover>a i{transform:none}
  .nav-dropdown.open>a i{transform:rotate(180deg)}
  .news-menu:before,.media-menu:before{display:none}
  .header-right{grid-template-columns:1fr 1fr;margin-left:0}
  .header-btn{justify-content:center;height:44px;font-size:14px}
  .lang-pill{height:44px}
  .lang-pill .language-select{width:100%;font-size:14px}
}
@media(max-width:600px){
  .brand img.brand-icon{height:42px}
  .brand img.brand-name{height:19px}
  .brand-tag{margin-top:5px;font-size:9.5px;letter-spacing:.8px}}
/* Tablets & phones: stack the % change under the price so big prices (1,33,663.42) fit side by side */
@media(max-width:1100px){
  .quote{flex-direction:column;align-items:flex-start;justify-content:center;gap:4px;min-width:0;padding:10px 12px}
  .quote small{margin-bottom:4px;font-size:11px;letter-spacing:.4px}
  .quote strong{font-size:15px}
  .quote .stock-change,.quote>span.flat{padding:2px 8px;font-size:12px}
}
/* Phones: all the boxes fit across the screen (no sideways scrolling); text scales with the screen width */
@media(max-width:600px){
  .tickerin{padding-left:0;padding-right:0}
  .quote{align-items:center;text-align:center;gap:3px;min-height:0;padding:7px 3px}
  .quote>div{display:flex;flex-direction:column;align-items:center;min-width:0;width:100%}
  .quote small{margin-bottom:3px;padding:1px 4px;font-size:min(9px,2.3vw);letter-spacing:0}
  .quote strong{font-size:min(12px,2.85vw);letter-spacing:0;white-space:nowrap}
  .quote .stock-change,.quote>span.flat{padding:1px 5px;font-size:min(10.5px,2.6vw)}
}
@yield('extra-css')
</style>
<style>
/* Google Translate: keep the page looking native (hide its toolbar, tooltips and highlights) */
.skiptranslate iframe,.goog-te-banner-frame,iframe.VIpgJd-ZVi9od-ORHb-OEVmcd,#goog-gt-tt,.goog-te-balloon-frame,.VIpgJd-ZVi9od-aZ2wEe-wOHMyf{display:none!important}
body{top:0!important;position:static!important}
.goog-text-highlight,font[style]{background:none!important;box-shadow:none!important}
#google_translate_element{display:none}
.language-select.is-loading{opacity:.6;pointer-events:none}
/* Phones: form fields at 16px, so iPhones don't zoom the whole page in when a field is tapped */
@media(max-width:768px){
  input:not([type=checkbox]):not([type=radio]):not([type=submit]):not([type=button]):not([type=range]):not([type=file]),select,textarea{font-size:16px!important}
}
/* Phones: slimmer ☰ button + tagline scales with the screen, so it never runs under the ☰ button */
@media(max-width:600px){
  .head{gap:12px}
  .menu-toggle{width:40px;height:40px}
  .brand{gap:8px}
  .brand img.brand-icon{height:40px}
  .brand-tag{font-size:clamp(7.5px,2.4vw,9.5px);letter-spacing:.4px;overflow:hidden;text-overflow:clip}
}
</style>
</head>
<body>
@include('partials.page-progress')

@php $socialLinks = \App\Models\Setting::socialLinks(); @endphp
{{-- TOP BAR --}}
<div class="top"><div class="container topin">
  <div class="top-social" aria-label="Follow Investment Point">
    @foreach($socialLinks as $s)
      <a href="{{ $s['url'] }}" style="--c:{{ $s['color'] }}" @if($s['mail']) data-mail="{{ $s['mail'] }}" aria-label="Email us: {{ $s['mail'] }}" @else target="_blank" rel="noopener noreferrer" aria-label="{{ $s['label'] }}" @endif title="{{ $s['label'] }}"><i class="bi {{ $s['icon'] }}"></i></a>
    @endforeach
  </div>
  @php $istNow = now()->timezone('Asia/Kolkata'); @endphp
  <div class="notranslate" translate="no"><span id="topDate">{{ $istNow->format('D, d M Y') }}</span> &nbsp;|&nbsp; <span id="topTime">{{ $istNow->format('h:i:s A') }}</span></div>
</div></div>
<script>
// Live Indian time (IST) in the top bar, whatever the visitor's own timezone
(function () {
  const dateEl = document.getElementById('topDate');
  const timeEl = document.getElementById('topTime');
  const fmt = new Intl.DateTimeFormat('en-US', { timeZone: 'Asia/Kolkata', weekday: 'short', day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true });
  function tick() {
    const p = {};
    fmt.formatToParts(new Date()).forEach(x => { p[x.type] = x.value; });
    dateEl.textContent = p.weekday + ', ' + p.day + ' ' + p.month + ' ' + p.year; // Fri, 25 Sep 2026
    timeEl.textContent = p.hour + ':' + p.minute + ':' + p.second + ' ' + p.dayPeriod.toUpperCase(); // 10:23:05 AM
  }
  tick();
  setInterval(tick, 1000);
})();
</script>

{{-- HEADER --}}
<header class="container-fluid p-0"><div class="container"><div class="head">
  <a class="brand notranslate" translate="no" href="{{ route('home') }}" data-live-region="logo">
    <img class="brand-icon" src="{{ asset('images/logo-bull.png') }}" alt="Investment Point logo">
    <span class="brand-text">
      <img class="brand-name" src="{{ asset('images/logo-title.png') }}" alt="Investment Point">
      <span class="brand-tag">News • Markets • Insights</span>
    </span>
  </a>
  <button type="button" class="menu-toggle" id="menuToggle" aria-expanded="false" aria-controls="siteMenu" aria-label="Open menu">
    <span></span><span></span><span></span>
  </button>
  <div class="menu" id="siteMenu">
  <nav>
    <a @class(['active' => request()->routeIs('home')]) href="{{ route('home') }}">Home</a>
    {{-- Nikhil Bhatt's daily articles (Admin -> Editor's View), right after Home (owner, 26 Oct 2026). Expert's View left the header; it is still in the footer --}}
    <a @class(['active' => request()->routeIs('editors-desk.*')]) href="{{ route('editors-desk.index') }}">Editor's View</a>
    <div class="nav-dropdown"><a @class(['active' => request()->routeIs(['epaper.*', 'digital-newspaper.*'])]) href="{{ route('epaper.index', 'gujarati') }}">E-Paper <i class="bi bi-chevron-down"></i></a>
      <div class="news-menu">
        @foreach(config('epaper.editions') as $epKey => $epLang)
          <a href="{{ route('epaper.index', $epKey) }}"><i class="bi bi-newspaper"></i> {{ $epLang['label'] }}</a>
        @endforeach
        <a href="{{ route('digital-newspaper.index') }}"><i class="bi bi-journal-richtext"></i> Weekly News Paper</a>
      </div>
    </div>
    <div class="nav-dropdown"><a href="{{ route('home') }}#media">Media <i class="bi bi-chevron-down"></i></a>
      <div class="media-menu">
        <a href="https://www.youtube.com/c/NikhilBhattResearchAnalystSEBI" target="_blank" rel="noopener noreferrer">Youtube Videos</a>
        <a href="{{ route('media-articles.index') }}">Media Article</a>
        <a href="{{ route('divya-bhaskar.index') }}">Divya Bhaskar</a>
        <a href="https://www.businessworld.in/" target="_blank" rel="noopener noreferrer">Business World</a>
        <a href="https://economictimes.indiatimes.com/industry/banking/finance/investment-point-4-best-picks/articleshow/84995678.cms" target="_blank" rel="noopener noreferrer">Economic Times</a>
        <a href="https://gujarati.gnsnews.co.in/author/business-2/" target="_blank" rel="noopener noreferrer">GNS Gujarati</a>
        <a href="https://g18news.com/author/nikhil-bhatt" target="_blank" rel="noopener noreferrer">G18 News</a>
        <a href="https://vatsalyanews.com/category/uncategorized/%e0%a4%ac%e0%a4%bf%e0%a4%9c%e0%a4%bc%e0%a4%a8%e0%a5%87%e0%a4%b8/" target="_blank" rel="noopener noreferrer">Vatsalya News Hindi</a>
        <a href="https://vatsalyamsamachar.com/author/nikhil-bhatt/" target="_blank" rel="noopener noreferrer">Vatsalyam Samachar</a>
        <a href="https://www.snssamachar.com/author/nikhilbhatt" target="_blank" rel="noopener noreferrer">Nutan Saurashtra</a>
      </div>
    </div>
    {{-- Services: Stock Market = the Subscription page; Insurance = the Life Insurance page (owner, 27 Oct 2026);
         General Insurance only once switched on in Admin -> Insurance Products --}}
    <div class="nav-dropdown"><a @class(['active' => request()->routeIs('services.*')]) href="{{ route('services.insurance', 'life') }}">Services <i class="bi bi-chevron-down"></i></a>
      <div class="media-menu services-menu">
        <a href="{{ route('subscription') }}"><i class="bi bi-graph-up-arrow"></i> Stock Market</a>
        <a href="{{ route('services.insurance', 'life') }}"><i class="bi bi-shield-check"></i> Insurance</a>
        @if(in_array('general', \App\Models\InsuranceProduct::shown(), true))
          <a href="{{ route('services.insurance', 'general') }}"><i class="bi bi-shield-plus"></i> General Insurance</a>
        @endif
      </div>
    </div>
  </nav>
  <div class="header-right">
    {{-- Members only: "Login" shows while this browser's subscription is active (see App\Support\MemberLogin) --}}
    @if(auth('member')->check())
      <a class="header-btn outline" href="{{ route('member.dashboard') }}"><i class="bi bi-person-circle"></i> Dashboard</a>
    @elseif(\App\Support\MemberLogin::showButton())
      <a class="header-btn outline" href="{{ route('member.login') }}"><i class="bi bi-person-circle"></i> Login</a>
    @endif
    <a class="header-btn gold" href="{{ route('subscription') }}"><i class="bi bi-star-fill"></i> Subscription</a>
    <label class="lang-pill" for="languageSwitcher">
      <i class="bi bi-globe2"></i>
      <select id="languageSwitcher" class="language-select notranslate" translate="no" aria-label="Select Language">
        <option value="en">English</option><option value="hi" @selected($siteLang === 'hi')>हिन्दी</option><option value="gu" @selected($siteLang === 'gu')>ગુજરાતી</option>
      </select>
      <i class="bi bi-chevron-down"></i>
    </label>
  </div>
  </div>
</div></div></header>

{{-- TICKER - Live market data --}}
@unless(request()->routeIs(['about', 'blog.show']))
  {{-- Last known values are rendered server-side, then refreshed live from /market-data --}}
  {{-- Boxes = first rows of Admin -> Market Indices; the admin chooses which stock/index each shows --}}
  @php
    $marketSnapshot = app(\App\Services\MarketDataService::class)->cachedSnapshot();
    // Indian digit grouping (1,23,815.00), same as the live JS update's en-IN format
    $inr = function ($n) {
        [$int, $dec] = explode('.', number_format(abs($n), 2, '.', ''));
        $head = substr($int, 0, -3);
        $int = ($head !== '' ? preg_replace('/\B(?=(\d{2})+$)/', ',', $head) . ',' : '') . substr($int, -3);
        return ($n < 0 ? '-' : '') . $int . '.' . $dec;
    };
  @endphp
  <div class="ticker"><div class="container tickerin notranslate" translate="no">
    @foreach(\App\Models\MarketIndex::tickers() as $row)
      @php
        $marketKey = 'm' . $row->id;
        $quote = $marketSnapshot['indices'][$marketKey] ?? null;
        if ($quote && ($quote['symbol'] ?? null) !== $row->symbol) { $quote = null; } // renamed since last fetch
      @endphp
      <div class="quote" data-market="{{ $marketKey }}" role="button" tabindex="0" aria-haspopup="dialog" aria-label="{{ $row->name }}: today's gain / loss">

        <div><small>{{ $row->name }}</small><strong id="{{ $marketKey }}-price">{{ $quote ? $inr($quote['price']) : '--' }}</strong></div>
        @if($quote)
          {{-- The day's change in points (the percentage is in the pop-up that opens on a click) --}}
          <span id="{{ $marketKey }}-change" title="{{ number_format(abs($quote['change_percent']), 2) }}%" class="stock-change {{ $quote['change_percent'] > 0 ? 'up' : ($quote['change_percent'] < 0 ? 'down' : 'flat') }}"><span class="stock-arrow">{{ $quote['change_percent'] > 0 ? '▲' : ($quote['change_percent'] < 0 ? '▼' : '•') }}</span> {{ $inr(abs($quote['change'] ?? ($quote['price'] - ($quote['previous_close'] ?? $quote['price'])))) }}</span>
        @else
          <span id="{{ $marketKey }}-change" class="flat">--</span>
        @endif
      </div>
    @endforeach
  </div></div>
  <script>window.MARKET_SNAPSHOT = @json($marketSnapshot);</script>

  {{-- Popup when a ticker box is clicked: today's gain / loss for that index or stock (filled by JS, updates live) --}}
  <div class="modal fade" id="quoteModal" tabindex="-1" aria-labelledby="qmName" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content qm notranslate" translate="no">
        <div class="qm-head">
          <div>
            <small>Today's performance</small>
            <h5 id="qmName">—</h5>
          </div>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="qm-price"><span id="qmPrice">--</span><span id="qmBadge" class="qm-badge flat">--</span></div>
        <div id="qmResult" class="qm-result flat">
          <i id="qmIcon" class="bi bi-dash-circle-fill"></i>
          <div><b id="qmResultLabel">No change</b><span id="qmResultText">--</span></div>
        </div>
        <div class="qm-grid">
          <div><small>Previous close</small><b id="qmPrev">--</b></div>
          <div><small>Change (points)</small><b id="qmChange">--</b></div>
          <div><small>Change (%)</small><b id="qmPct">--</b></div>
        </div>
        <div class="qm-bar" aria-hidden="true"><div id="qmBarFill"></div></div>
        <p class="qm-foot"><span id="qmStatus" class="qm-dot"></span> <span id="qmStatusText">--</span></p>
      </div>
    </div>
  </div>
@endunless

@yield('content')

{{-- SOCIAL LINKS ROW (links: Admin -> Site Settings -> Social Media Links) --}}
@if($socialLinks)
<div class="socialbar"><div class="container social-row">
  @foreach($socialLinks as $s)
    <a href="{{ $s['url'] }}" @if($s['mail']) data-mail="{{ $s['mail'] }}" title="{{ $s['mail'] }}" @else target="_blank" rel="noopener noreferrer" @endif><i class="bi {{ $s['icon'] }}"></i> {{ $s['mail'] ? 'Mail' : $s['label'] }}</a>
  @endforeach
</div></div>
@endif

{{-- FOOTER --}}
<footer class="container-fluid p-0"><div class="container">
  <div class="foot-cols">
    <div class="foot-col">
      <h3>About Company</h3>
      <a href="{{ route('about') }}">About Us</a>
      <a href="{{ route('experts-view.index') }}">Expert’s View</a>
      <a href="{{ route('contact') }}">Contact Us</a>
      <a href="{{ route('blog.create') }}">Write for Us</a>
    </div>
    <div class="foot-col">
      <h3>Policy</h3>
      <a href="{{ route('disclaimer') }}">Disclaimer</a>
      <a href="{{ route('privacy') }}">Privacy Policy</a>
      <a href="{{ route('terms') }}">Terms &amp; Condition</a>
      <a href="{{ route('refund') }}">Terms of Refund &amp; Cancellation Policy</a>
      <a href="{{ route('cookies') }}">Cookie Policy</a>
    </div>
  </div>
  <p class="foot-note">The securities quoted are for illustration only and are not recommendatory. Investment in securities market are subject to market risks. Read Disclaimer and related all the documents carefully before investing, mentioned on <a href="https://www.investmentpoint.in" target="_blank" rel="noopener noreferrer">www.investmentpoint.in</a></p>
  {{-- Year updates by itself every 1 January --}}
  <div class="foot-copy">Copyright © {{ now('Asia/Kolkata')->year }}, Investment Point. All rights reserved.@if(app(\App\Services\StockPhotos::class)->keyed() && $stockPhotos = app(\App\Services\StockPhotos::class)->provider()) · <a href="{{ $stockPhotos === 'pexels' ? 'https://www.pexels.com' : 'https://pixabay.com' }}" target="_blank" rel="noopener nofollow">Photos provided by {{ $stockPhotos === 'pexels' ? 'Pexels' : 'Pixabay' }}</a>@endif</div></div></footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
// Mobile/tablet menu: ☰ toggle, tap-to-expand dropdowns, close on outside click / Esc
(function () {
  const toggle = document.getElementById('menuToggle');
  const menu = document.getElementById('siteMenu');
  if (!toggle || !menu) return;
  const mobile = () => window.matchMedia('(max-width:1100px)').matches;

  function setOpen(open) {
    menu.classList.toggle('open', open);
    toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    toggle.setAttribute('aria-label', open ? 'Close menu' : 'Open menu');
  }
  toggle.addEventListener('click', () => setOpen(!menu.classList.contains('open')));

  // Dropdown titles (E-Paper, Media) only open their list; a page opens when an option is picked.
  // Titles marked data-direct (Expert's View) open their own page; only their ⌄ arrow opens the list.
  const dropdowns = menu.querySelectorAll('.nav-dropdown');
  menu.querySelectorAll('.nav-dropdown > a').forEach(function (link) {
    link.addEventListener('click', function (e) {
      if (link.hasAttribute('data-direct') && !e.target.closest('.bi-chevron-down')) return;
      e.preventDefault();
      const item = link.parentElement;
      const open = !item.classList.contains('open');
      closeAll();
      item.classList.remove('closing');
      item.classList.toggle('open', open);
      // A second click on the title closes it even while the mouse is still over it
      if (!open) {
        item.classList.add('closing');
        item.addEventListener('mouseleave', () => item.classList.remove('closing'), { once: true });
      }
    });
  });
  function closeAll() {
    dropdowns.forEach(d => d.classList.remove('open'));
    if (document.activeElement && document.activeElement.closest('.nav-dropdown')) document.activeElement.blur();
  }
  // Clicking anywhere else, or Esc, closes an open dropdown list
  document.addEventListener('click', function (e) {
    if (!e.target.closest('.nav-dropdown')) closeAll();
  });
  document.addEventListener('keydown', e => { if (e.key === 'Escape') closeAll(); });

  document.addEventListener('click', function (e) {
    if (mobile() && menu.classList.contains('open') && !menu.contains(e.target) && !toggle.contains(e.target)) setOpen(false);
  });
  document.addEventListener('keydown', e => { if (e.key === 'Escape') setOpen(false); });
  // Close after following an in-page link like #media
  menu.querySelectorAll('a[href^="#"]').forEach(a => a.addEventListener('click', () => { if (mobile() && !a.parentElement.classList.contains('nav-dropdown')) setOpen(false); }));
  window.addEventListener('resize', () => { if (!mobile()) setOpen(false); });
})();
</script>

<script>
// Live market data: polls our own /market-data endpoint (Laravel fetches Yahoo server-side and caches it).
// Every config('market.cache_seconds') (15s) while NSE is open, every 60s after close.
// Other scripts can listen for the "market:update" event.
(function () {
    const URL = @json(route('market.data'));
    const OPEN_MS = {{ max(5, (int) config('market.cache_seconds')) * 1000 }};
    let timer = null;

    function formatPrice(n) {
        return Number(n).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function renderTicker(snapshot) {
        Object.entries(snapshot.indices || {}).forEach(function ([key, q]) {
            if (!q) return;
            const price = document.getElementById(key + '-price');
            const change = document.getElementById(key + '-change');
            if (!price || !change) return;
            const pct = Number(q.change_percent);
            const dir = pct > 0 ? 'up' : (pct < 0 ? 'down' : 'flat');
            const label = price.parentElement.querySelector('small');
            if (label && q.name && label.textContent !== q.name) label.textContent = q.name; // renamed in admin
            price.textContent = formatPrice(q.price);
            // The day's change in points; the percentage is in the pop-up and the tooltip
            const points = q.change != null ? Number(q.change) : Number(q.price) - Number(q.previous_close != null ? q.previous_close : q.price);
            change.className = 'stock-change ' + dir;
            change.title = Math.abs(pct).toFixed(2) + '%';
            change.innerHTML = '<span class="stock-arrow">' + (dir === 'up' ? '▲' : dir === 'down' ? '▼' : '•') + '</span> ' + formatPrice(Math.abs(points));
        });
    }

    function publish(snapshot) {
        window.MARKET_SNAPSHOT = snapshot;
        renderTicker(snapshot);
        renderPopup();
        document.dispatchEvent(new CustomEvent('market:update', { detail: snapshot }));
    }

    // Ticker popup: click a box to see today's gain / loss; stays live while open
    const modalEl = document.getElementById('quoteModal');
    let openKey = null, modal = null;
    const $ = id => document.getElementById(id);
    const signed = n => (n > 0 ? '+' : n < 0 ? '−' : '') + formatPrice(Math.abs(n));

    function renderPopup() {
        if (!openKey || !modalEl) return;
        const box = document.querySelector('.quote[data-market="' + openKey + '"]');
        const q = (window.MARKET_SNAPSHOT && window.MARKET_SNAPSHOT.indices || {})[openKey];
        $('qmName').textContent = (q && q.name) || (box && box.querySelector('small').textContent) || '';
        if (!q) {
            $('qmPrice').textContent = '--';
            $('qmResultLabel').textContent = 'Live price not available right now';
            $('qmResultText').textContent = 'Please try again in a minute.';
            return;
        }
        const pct = Number(q.change_percent), change = Number(q.change);
        const prev = q.previous_close != null ? Number(q.previous_close) : Number(q.price) - change;
        const dir = pct > 0 ? 'up' : (pct < 0 ? 'down' : 'flat');
        $('qmPrice').textContent = formatPrice(q.price);
        $('qmBadge').className = 'qm-badge ' + dir;
        $('qmBadge').textContent = (dir === 'up' ? '▲ ' : dir === 'down' ? '▼ ' : '') + signed(pct) + '%';
        $('qmResult').className = 'qm-result ' + dir;
        $('qmIcon').className = 'bi ' + (dir === 'up' ? 'bi-graph-up-arrow' : dir === 'down' ? 'bi-graph-down-arrow' : 'bi-dash-circle-fill');
        $('qmResultLabel').textContent = dir === 'up' ? 'Gain today' : dir === 'down' ? 'Loss today' : 'No change today';
        $('qmResultText').textContent = dir === 'flat'
            ? 'Same as the previous close.'
            : (dir === 'up' ? 'Up ' : 'Down ') + formatPrice(Math.abs(change)) + ' points (' + Math.abs(pct).toFixed(2) + '%) from the previous close.';
        $('qmPrev').textContent = formatPrice(prev);
        $('qmChange').textContent = signed(change);
        $('qmChange').className = dir;
        $('qmPct').textContent = signed(pct) + '%';
        $('qmPct').className = dir;
        const fill = $('qmBarFill');
        fill.style.width = Math.min(100, Math.abs(pct) / 3 * 100) + '%'; // full bar = a 3% move
        fill.style.background = dir === 'up' ? '#0a9f63' : dir === 'down' ? '#e5484d' : '#9aa3b5';
        const snap = window.MARKET_SNAPSHOT || {};
        const asOf = snap.as_of ? new Date(snap.as_of).toLocaleString('en-IN', { timeZone: 'Asia/Kolkata', day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' }) : '';
        $('qmStatus').className = 'qm-dot' + (snap.market_open ? ' open' : '');
        $('qmStatusText').textContent = (snap.market_open ? 'Market open · live, updates every few seconds' : 'Market closed · final values of the last session')
            + (asOf ? ' · as of ' + asOf + ' IST' : '');
    }

    function openPopup(box) {
        if (!modalEl || !window.bootstrap) return;
        openKey = box.dataset.market;
        renderPopup();
        modal = modal || new bootstrap.Modal(modalEl);
        modal.show();
    }
    if (modalEl) {
        modalEl.addEventListener('hidden.bs.modal', () => { openKey = null; });
        document.querySelectorAll('.quote[data-market]').forEach(function (box) {
            box.addEventListener('click', () => openPopup(box));
            box.addEventListener('keydown', e => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); openPopup(box); } });
        });
    }

    async function refresh() {
        let open = false;
        try {
            const res = await fetch(URL, { cache: 'no-store', headers: { Accept: 'application/json' } });
            if (!res.ok) throw new Error('HTTP ' + res.status);
            const snapshot = await res.json();
            publish(snapshot);
            open = !!snapshot.market_open;
        } catch (e) {
            console.warn('Market data refresh failed:', e.message);
        }
        clearTimeout(timer);
        timer = setTimeout(refresh, document.hidden ? 60000 : (open ? OPEN_MS : 60000));
    }

    document.addEventListener('visibilitychange', function () { if (!document.hidden) refresh(); });
    refresh();
})();
</script>

{{-- LANGUAGE SWITCHER: whole-page translation (incl. admin-added content) via Google Website Translator.
     The choice is kept in the "googtrans" cookie, so it stays on every page until changed. --}}
<div id="google_translate_element"></div>
<script>
(function () {
  const select = document.getElementById('languageSwitcher');
  const supported = ['en', 'hi', 'gu'];

  function currentLang() {
    const m = document.cookie.match(/(?:^|;\s*)googtrans=\/en\/([a-z]{2})/);
    return m && supported.includes(m[1]) ? m[1] : 'en';
  }

  // Cookie must be set for this host and (on a real domain) its parent domain, or Google ignores it
  function setCookie(value, expires) {
    const base = 'googtrans=' + value + ';path=/' + (expires ? ';expires=' + expires : '');
    document.cookie = base;
    const host = location.hostname;
    if (host.includes('.') && !/^\d+(\.\d+){3}$/.test(host)) {
      document.cookie = base + ';domain=.' + host.replace(/^www\./, '');
    }
  }

  // Google's script: already started in <head> on translated visits; loaded on demand otherwise
  function loadGoogle() {
    if (window.__gtLoading) return;
    window.__gtLoading = true;
    window.googleTranslateInit = function () {
      new google.translate.TranslateElement({ pageLanguage: 'en', includedLanguages: 'en,hi,gu', autoDisplay: false }, 'google_translate_element');
    };
    const s = document.createElement('script');
    s.src = 'https://translate.google.com/translate_a/element.js?cb=googleTranslateInit';
    s.async = true;
    document.body.appendChild(s);
  }

  // Translate the page in place (no reload): pick the language in Google's hidden language box
  function applyLanguage(target) {
    const started = Date.now();
    (function tryApply() {
      const combo = document.querySelector('.goog-te-combo');
      if (combo && combo.options.length > 1) {
        combo.value = target;
        combo.dispatchEvent(new Event('change'));
        return;
      }
      if (Date.now() - started > 8000) { location.reload(); return; } // Google slow/blocked: fall back to a reload
      setTimeout(tryApply, 100);
    })();
  }

  // Grey out the switcher until Google has finished translating
  function busyUntilTranslated() {
    select.classList.add('is-loading');
    const html = document.documentElement;
    const done = () => { select.classList.remove('is-loading'); observer.disconnect(); };
    const observer = new MutationObserver(() => { if (/translated-(ltr|rtl)/.test(html.className)) done(); });
    observer.observe(html, { attributes: true, attributeFilter: ['class'] });
    setTimeout(done, 5000); // e.g. Hindi → Gujarati keeps the same class, so don't wait forever
  }

  const lang = currentLang();
  if (lang !== 'en') {
    document.documentElement.lang = lang;
    loadGoogle();
  }
  if (select) {
    select.value = lang;
    select.addEventListener('change', function () {
      const target = select.value;
      if (target === 'en') {
        // Back to English: the untranslated page, without Google's script (fastest)
        select.classList.add('is-loading');
        setCookie('', 'Thu, 01 Jan 1970 00:00:00 GMT');
        location.reload();
        return;
      }
      setCookie('/en/' + target);
      document.documentElement.lang = target;
      busyUntilTranslated();
      loadGoogle();
      applyLanguage(target);
    });
  }
})();
</script>
<script>
// LIVE ADMIN UPDATES: when the admin saves anything, open pages refresh their content without a reload.
// Every 60s (and whenever the tab is shown again) we ask /content-version; if it changed, we fetch this
// page in the background and swap in only the parts that changed: [data-live-region] blocks, or the
// whole <main> on simple pages. Forms (<main data-live="off">) are never swapped, so nothing a visitor
// is typing is lost. 60s keeps the load low on shared hosting (every check is a PHP request).
(function () {
  const VERSION_URL = @json(route('content.version'));
  const INTERVAL = 60000;

  function regionsIn(doc) {
    const map = {};
    doc.querySelectorAll('[data-live-region]').forEach(el => { map[el.dataset.liveRegion] = el; });
    const main = doc.querySelector('main');
    if (main && main.dataset.live !== 'off' && !main.querySelector('[data-live-region]')) map.__main = main;
    return map;
  }

  // Server HTML of each region as first loaded (captured before page scripts change the DOM)
  const baseline = {};
  Object.entries(regionsIn(document)).forEach(([key, el]) => { baseline[key] = el.innerHTML; });
  if (!Object.keys(baseline).length) return;

  // Version this page was rendered with, so a change is noticed even if the tab opened in the background
  let version = @json(\App\Support\ContentVersion::current()), busy = false;

  async function applyUpdate() {
    const res = await fetch(location.href, { cache: 'no-store', headers: { 'X-Live-Update': '1' } });
    if (!res.ok) return;
    const doc = new DOMParser().parseFromString(await res.text(), 'text/html');
    const fresh = regionsIn(doc);
    const current = regionsIn(document);
    Object.entries(fresh).forEach(([key, el]) => {
      if (!current[key] || baseline[key] === el.innerHTML) return; // unchanged
      baseline[key] = el.innerHTML;
      current[key].innerHTML = el.innerHTML;
      document.dispatchEvent(new CustomEvent('live:region-updated', { detail: { key: key, el: current[key] } }));
    });
    const title = doc.querySelector('title');
    if (title) document.title = title.textContent;
    document.dispatchEvent(new CustomEvent('live:content-changed'));
  }

  async function check() {
    if (busy || document.hidden) return;
    busy = true;
    try {
      const res = await fetch(VERSION_URL, { cache: 'no-store', headers: { Accept: 'application/json' } });
      if (res.ok) {
        const v = (await res.json()).v;
        if (v !== version) await applyUpdate();
        version = v;
      }
    } catch (e) { /* offline or server busy: try again next time */ }
    busy = false;
  }

  check();
  setInterval(check, INTERVAL);
  document.addEventListener('visibilitychange', () => { if (!document.hidden) check(); });
})();
</script>
<script>
// Mail icon: phones open their mail app (mailto:); computers usually have no mail app set up,
// so open a Gmail "new message" window with our address filled in instead of doing nothing.
document.addEventListener('click', function (e) {
  const a = e.target.closest('a[data-mail]');
  if (!a || window.matchMedia('(pointer: coarse)').matches) return;
  e.preventDefault();
  window.open('https://mail.google.com/mail/?view=cm&fs=1&to=' + encodeURIComponent(a.dataset.mail) + '&su=' + encodeURIComponent('Investment Point enquiry'), '_blank', 'noopener');
});
</script>
@include('partials.whatsapp-button')
@include('partials.image-zoom')
@yield('extra-js')
@if(config('seo.protect_content'))
{{-- CONTENT PROTECTION (live site only, config/seo.php): a deterrent against copying articles.
     Blocks right-click, text selection / copy / drag, and the Inspect, View-source, Save and Print shortcuts.
     Search boxes and forms keep working (typing, paste). It can't stop a determined person (no website can),
     but it stops casual copying. --}}
<style>
  body{-webkit-user-select:none;user-select:none;-webkit-touch-callout:none}
  input,textarea,select,[contenteditable],[contenteditable] *{-webkit-user-select:text;user-select:text;-webkit-touch-callout:default}
  img{-webkit-user-drag:none;user-drag:none}
  @media print{body{display:none!important}}
</style>
<script>
(function () {
  const editable = el => el && el.closest && el.closest('input, textarea, select, [contenteditable]');
  const block = e => { if (!editable(e.target)) { e.preventDefault(); return false; } };

  ['contextmenu', 'copy', 'cut', 'dragstart', 'selectstart'].forEach(type => document.addEventListener(type, block, true));

  document.addEventListener('keydown', function (e) {
    const k = (e.key || '').toLowerCase();
    const ctrl = e.ctrlKey || e.metaKey;
    const devtools = k === 'f12'
      || (ctrl && e.shiftKey && ['i', 'j', 'c', 'k'].includes(k))   // Inspect / Console / Element picker (Chrome, Firefox)
      || (e.metaKey && e.altKey && ['i', 'j', 'c', 'u'].includes(k)); // Mac shortcuts
    const pageKeys = ctrl && ['u', 's', 'p'].includes(k);          // View source, Save page, Print
    const copyKeys = ctrl && ['c', 'x', 'a'].includes(k) && !editable(e.target);
    if (devtools || pageKeys || copyKeys) { e.preventDefault(); e.stopPropagation(); return false; }
  }, true);

  // No messages from the site in the browser console
  ['log', 'info', 'warn', 'debug', 'table', 'dir'].forEach(m => { try { console[m] = function () {}; } catch (err) {} });
})();
</script>
@endif
</body>
</html>
