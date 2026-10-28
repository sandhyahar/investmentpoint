{{-- Large round WhatsApp button fixed at the bottom-right of every public page: opens a chat with the
     office number. The link is the WhatsApp one in Admin -> Site Settings -> Social Media Links.
     Included once in layouts.app. --}}
@php
    $waLink = collect(\App\Models\Setting::socialLinks())->firstWhere('key', 'whatsapp')['url'] ?? 'https://wa.me/919939080808';
@endphp
<style>
  .wa-float{position:fixed;right:18px;bottom:calc(18px + env(safe-area-inset-bottom,0px));z-index:1500;width:60px;height:60px;border-radius:50%;background:#25d366;color:#fff;display:flex;align-items:center;justify-content:center;font-size:34px;line-height:1;box-shadow:0 8px 22px rgba(18,140,70,.45);transition:transform .18s ease,box-shadow .18s ease}
  .wa-float:hover,.wa-float:focus-visible{color:#fff;transform:scale(1.08);box-shadow:0 10px 26px rgba(18,140,70,.6)}
  .wa-float::before{content:"";position:absolute;inset:0;border-radius:50%;border:2px solid #25d366;animation:wa-ring 2.4s ease-out infinite;pointer-events:none}
  @keyframes wa-ring{0%{transform:scale(1);opacity:.7}70%,100%{transform:scale(1.55);opacity:0}}
  @media(max-width:700px){.wa-float{right:14px;bottom:calc(14px + env(safe-area-inset-bottom,0px));width:54px;height:54px;font-size:30px}}
  @media(prefers-reduced-motion:reduce){.wa-float::before{animation:none;display:none}}
  @media print{.wa-float{display:none}}
</style>
<a class="wa-float notranslate" translate="no" href="{{ $waLink }}" target="_blank" rel="noopener noreferrer" aria-label="Chat with us on WhatsApp" title="Chat with us on WhatsApp"><i class="bi bi-whatsapp" aria-hidden="true"></i></a>
