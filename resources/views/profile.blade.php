@extends('layouts.app')

@section('title', config('profile.name') . ' – Profile | Investment Point')
@section('meta_description', config('profile.name') . ' - research analyst, business editor and founder of Investment Point: profile, experience, awards and contact.')

@php($p = config('profile'))

@section('content')
<main class="tc-page">
    <section class="tc-hero pf-hero">
        <div class="container">
            <div class="tc-crumbs"><a href="{{ route('home') }}">Home</a><span>›</span><a href="{{ route('media-articles.index') }}">Media Articles</a><span>›</span>Profile</div>
            <div class="pf-head">
                <a class="pf-photo" href="{{ $p['photo_full'] }}" target="_blank" rel="noopener noreferrer" title="View full size">
                    <img src="{{ $p['photo'] }}" alt="{{ $p['person'] }}">
                    <span><i class="bi bi-arrows-fullscreen"></i> View Full Size</span>
                </a>
                <div>
                    <span class="tc-kicker">Profile</span>
                    <h1>{{ $p['name'] }}</h1>
                    <p>{{ $p['person'] }} · Founder, Editor &amp; Owner</p>
                </div>
            </div>
        </div>
    </section>

    <section class="container pf-body">
        <aside class="pf-card pf-contact">
            <h3>Contact me</h3>
            <a href="mailto:{{ $p['email'] }}" class="notranslate" translate="no"><i class="bi bi-envelope-fill"></i><span><small>Email</small>{{ $p['email'] }}</span></a>
            <a href="{{ $p['website'] }}" target="_blank" rel="noopener noreferrer"><i class="bi bi-globe2"></i><span><small>My Web Page</small>{{ preg_replace('#^https?://#', '', $p['website']) }}</span></a>
            <div><i class="bi bi-calendar-check"></i><span><small>Member since</small>{{ $p['since'] }}</span></div>
        </aside>

        <article class="pf-card">
            <h3>About me</h3>
            <dl class="pf-facts">
                <div><dt>Gender</dt><dd>{{ $p['gender'] }}</dd></div>
                <div><dt>Industry</dt><dd>{{ $p['industry'] }}</dd></div>
                <div><dt>Location</dt><dd>{{ $p['location'] }}</dd></div>
            </dl>
            <h4>Introduction</h4>
            <p>{{ $p['introduction'] }}</p>
            <a class="ma-profile-btn" href="{{ route('media-articles.index') }}"><i class="bi bi-newspaper"></i> Read my media articles</a>
        </article>
    </section>
</main>
@endsection

@section('extra-css')
@include('partials.legal-styles')
@include('media-articles.partials.styles')
.pf-hero{padding-bottom:40px}
.pf-head{display:flex;align-items:center;gap:26px}
.pf-head h1{margin:10px 0 4px}
.pf-photo{position:relative;flex-shrink:0;display:block;width:150px;height:150px;border-radius:50%;overflow:hidden;border:4px solid rgba(255,255,255,.3);box-shadow:0 10px 30px rgba(0,0,0,.3)}
.pf-photo img{width:100%;height:100%;object-fit:cover;display:block;background:#dfe5f3}
.pf-photo span{position:absolute;inset:auto 0 0 0;background:rgba(20,31,85,.85);color:#fff;font-size:11px;font-weight:700;text-align:center;padding:6px 0 10px;opacity:0;transition:opacity .15s}
.pf-photo:hover span{opacity:1}
.pf-body{display:grid;grid-template-columns:300px minmax(0,1fr);gap:24px;margin-top:28px;align-items:start}
.pf-card{background:#fff;border:1px solid var(--line);border-radius:14px;box-shadow:var(--shadow);padding:24px}
.pf-card h3{font-size:13px;font-weight:800;text-transform:uppercase;letter-spacing:.6px;color:var(--muted);margin:0 0 14px}
.pf-card h4{font-size:15px;font-weight:800;color:var(--navy);margin:20px 0 8px}
.pf-card p{font-size:15px;line-height:1.8;color:#293f51;margin:0 0 18px}
.pf-contact{display:flex;flex-direction:column;gap:6px}
.pf-contact>a,.pf-contact>div{display:flex;gap:12px;align-items:center;padding:10px 0;border-bottom:1px solid var(--line);color:var(--text);min-width:0}
.pf-contact>:last-child{border:0}
.pf-contact i{flex-shrink:0;width:38px;height:38px;border-radius:10px;background:var(--blue2);color:var(--blue);display:flex;align-items:center;justify-content:center;font-size:16px}
.pf-contact span{min-width:0;overflow-wrap:anywhere;font-size:14px;font-weight:600}
.pf-contact small{display:block;font-size:11px;font-weight:800;text-transform:uppercase;letter-spacing:.5px;color:var(--muted)}
.pf-contact a:hover span{color:var(--blue)}
.pf-facts{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:1px;background:var(--line);border:1px solid var(--line);border-radius:10px;overflow:hidden;margin:0}
.pf-facts div{background:#fff;padding:12px 16px}
.pf-facts dt{font-size:11px;font-weight:800;text-transform:uppercase;letter-spacing:.5px;color:var(--muted)}
.pf-facts dd{margin:2px 0 0;font-size:15px;font-weight:700;color:var(--navy)}
@media(max-width:900px){.pf-body{grid-template-columns:1fr}}
@media(max-width:640px){
  .pf-head{flex-direction:column;align-items:flex-start;gap:16px}
  .pf-photo{width:120px;height:120px}
  .pf-photo span{opacity:1}
  .pf-facts{grid-template-columns:1fr}
}
@endsection
