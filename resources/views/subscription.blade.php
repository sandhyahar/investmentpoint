@extends('layouts.app')

@section('title', 'Subscription | Investment Point')
@section('meta_description', 'Subscribe to Investment Point: the Daily Digital News Letter on WhatsApp or the Weekly Gujarati News Paper by post, by Nikhil Bhatt.')

@section('content')
@php
    // The plan chosen: after a form error the same one, else ?plan=newspaper, else the first
    $chosen = old('plan', request('plan'));
    $chosen = isset($plans[$chosen]) ? $chosen : array_key_first($plans);
    $rupees = fn ($n) => '₹' . number_format($n);
@endphp

{{-- data-live="off": the live admin updates never swap this form (a swap loses what was typed and the button's script) --}}
<main class="sp-page" data-live="off">
    <div class="sp-shell sp-shell-wide">
        <div class="sp-head">
            <span class="tag">Investment Point Subscription</span>
            <h1>Choose your plan</h1>
        </div>

        @if(session('subscribed'))
            <div class="sp-done" role="status" style="max-width:560px;margin:0 auto">
                <b>✅ Thank you! Your subscription request has been received.</b>
                <p>{{ $plan['contact_name'] }} will contact you shortly on your mobile number to complete your subscription.</p>
            </div>
        @else
        <form class="sp-form" method="POST" action="{{ route('subscription.store') }}" novalidate id="sp-form">
            @csrf
            {{-- Honeypot: hidden from people, bots fill it --}}
            <input type="text" name="website" class="sp-hp" tabindex="-1" autocomplete="off" aria-hidden="true">

            {{-- The plans: two compact cards side by side (also on phones); choosing one (radio) sets the
                 price, the "What you get" list and what the form asks for --}}
            <div class="sp-plans" role="radiogroup" aria-label="Subscription plans">
                @foreach($plans as $key => $p)
                    <label @class(['sp-plan-pick', 'is-chosen' => $chosen === $key, 'is-post' => $p['deliver'] === 'post'])>
                        <input type="radio" name="plan" value="{{ $key }}" class="sp-plan-radio" @checked($chosen === $key)
                               data-name="{{ $p['name'] }}" data-price="{{ $rupees($p['price']) }}" data-period="{{ $p['period'] }}" data-deliver="{{ $p['deliver'] }}">
                        <span class="sp-pick-check" aria-hidden="true"><i class="bi bi-check-lg"></i></span>
                        <small class="sp-pick-kind">{{ $p['period'] === 'year' ? 'Yearly' : 'Monthly' }}</small>
                        <b class="sp-pick-name">{{ $p['name'] }}</b>
                        <span class="sp-pick-price"><strong><span class="sp-rs">₹</span>{{ number_format($p['price']) }}</strong><em>/ {{ $p['period'] }}</em></span>
                        @if($p['tagline'])
                            <span class="sp-pick-line"><i class="bi {{ $p['deliver'] === 'post' ? 'bi-mailbox2' : 'bi-whatsapp' }}"></i> {{ $p['tagline'] }}</span>
                        @endif
                    </label>
                @endforeach
            </div>
            @error('plan')<small class="sp-err" style="text-align:center">{{ $message }}</small>@enderror

            {{-- What the chosen plan gives (one list, switched with the plan) --}}
            <div class="sp-gets">
                @foreach($plans as $key => $p)
                    <div class="sp-gets-plan" data-plan="{{ $key }}" @if($chosen !== $key) hidden @endif>
                        <h3>What you get <span>· {{ $p['name'] }}</span></h3>
                        <ul>
                            @foreach($p['benefits'] as [$icon, $benefit])
                                <li><span aria-hidden="true">{{ $icon }}</span>{{ $benefit }}</li>
                            @endforeach
                            @if($p['member_access'])
                                <li class="is-extra"><span aria-hidden="true">🔐</span>Member Login With WhatsApp OTP</li>
                            @endif
                        </ul>
                    </div>
                @endforeach
                <div class="sp-chips">
                    <span class="is-edu"><i aria-hidden="true">🎯</i> Educational &amp; Journalism Purposes Only</span>
                    <span class="is-no"><i aria-hidden="true">❌</i> No Buy/Sell Recommendations</span>
                </div>
            </div>

            {{-- Details for the chosen plan --}}
            <div class="sp-details" id="subscribe" style="scroll-margin-top:110px">
                <div class="sp-details-head">
                    <h3>Your details</h3>
                </div>

                {{-- Name and WhatsApp number side by side (one under the other on phones). The number is
                     also the WhatsApp number (no "whatsapp" field sent: the server uses this one). --}}
                <div class="sp-two">
                    <div class="sp-field">
                        <label for="sp-name">Full name *</label>
                        <input id="sp-name" type="text" name="name" value="{{ old('name') }}" maxlength="100" required autocomplete="name" @class(['is-invalid' => $errors->has('name')])>
                        @error('name')<small class="sp-err">{{ $message }}</small>@enderror
                    </div>
                    <div class="sp-field">
                        <label for="sp-phone">WhatsApp number *</label>
                        <input id="sp-phone" type="tel" name="phone" value="{{ old('phone') }}" maxlength="16" required autocomplete="tel" placeholder="10-digit WhatsApp number" @class(['is-invalid' => $errors->has('phone')])>
                        @error('phone')<small class="sp-err">{{ $message }}</small>@enderror
                    </div>
                </div>

                {{-- Daily Digital News Letter: the login OTP goes to this number --}}
                <div class="sp-for" data-for="whatsapp" @if($plans[$chosen]['deliver'] !== 'whatsapp') hidden @endif>
                    <small class="sp-wa-hint"><i class="bi bi-whatsapp" style="color:#128c7e"></i> Login OTP is sent to this number on WhatsApp.</small>
                </div>

                {{-- Weekly Gujarati News Paper: the postal address - house / street / area typed, then the City
                     (type to search) and its PIN code from India Post's list (loaded when this plan is chosen);
                     the State fills in by itself --}}
                <div class="sp-for" data-for="post" id="sp-post" data-pincodes="{{ \App\Support\IndiaPincodes::url() }}"
                     data-old-state="{{ old('state') }}" data-old-city="{{ old('city') }}" data-old-pincode="{{ old('pincode') }}"
                     @if($plans[$chosen]['deliver'] !== 'post') hidden @endif>
                    <label for="sp-address">Postal address *</label>
                    <textarea id="sp-address" name="address" rows="2" maxlength="400" autocomplete="street-address"
                              placeholder="House / flat, street, area" @class(['is-invalid' => $errors->has('address')])>{{ old('address') }}</textarea>
                    @error('address')<small class="sp-err">{{ $message }}</small>@enderror
                    <div class="sp-three">
                        <div class="sp-field">
                            <label for="sp-city-pick">City *</label>
                            <input id="sp-city-pick" type="text" list="sp-city-list" autocomplete="off" maxlength="80" placeholder="Type your city" @class(['is-invalid' => $errors->has('city')])>
                            <datalist id="sp-city-list"></datalist>
                            <input type="hidden" name="city" id="sp-city">
                            @error('city')<small class="sp-err">{{ $message }}</small>@enderror
                        </div>
                        <div class="sp-field">
                            <label for="sp-pincode">PIN code *</label>
                            <select id="sp-pincode" name="pincode" disabled @class(['is-invalid' => $errors->has('pincode')])><option value="">Select PIN code</option></select>
                            @error('pincode')<small class="sp-err">{{ $message }}</small>@enderror
                        </div>
                        <div class="sp-field">
                            <label for="sp-state">State *</label>
                            <input id="sp-state" type="text" name="state" readonly tabindex="-1" placeholder="Fills in by itself" @class(['sp-auto', 'is-invalid' => $errors->has('state')])>
                            @if(!$errors->has('city')) @error('state')<small class="sp-err">{{ $message }}</small>@enderror @endif
                        </div>
                    </div>
                    <small class="sp-wa-hint">The printed paper is posted here every week.</small>
                </div>

                <label for="sp-email">Email <span>(optional)</span></label>
                <input id="sp-email" type="email" name="email" value="{{ old('email') }}" maxlength="150" autocomplete="email" @class(['is-invalid' => $errors->has('email')])>
                @error('email')<small class="sp-err">{{ $message }}</small>@enderror

                <label class="sp-agree">
                    <input type="checkbox" name="agree" value="1" id="sp-agree" @checked(old('agree')) required>
                    <span>I have read and agree to the <a href="{{ route('terms') }}" target="_blank">Terms &amp; Conditions</a>, <a href="{{ route('privacy') }}" target="_blank">Privacy Policy</a>, <a href="{{ route('refund') }}" target="_blank">Refund &amp; Cancellation Policy</a> and <a href="{{ route('disclaimer') }}" target="_blank">Disclaimer</a>, and to sharing the information entered on this page with Shree Investment Point (owner of this page) and Razorpay. I understand the content is for educational purposes only and is not a buy/sell recommendation.</span>
                </label>
                @error('agree')<small class="sp-err">{{ $message }}</small>@enderror

                <button type="submit" class="sp-cta" id="sp-submit" @disabled(!old('agree'))><i class="bi bi-star-fill"></i> Subscribe <span id="sp-pay-price">{{ $rupees($plans[$chosen]['price']) }}</span> →</button>

                @guest('member')
                    <p class="sp-secure" style="font-size:13px;margin-top:14px"><a href="{{ route('member.login') }}" style="color:var(--blue);font-weight:800">Subscription Login</a></p>
                @endguest

                <div class="sp-contact">
                    <small>Contact Us</small>
                    <b>{{ $plan['contact_name'] }}</b>
                    <a class="notranslate" translate="no" href="mailto:{{ $plan['contact_email'] }}">✉️ {{ $plan['contact_email'] }}</a>
                    <a class="notranslate" translate="no" href="tel:{{ $plan['contact_phone'] }}">📞 {{ preg_replace('/^\+91(\d{5})(\d{5})$/', '+91 $1 $2', $plan['contact_phone']) }}</a>
                </div>
            </div>
        </form>
        @endif

        <div class="sp-steps">
            <div class="sp-step">
                <span>1</span>
                <div><b>Choose a plan</b><small>Daily news letter on WhatsApp, or the weekly Gujarati paper by post.</small></div>
            </div>
            <div class="sp-step">
                <span>2</span>
                <div><b>Pay securely</b><small>Agree to the terms and pay with Razorpay: UPI, cards or net banking.</small></div>
            </div>
            <div class="sp-step">
                <span>3</span>
                <div><b>Start receiving</b><small>The news letter arrives on WhatsApp (plus your member page); the paper comes by post.</small></div>
            </div>
        </div>
    </div>
</main>
@endsection

@section('extra-js')
<script>
// "Subscribe" works only after the "I agree" box is ticked
(function () {
    const sync = () => {
        const box = document.getElementById('sp-agree');
        const btn = document.getElementById('sp-submit');
        if (!box || !btn) return;
        btn.disabled = !box.checked;
    };
    document.addEventListener('change', e => { if (e.target.id === 'sp-agree') sync(); });
    // After Reload or Back the browser ticks the box again by itself, later than this script: check again then
    window.addEventListener('pageshow', sync);
    window.addEventListener('load', sync);
    sync();
})();
// Postal address: City (type to search, all of India) -> its PIN codes; the State fills in by itself.
// India Post's list is fetched once, when the paper plan is shown.
(function () {
    const box = document.getElementById('sp-post');
    if (!box) return;
    const pick = document.getElementById('sp-city-pick'), list = document.getElementById('sp-city-list'),
          city = document.getElementById('sp-city'), pin = document.getElementById('sp-pincode'), state = document.getElementById('sp-state');
    let choices = null, loading = null;
    const pins = (c, chosen) => {
        pin.innerHTML = '';
        pin.add(new Option('Select PIN code', ''));
        (c ? c.pins : []).forEach(p => pin.add(new Option(p, p, false, p === chosen)));
        pin.disabled = !c;
    };
    const choose = (c, chosen) => {
        city.value = c ? c.city : '';
        state.value = c ? c.state : '';
        pins(c, chosen);
    };
    // The typed text must be one of the list's names (any capitals); it then picks the city
    const onPick = () => {
        const typed = pick.value.trim().toLowerCase();
        const c = choices && choices.find(x => x.label.toLowerCase() === typed);
        if (c && pick.value !== c.label) pick.value = c.label;
        if (!c || c.city !== city.value || c.state !== state.value) choose(c, '');
    };
    pick.addEventListener('input', onPick);
    pick.addEventListener('change', onPick);
    window.spLoadPins = function () {
        if (choices || loading) return;
        pick.placeholder = 'Loading…';
        loading = fetch(box.dataset.pincodes).then(r => r.json()).then(data => {
            const all = [];
            Object.keys(data).forEach(s => Object.keys(data[s]).forEach(c => all.push({ state: s, city: c, pins: data[s][c] })));
            // A name found in two states (Aurangabad: Bihar and Maharashtra) shows its state
            const count = {};
            all.forEach(x => { count[x.city] = (count[x.city] || 0) + 1; });
            all.forEach(x => { x.label = count[x.city] > 1 ? x.city + ' (' + x.state + ')' : x.city; });
            all.sort((a, b) => a.label.localeCompare(b.label));
            list.innerHTML = all.map(x => '<option value="' + x.label.replace(/"/g, '&quot;') + '"></option>').join('');
            choices = all;
            pick.placeholder = 'Type your city';
            // After a form error: the choices sent before
            const old = all.find(x => x.state === box.dataset.oldState && x.city === box.dataset.oldCity);
            if (old) pick.value = old.label;
            choose(old, box.dataset.oldPincode);
        }).catch(() => { loading = null; pick.placeholder = 'Could not load - please reload the page'; });
    };
})();
// Choosing a plan: highlight its card, show its price and ask for a WhatsApp number or a postal address
(function () {
    const radios = Array.from(document.querySelectorAll('.sp-plan-radio'));
    if (!radios.length) return;
    function apply(scroll) {
        const r = radios.find(x => x.checked) || radios[0];
        radios.forEach(x => x.closest('.sp-plan-pick').classList.toggle('is-chosen', x === r));
        document.querySelectorAll('.sp-gets-plan').forEach(box => { box.hidden = box.dataset.plan !== r.value; });
        document.getElementById('sp-pay-price').textContent = r.dataset.price;
        document.querySelectorAll('.sp-for[data-for]').forEach(box => { box.hidden = box.dataset.for !== r.dataset.deliver; });
        if (r.dataset.deliver === 'post' && window.spLoadPins) window.spLoadPins();
        if (scroll) document.getElementById('subscribe').scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
    radios.forEach(r => r.addEventListener('change', () => apply(true)));
    window.addEventListener('pageshow', () => apply(false)); // choice restored by the browser on Back
    apply(false);
})();
</script>
@endsection

@section('extra-css')
@include('partials.subscription-styles')
.sp-same{display:flex;align-items:flex-start;gap:8px;margin:2px 0 10px;font-size:13.5px;font-weight:600;color:var(--text);cursor:pointer}
.sp-same input{width:17px;height:17px;margin:2px 0 0;flex-shrink:0;accent-color:var(--blue);cursor:pointer}
.sp-same .bi-whatsapp{color:#128c7e}
.sp-wa-hint{display:block;font-size:12px;color:var(--muted);margin:-4px 0 12px}
/* Compact layout: two small plan cards side by side (also on phones), one "What you get" box, the form */
.sp-shell-wide{max-width:720px}
.sp-plans,.sp-gets,.sp-details{width:100%;max-width:680px;margin-left:auto;margin-right:auto}
.sp-plans{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}
.sp-form .sp-plan-pick{position:relative;display:flex;flex-direction:column;gap:3px;margin:0;padding:16px 16px 14px;border-radius:16px;
  background:var(--premium-grad);color:#fff;font-size:inherit;font-weight:inherit;cursor:pointer;box-shadow:0 10px 26px rgba(16,37,63,.14);
  transition:box-shadow .2s ease,transform .2s ease,opacity .2s ease;opacity:.82}
.sp-form .sp-plan-pick.is-post{background:linear-gradient(135deg,#5b1d1d 0%,#8a2b1e 55%,#b4471f 100%)}
.sp-form .sp-plan-pick:hover{transform:translateY(-2px);opacity:1}
.sp-form .sp-plan-pick.is-chosen{opacity:1;box-shadow:0 0 0 3px var(--gold),0 14px 32px rgba(16,37,63,.2)}
.sp-plan-radio{position:absolute;opacity:0;pointer-events:none}
.sp-form .sp-plan-pick:focus-within{box-shadow:0 0 0 3px rgba(43,63,158,.45),0 10px 26px rgba(16,37,63,.14)}
.sp-form .sp-plan-pick.is-chosen:focus-within{box-shadow:0 0 0 3px var(--gold),0 14px 32px rgba(16,37,63,.2)}
.sp-pick-check{position:absolute;top:12px;right:12px;width:24px;height:24px;border-radius:50%;border:2px solid rgba(255,255,255,.55);display:flex;align-items:center;justify-content:center;font-size:15px;color:transparent}
.sp-plan-pick.is-chosen .sp-pick-check{background:var(--gold);border-color:var(--gold);color:#141f55}
.sp-form .sp-plan-pick .sp-pick-kind{font-size:10.5px;font-weight:800;letter-spacing:1px;text-transform:uppercase;color:#6ee7b7}
.sp-form .sp-plan-pick.is-post .sp-pick-kind{color:#fde68a}
.sp-pick-name{font-size:16px;font-weight:800;line-height:1.25;color:#fff;padding-right:28px}
.sp-pick-price{display:flex;align-items:baseline;gap:5px;margin-top:4px}
.sp-pick-price strong{font-size:30px;line-height:1.1;font-weight:900;color:#ffd36a;letter-spacing:-.5px}
.sp-form .sp-pick-price .sp-rs{margin-right:.18em;color:inherit;font-weight:inherit} /* not the grey of ".sp-form label span" */
.sp-form .sp-plan-pick .sp-pick-price em{font-style:normal;font-size:13px;font-weight:700;color:#fff}
.sp-form .sp-plan-pick .sp-pick-line{font-size:12px;font-weight:500;color:#e6ecf7;line-height:1.4}
.sp-pick-line .bi-whatsapp{color:#6ee7b7}
/* What the chosen plan gives */
.sp-gets{margin-top:16px;background:#fff;border:1px solid var(--line);border-radius:14px;padding:14px 16px;box-shadow:var(--shadow)}
.sp-gets h3{margin:0 0 10px;font-size:12px;font-weight:800;letter-spacing:.6px;text-transform:uppercase;color:var(--muted)}
.sp-gets h3 span{text-transform:none;letter-spacing:0;color:var(--navy)}
.sp-gets ul{list-style:none;margin:0;padding:0;display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px 16px}
.sp-gets li{display:flex;align-items:center;gap:9px;font-size:13.5px;font-weight:600;color:var(--text);line-height:1.35}
.sp-gets li span{flex-shrink:0;width:26px;height:26px;border-radius:7px;background:var(--blue2);display:flex;align-items:center;justify-content:center;font-size:14px}
.sp-gets li.is-extra span{background:#e7f6ef}
.sp-gets-plan[hidden]{display:none}
/* The details form under the plans */
.sp-details{margin-top:16px;background:#fff;border:1px solid var(--line);border-radius:18px;box-shadow:0 18px 44px rgba(16,37,63,.1);padding:22px 26px 26px;display:flex;flex-direction:column}
.sp-details-head h3{font-size:18px;font-weight:800;color:var(--navy);margin:0 0 4px}
.sp-details-head p{margin:0 0 6px;font-size:14px;color:var(--muted)}
.sp-details-head b{color:var(--navy)}
.sp-form textarea{border:1px solid #d5ddeb;border-radius:10px;padding:11px 14px;font:inherit;font-size:14px;background:#fbfcff;resize:vertical;min-height:84px}
.sp-form textarea:focus{outline:0;border-color:var(--blue);background:#fff;box-shadow:0 0 0 3px rgba(43,63,158,.12)}
.sp-form textarea.is-invalid{border-color:#dc2626}
.sp-for,.sp-field{display:flex;flex-direction:column}
.sp-for[hidden]{display:none}
.sp-shell-wide .sp-steps{max-width:680px;margin-left:auto;margin-right:auto}
/* Compact details form: smaller boxes, name + mobile side by side, the note as two small chips */
.sp-gets .sp-chips{display:flex;flex-wrap:wrap;gap:6px;margin:12px 0 0}
.sp-gets .sp-chips span{display:inline-flex;align-items:center;gap:5px;background:#fff8e6;border:1px solid #f6dfa0;border-radius:20px;padding:3px 10px;font-size:12px;font-weight:700;color:#7a4e00}
.sp-gets .sp-chips i{font-style:normal;font-size:12px}
.sp-gets .sp-chips .is-edu{background:#eef2fc;border-color:#c5d1f3;color:#2b3f9e}   /* blue */
.sp-gets .sp-chips .is-no{background:#fdf0f0;border-color:#f3c4c4;color:#c42b30}    /* red */
.sp-two{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:0 12px}
/* Postal address: City / PIN code / State side by side (one under the other on phones) */
.sp-three{display:grid;grid-template-columns:1.3fr 1fr 1.2fr;gap:0 12px}
.sp-details input.sp-auto{background:#f3f5fa;color:var(--text);cursor:default}
.sp-details input.sp-auto:focus{box-shadow:none;border-color:#d5ddeb}
.sp-details select{border:1px solid #d5ddeb;border-radius:10px;padding:9px 10px;font:inherit;font-size:14px;background:#fbfcff;color:var(--text);width:100%;min-width:0}
.sp-details select:focus{outline:0;border-color:var(--blue);background:#fff;box-shadow:0 0 0 3px rgba(43,63,158,.12)}
.sp-details select:disabled{color:var(--muted);background:#f3f5fa;cursor:not-allowed}
.sp-details select.is-invalid{border-color:#dc2626}
.sp-details.sp-details label{margin:8px 0 4px;font-size:12.5px}
.sp-details input[type=text],.sp-details input[type=tel],.sp-details input[type=email]{padding:9px 12px;font-size:14px}
.sp-details textarea{padding:9px 12px;min-height:62px}
.sp-details .sp-same{margin:6px 0 4px}
.sp-details .sp-wa-hint{margin:2px 0 4px}
.sp-details .sp-terms{margin-top:10px;padding:9px 12px;font-size:11.5px;line-height:1.5}
.sp-details .sp-terms b{font-size:12px;margin-bottom:2px}
.sp-details .sp-terms p{margin:0 0 3px}
.sp-details .sp-agree{margin:10px 0 2px!important;padding:9px 10px;font-size:12px!important;line-height:1.45}
.sp-details .sp-cta{margin-top:10px;padding:13px}
.sp-details .sp-contact{margin-top:12px;padding:10px}
.sp-details .sp-contact b{font-size:15px}
.sp-details .sp-contact a{font-size:14px;margin-top:2px}
@media(max-width:600px){
  .sp-two,.sp-three{grid-template-columns:1fr}
}
/* Phones: everything tighter, the 3 steps left out (the page explains itself) */
@media(max-width:600px){
  .sp-page{padding:16px 10px 28px}
  .sp-head{margin-bottom:14px}
  .sp-head h1{font-size:24px;margin:8px 0 4px}
  .sp-head p{font-size:13px}
  .sp-plans{gap:10px}
  .sp-form .sp-plan-pick{padding:12px 11px 11px;border-radius:14px}
  .sp-pick-check{top:9px;right:9px;width:20px;height:20px;font-size:12px}
  .sp-form .sp-plan-pick .sp-pick-kind{font-size:9.5px}
  .sp-pick-name{font-size:13.5px;padding-right:22px}
  .sp-pick-price strong{font-size:22px}
  .sp-form .sp-plan-pick .sp-pick-price em{font-size:11px}
  .sp-form .sp-plan-pick .sp-pick-line{font-size:11px}
  .sp-gets{margin-top:12px;padding:12px}
  .sp-gets ul{grid-template-columns:1fr;gap:7px}
  .sp-gets li{font-size:13px}
  .sp-gets li span{width:22px;height:22px;font-size:12px}
  .sp-details{margin-top:12px;padding:16px 14px 18px;border-radius:14px}
  .sp-shell-wide .sp-steps{display:none}
}
@endsection
