@extends('layouts.app')

@section('title', 'Contact Us | Investment Point')
@section('meta_description', 'Contact Investment Point for subscriptions, research questions, advertising or feedback. Call, WhatsApp or e-mail Nikhil Bhatt\'s team in Jamnagar, Gujarat.')

@php
    $mapPlace = 'Nikhil Bhatt - Research Analyst - SEBI, 1423, Shilp Epitome, Sindhubhavan Rd, opp. Nyara Petrol Pump, behind Rajpath Rangoli Road, Bodakdev, Ahmedabad, Gujarat 380059, India';
@endphp

@section('content')
<main class="tc-page">
    {{-- HERO --}}
    <section class="tc-hero">
        <div class="container">
            <div class="tc-crumbs">
                <a href="{{ route('home') }}">Home</a><span>›</span>Contact Us
            </div>
            <span class="tc-kicker">Get in touch</span>
            <h1>Contact Us</h1>
            <p>Please visit us with an appointment so we can give you a better &amp; best time.</p>
        </div>
    </section>

    <section class="container ct-body">
        {{-- Editor card --}}
        <article class="ct-card ct-person">
            <div class="ct-head">
                <img class="ct-avatar" src="{{ asset('images/nikhil-bhatt.png') }}" alt="Nikhil Bhatt">
                <div>
                    <h2 class="notranslate" translate="no">Nikhil Bhatt</h2>
                    <span>Editor – Owner</span>
                    <small>Investment Point – Gujarati Weekly News Paper</small>
                </div>
            </div>

            <div class="ct-rows">
                <div class="ct-row">
                    <i class="bi bi-geo-alt-fill"></i>
                    <div><b>Address</b><p>101, Madhav Complex,<br>Opp. DKV Collage,<br>Patel Colony,<br>Jamnagar</p></div>
                </div>
                <div class="ct-row">
                    <i class="bi bi-telephone-fill"></i>
                    <div><b>Contact</b><p><a class="notranslate" translate="no" href="tel:+919939080808">99390 80808</a></p></div>
                </div>
            </div>
        </article>

        {{-- Appointment note --}}
        <aside class="ct-card ct-note">
            <i class="bi bi-calendar-check"></i>
            <h3>Visit by appointment</h3>
            <p>Please welcome with an Appointment so we can provide you Better &amp; Best time.</p>
            <p class="ct-sign">Warm Regards…</p>
            <a class="btn-custom gold ct-call notranslate" translate="no" href="tel:+919939080808"><i class="bi bi-telephone-fill"></i> Call 99390 80808</a>
        </aside>

        {{-- Map --}}
        <section class="ct-card ct-map">
            <div class="ct-map-head">
                <div>
                    <h3>Nikhil Bhatt - Research Analyst - SEBI</h3>
                    <p>1423, Shilp Epitome, Sindhubhavan Rd, opp. Nyara Petrol Pump, behind Rajpath Rangoli Road, Bodakdev, Ahmedabad, Gujarat 380059, India</p>
                </div>
                <a class="ct-dir" href="https://www.google.com/maps/search/?api=1&query={{ urlencode($mapPlace) }}" target="_blank" rel="noopener noreferrer"><i class="bi bi-sign-turn-right-fill"></i> Get Directions</a>
            </div>
            <iframe src="https://www.google.com/maps?q={{ urlencode($mapPlace) }}&output=embed" title="Map: Nikhil Bhatt - Research Analyst - SEBI, Ahmedabad" loading="lazy" referrerpolicy="no-referrer-when-downgrade" allowfullscreen></iframe>
        </section>
    </section>
</main>
@endsection

@section('extra-css')
@include('partials.legal-styles')
.ct-body{display:grid;grid-template-columns:minmax(0,1fr) 340px;gap:24px;margin-top:28px;align-items:start}
.ct-card{background:#fff;border:1px solid var(--line);border-radius:14px;box-shadow:var(--shadow)}

.ct-person{padding:28px 30px}
.ct-head{display:flex;align-items:center;gap:18px;padding-bottom:20px;border-bottom:1px solid var(--line)}
.ct-avatar{width:84px;height:84px;flex-shrink:0;border-radius:50%;object-fit:cover;object-position:50% 25%;background:#fff;border:3px solid var(--gold)}
.ct-head h2{font-size:24px;font-weight:800;color:var(--navy);margin:0}
.ct-head span{display:block;font-size:14px;font-weight:700;color:var(--blue);margin-top:2px}
.ct-head small{display:block;font-size:13px;color:var(--muted);margin-top:2px}
.ct-rows{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:18px;margin-top:20px}
.ct-row{display:flex;gap:14px;align-items:flex-start}
.ct-row>i{flex-shrink:0;width:40px;height:40px;border-radius:10px;background:var(--blue2);color:var(--blue);font-size:17px;display:flex;align-items:center;justify-content:center}
.ct-row b{display:block;font-size:11px;font-weight:800;text-transform:uppercase;letter-spacing:.6px;color:var(--muted)}
.ct-row p{margin:3px 0 0;font-size:15px;line-height:1.6;color:var(--text)}
.ct-row a{font-size:18px;font-weight:800;color:var(--navy)}

.ct-note{padding:24px;background:linear-gradient(160deg,#fff8e6,#fff);border-color:#f6dfa0}
.ct-note>i{font-size:26px;color:#b7791f}
.ct-note h3{font-size:17px;font-weight:800;color:var(--navy);margin:8px 0 6px}
.ct-note p{font-size:14px;line-height:1.7;color:#293f51;margin:0 0 10px}
.ct-sign{font-style:italic;font-weight:600;color:var(--navy)!important}
.ct-call{display:flex!important;align-items:center;justify-content:center;gap:8px;margin-top:6px}

.ct-map{grid-column:1/-1;overflow:hidden}
.ct-map-head{display:flex;justify-content:space-between;align-items:center;gap:16px;padding:18px 22px}
.ct-map-head h3{font-size:17px;font-weight:800;color:var(--navy);margin:0 0 4px}
.ct-map-head p{font-size:13px;color:var(--muted);margin:0;line-height:1.6}
.ct-dir{flex-shrink:0;display:inline-flex;align-items:center;gap:7px;background:var(--blue);color:#fff;font-size:13px;font-weight:700;padding:10px 16px;border-radius:10px}
.ct-dir:hover{background:var(--navy);color:#fff}
.ct-map iframe{display:block;width:100%;height:420px;border:0}

@media(max-width:900px){
  .ct-body{grid-template-columns:1fr}
}
@media(max-width:640px){
  .ct-person{padding:22px 18px}
  .ct-rows{grid-template-columns:1fr}
  .ct-map-head{flex-direction:column;align-items:flex-start}
  .ct-map iframe{height:320px}
}
@endsection
