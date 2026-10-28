@extends('admin.layouts.app')

@section('page-title', 'Welcome')

@section('content')
{{-- Team users without the Dashboard land here after login (owner, 23 Oct 2026): hello, role, username, their pages --}}
@php
  $hour = (int) now('Asia/Kolkata')->format('G');
  [$greeting, $sun] = $hour < 12 ? ['Good morning', '🌅'] : ($hour < 17 ? ['Good afternoon', '☀️'] : ['Good evening', '🌙']);
  $icons = ['Overview' => '📊', 'Publish' => '✍️', 'E-Paper' => '📰', 'News & Market' => '📈', 'Articles & Media' => '🖼️', 'Members' => '👥', 'Settings' => '⚙️'];
@endphp
<div class="welcome-hero mb-4">
  <div class="welcome-wave" aria-hidden="true">👋</div>
  <div>
    <h1>Hello, {{ $user->name }}!</h1>
    <p>{{ $greeting }} {{ $sun }} Welcome to the Investment Point admin panel.</p>
  </div>
</div>

<div class="row g-3 mb-4">
  <div class="col-md-6">
    <div class="widget welcome-fact">
      <span class="welcome-emoji" aria-hidden="true">🧑‍💼</span>
      <div><small>Your role</small><b>{{ $role }}</b></div>
    </div>
  </div>
  <div class="col-md-6">
    <div class="widget welcome-fact">
      <span class="welcome-emoji" aria-hidden="true">🔑</span>
      <div><small>Your login username</small><b>{{ $user->username }}</b></div>
    </div>
  </div>
</div>

<div class="widget">
  <h5 style="font-weight:800;font-size:15px;margin-bottom:12px">🚀 Your pages</h5>
  @if($pages)
    <div class="welcome-pages">
      @foreach($pages as $page)
        <a href="{{ route($page['route']) }}" class="welcome-page"><span aria-hidden="true">{{ $icons[$page['group']] ?? '📄' }}</span> {{ $page['label'] }} <i class="bi bi-arrow-right"></i></a>
      @endforeach
    </div>
  @else
    <p class="text-muted mb-0" style="font-size:13px">No pages are given to your role yet. Please ask the Super Admin.</p>
  @endif
</div>

<style>
.welcome-hero{display:flex;align-items:center;gap:18px;background:linear-gradient(135deg,#141f55,#2b3f9e);color:#fff;border-radius:var(--radius);padding:26px 28px;box-shadow:var(--shadow)}
.welcome-hero h1{font-size:26px;font-weight:800;margin:0 0 4px;color:#fff}
.welcome-hero p{margin:0;color:#dfe5ff;font-size:14px}
.welcome-wave{font-size:46px;line-height:1;animation:welcome-wave 1.6s ease-in-out 2;transform-origin:70% 70%}
@keyframes welcome-wave{0%,60%,100%{transform:rotate(0)}10%,30%{transform:rotate(16deg)}20%,40%{transform:rotate(-8deg)}}
@media (prefers-reduced-motion:reduce){.welcome-wave{animation:none}}
.welcome-fact{display:flex;align-items:center;gap:14px}
.welcome-emoji{font-size:30px;line-height:1}
.welcome-fact small{display:block;font-size:12px;color:#66798d;font-weight:600}
.welcome-fact b{font-size:17px;color:#10253f}
.welcome-pages{display:flex;flex-wrap:wrap;gap:10px}
.welcome-page{display:inline-flex;align-items:center;gap:8px;padding:10px 16px;border:1px solid #d6def5;border-radius:10px;background:#f4f7ff;color:#2b3f9e;font-weight:700;font-size:14px;text-decoration:none}
.welcome-page:hover{background:#2b3f9e;color:#fff}
</style>
@endsection
