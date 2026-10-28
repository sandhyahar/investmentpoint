@extends('layouts.app')

{{-- Services -> Insurance (Life Insurance, or General Insurance once shown): tab "Products" (tick plan categories) and tab
     "Join As Advisor"; both ask name, mobile, age and the address with city / PIN code. Sent to Admin -> Services ->
     Insurance Enquiries. $insurance (life | general), $label, $products --}}
@section('title', $label . ' | Investment Point')
@section('meta_description', $label . ' plans with Investment Point: term plan, child saving, wealth creation and retirement plans. Ask for a call back, or join as an advisor.')

@php
  $sent = session('enquiry_sent');
  // The tab to open: the one just sent or with errors, else the address (#adviser), else Products
  $openTab = $sent ?: ($errors->getBag('adviser')->any() ? 'adviser' : ($errors->getBag('product')->any() ? 'product' : null));
@endphp

@section('content')
<main class="tc-page">
  <section class="tc-hero">
    <div class="container">
      <div class="tc-crumbs"><a href="{{ route('home') }}">Home</a><span>›</span>Services<span>›</span>Insurance</div>
      <span class="tc-kicker">Services · Insurance</span>
      <h1>{{ $label }}</h1>
      <p>Protect your family's future and grow your savings. Tell us what you need, and Investment Point will call you back with the right plan.</p>
    </div>
  </section>

  <section class="container ins-body">
    <div class="ins-tabs" role="tablist">
      <button type="button" role="tab" data-ins-tab="product" aria-selected="true"><i class="bi bi-umbrella"></i> Products</button>
      <button type="button" role="tab" data-ins-tab="adviser" aria-selected="false"><i class="bi bi-person-badge"></i> Join As Advisor</button>
    </div>

    {{-- PRODUCTS: tick the plans, then the contact details --}}
    <div class="ins-panel" data-ins-panel="product" id="product">
      @if($sent === 'product')
        <div class="ins-done"><i class="bi bi-check-circle-fill"></i><div><b>Thank you! Your request has been sent.</b>Our team will call you soon about the plans you chose.</div></div>
      @endif
      <form method="POST" action="{{ route('services.insurance.enquire', $insurance) }}" class="ins-card" novalidate>
        @csrf
        <input type="hidden" name="kind" value="product">
        <input type="text" name="website" class="ins-hp" tabindex="-1" autocomplete="off" aria-hidden="true">
        <h2>Choose the plans you are interested in</h2>
        <p class="ins-lead">Tick one or more plans. You can choose more than one.</p>

        @if($products->isEmpty())
          <p class="text-muted">The plans will be listed here soon. Meanwhile, send your details and we will call you.</p>
        @else
          <div class="ins-products">
            @foreach($products as $i => $product)
              <label class="ins-product">
                <input type="checkbox" name="products[]" value="{{ $product->id }}" @checked(in_array($product->id, old('kind') === 'product' ? (array) old('products', []) : []))>
                <span class="ins-tick"><i class="bi bi-check-lg"></i></span>
                <span><b>{{ $i + 1 }}. {{ $product->name }}</b>@if($product->description)<small>{{ $product->description }}</small>@endif</span>
              </label>
            @endforeach
          </div>
          @error('products', 'product')<div class="ins-error">{{ $message }}</div>@enderror
        @endif

        @include('services.partials.contact-fields', ['bag' => 'product'])
        <button class="btn-custom gold ins-submit"><i class="bi bi-telephone-fill"></i> Get a call back</button>
      </form>
    </div>

    {{-- JOIN AS ADVISOR --}}
    <div class="ins-panel" data-ins-panel="adviser" id="adviser" hidden>
      @if($sent === 'adviser')
        <div class="ins-done"><i class="bi bi-check-circle-fill"></i><div><b>Thank you for your interest!</b>Our team will call you soon about joining as an advisor.</div></div>
      @endif
      <div class="ins-adviser">
        <div class="ins-card ins-why">
          <h2>Join As Advisor</h2>
          <p class="ins-lead">Start a career in insurance with Investment Point and help families secure their future.</p>
          <ul>
            <li><i class="bi bi-cash-coin"></i><span><b>Earn as you grow</b>Income from every policy you help a family buy.</span></li>
            <li><i class="bi bi-clock-history"></i><span><b>Work at your own time</b>Part-time or full-time, from your own city.</span></li>
            <li><i class="bi bi-mortarboard"></i><span><b>Guidance to get licensed</b>Help with the training and exam needed to become an advisor.</span></li>
            <li><i class="bi bi-people"></i><span><b>Serve your community</b>Bring protection and savings plans to the people you know.</span></li>
          </ul>
        </div>
        <form method="POST" action="{{ route('services.insurance.enquire', $insurance) }}" class="ins-card" novalidate>
          @csrf
          <input type="hidden" name="kind" value="adviser">
          <input type="text" name="website" class="ins-hp" tabindex="-1" autocomplete="off" aria-hidden="true">
          <h2>Your details</h2>
          <p class="ins-lead">Send your details and we will call you.</p>
          @include('services.partials.contact-fields', ['bag' => 'adviser'])
          <button class="btn-custom gold ins-submit"><i class="bi bi-send-fill"></i> Join now</button>
        </form>
      </div>
    </div>

    <datalist id="ins-city-list"></datalist>
    <p class="ins-note">Insurance is the subject matter of solicitation. For more details on risk factors, terms and conditions, please read the sales brochure carefully before concluding a sale.</p>
  </section>
</main>

<script>
// Products / Join As Advisor tabs (the open one is kept in the address: #adviser)
(function () {
  const tabs = document.querySelectorAll('[data-ins-tab]');
  function open(name) {
    tabs.forEach(t => t.setAttribute('aria-selected', t.dataset.insTab === name ? 'true' : 'false'));
    document.querySelectorAll('[data-ins-panel]').forEach(p => { p.hidden = p.dataset.insPanel !== name; });
  }
  tabs.forEach(t => t.addEventListener('click', () => { open(t.dataset.insTab); history.replaceState(null, '', '#' + t.dataset.insTab); }));
  open(@json($openTab) || (location.hash === '#adviser' ? 'adviser' : 'product'));
})();

// City (type to search, all of India) -> its PIN codes; the state goes in a hidden field. India Post's list is fetched
// once, when a City box is first used (or at once after a form error). Same list and rules as the subscription page.
(function () {
  const URL = @json(\App\Support\IndiaPincodes::url());
  const places = Array.from(document.querySelectorAll('.ins-place'));
  const datalist = document.getElementById('ins-city-list');
  let choices = null, loading = null;

  function setup(box) {
    const pick = box.querySelector('[data-city-pick]'), city = box.querySelector('[data-city]'),
          pin = box.querySelector('[data-pincode]'), state = box.querySelector('[data-state]');
    const choose = (c, chosen) => {
      city.value = c ? c.city : '';
      state.value = c ? c.state : '';
      pin.innerHTML = '';
      pin.add(new Option('Select PIN code', ''));
      (c ? c.pins : []).forEach(p => pin.add(new Option(p, p, false, p === chosen)));
      pin.disabled = !c;
    };
    // The typed text must be one of the list's names (any capitals); it then picks the city
    const onPick = () => {
      const typed = pick.value.trim().toLowerCase();
      const c = choices && choices.find(x => x.label.toLowerCase() === typed);
      if (c && pick.value !== c.label) pick.value = c.label;
      if (!c || c.city !== city.value || c.state !== state.value) choose(c, '');
    };
    pick.addEventListener('focus', load);
    pick.addEventListener('input', onPick);
    pick.addEventListener('change', onPick);
    box.restore = () => {
      const old = choices.find(x => x.state === box.dataset.oldState && x.city === box.dataset.oldCity);
      if (old) { pick.value = old.label; choose(old, box.dataset.oldPincode); }
    };
  }

  function load() {
    if (choices || loading) return loading;
    places.forEach(b => { b.querySelector('[data-city-pick]').placeholder = 'Loading…'; });
    loading = fetch(URL).then(r => r.json()).then(data => {
      const all = [];
      Object.keys(data).forEach(s => Object.keys(data[s]).forEach(c => all.push({ state: s, city: c, pins: data[s][c] })));
      // A name found in two states (Aurangabad: Bihar and Maharashtra) shows its state
      const count = {};
      all.forEach(x => { count[x.city] = (count[x.city] || 0) + 1; });
      all.forEach(x => { x.label = count[x.city] > 1 ? x.city + ' (' + x.state + ')' : x.city; });
      all.sort((a, b) => a.label.localeCompare(b.label));
      datalist.innerHTML = all.map(x => '<option value="' + x.label.replace(/"/g, '&quot;') + '"></option>').join('');
      choices = all;
      places.forEach(b => { b.querySelector('[data-city-pick]').placeholder = 'Type your city'; b.restore(); });
    }).catch(() => {
      loading = null;
      places.forEach(b => { b.querySelector('[data-city-pick]').placeholder = 'Could not load - please reload the page'; });
    });
    return loading;
  }

  places.forEach(setup);
  if (places.some(b => b.dataset.oldCity)) load(); // after a form error: put the city and PIN code back
})();
</script>
@endsection

@section('extra-css')
@include('partials.legal-styles')
.ins-body{margin-top:28px}
.ins-tabs{display:inline-flex;gap:6px;background:#fff;border:1px solid var(--line);border-radius:14px;padding:6px;box-shadow:var(--shadow);margin-bottom:20px}
.ins-tabs button{border:0;background:transparent;font-size:15px;font-weight:800;color:var(--navy);padding:10px 22px;border-radius:10px;display:inline-flex;align-items:center;gap:8px;cursor:pointer}
.ins-tabs button[aria-selected="true"]{background:var(--blue);color:#fff}
.ins-card{background:#fff;border:1px solid var(--line);border-radius:14px;box-shadow:var(--shadow);padding:26px 28px}
.ins-card h2{font-size:22px;font-weight:800;color:var(--navy);margin:0 0 6px}
.ins-lead{font-size:14px;color:var(--muted);margin:0 0 18px}
.ins-products{display:grid;grid-template-columns:repeat(auto-fill,minmax(250px,1fr));gap:12px;margin-bottom:6px}
.ins-product{display:flex;gap:12px;align-items:flex-start;border:2px solid var(--line);border-radius:12px;padding:14px;cursor:pointer;transition:border-color .15s,background .15s}
.ins-product:hover{border-color:#b8c6f0}
.ins-product input{position:absolute;opacity:0;pointer-events:none}
.ins-tick{flex-shrink:0;width:24px;height:24px;border-radius:7px;border:2px solid #b8c6f0;display:flex;align-items:center;justify-content:center;color:#fff;font-size:15px;margin-top:1px}
.ins-tick i{opacity:0}
.ins-product b{display:block;font-size:15px;color:var(--navy)}
.ins-product small{display:block;font-size:13px;line-height:1.5;color:var(--muted);margin-top:3px}
.ins-product:has(input:checked){border-color:var(--blue);background:var(--blue2)}
.ins-product:has(input:checked) .ins-tick{background:var(--blue);border-color:var(--blue)}
.ins-product:has(input:checked) .ins-tick i{opacity:1}
.ins-product:has(input:focus-visible){outline:3px solid #ffd36a;outline-offset:2px}
.ins-fields{display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-top:18px}
.ins-fields .ins-wide{grid-column:1/-1}
.ins-two{display:grid;grid-template-columns:minmax(0,1fr) 110px;gap:14px}
.ins-place{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}
.ins-fields label{display:block;font-size:13px;font-weight:700;color:var(--navy);margin-bottom:5px}
.ins-fields input,.ins-fields textarea,.ins-fields select{width:100%;border:1px solid var(--line);border-radius:10px;padding:11px 13px;font-size:15px;background:#fff}
.ins-fields select:disabled{background:#f4f6fb;color:var(--muted)}
.ins-fields input:focus,.ins-fields textarea:focus,.ins-fields select:focus{outline:0;border-color:var(--blue);box-shadow:0 0 0 3px rgba(43,63,158,.12)}
.ins-fields .is-invalid{border-color:#dc3545}
.ins-error{font-size:13px;font-weight:600;color:#c42b30;margin-top:5px}
.ins-submit{margin-top:18px;display:inline-flex!important;align-items:center;gap:8px;border:0;font-size:15px!important;padding:12px 26px!important}
.ins-hp{position:absolute;left:-9999px;width:1px;height:1px;opacity:0}
.ins-done{display:flex;gap:12px;align-items:flex-start;background:#e8f7ef;border:1px solid #bbe8cf;color:#0a7a4a;border-radius:12px;padding:14px 16px;margin-bottom:16px;font-size:14px}
.ins-done i{font-size:22px}
.ins-done b{display:block;font-size:15px}
.ins-adviser{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1.3fr);gap:20px;align-items:start}
.ins-adviser .ins-fields{grid-template-columns:1fr}
.ins-why{background:linear-gradient(160deg,#fff8e6,#fff);border-color:#f6dfa0}
.ins-why ul{list-style:none;padding:0;margin:0;display:grid;gap:14px}
.ins-why li{display:flex;gap:12px;align-items:flex-start}
.ins-why li>i{flex-shrink:0;width:40px;height:40px;border-radius:10px;background:#fff;border:1px solid #f6dfa0;color:#b7791f;font-size:18px;display:flex;align-items:center;justify-content:center}
.ins-why b{display:block;font-size:15px;color:var(--navy)}
.ins-why li span{font-size:14px;color:#293f51;line-height:1.5}
.ins-note{font-size:12px;color:var(--muted);margin:22px 0 0;line-height:1.6}
@media(max-width:900px){.ins-adviser{grid-template-columns:1fr}}
@media(max-width:640px){
  .ins-card{padding:20px 16px}
  .ins-fields,.ins-place{grid-template-columns:1fr}
  .ins-tabs{display:flex}
  .ins-tabs button{flex:1;justify-content:center;padding:10px 12px}
}
@endsection
