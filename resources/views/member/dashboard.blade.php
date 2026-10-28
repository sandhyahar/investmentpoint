{{-- Member page. $active, $endsAt, $current; when active: $search, $latestDate (today or the searched day), $isWeekend, $picks, $previous (date => picks) --}}
@extends('layouts.app')

@section('title', 'My Dashboard | Investment Point')
@section('meta_robots', 'noindex, nofollow')

@section('content')
@php
    $user = auth()->user();
    $longDay = fn ($d) => \Illuminate\Support\Carbon::parse($d)->format('l, d M Y');
@endphp

<main class="sp-page">
    <div class="mb-shell">
        <div class="mb-top">
            <div>
                <span class="tag">Investment Point Premium</span>
                <h1>Hello, {{ \Illuminate\Support\Str::of($user->name ?: 'Member')->before(' ') }}</h1>
                <p>
                    @if($current)
                        <b>● Subscription active</b>
                    @elseif($user->isAdmin())
                        Admin preview
                    @else
                        No active subscription
                    @endif
                </p>
            </div>
            <div class="mb-top-right">
                {{-- Subscribed on / expires on (expiry counts renewals paid in advance) --}}
                @if($current && $endsAt)
                    @php($daysLeft = (int) ceil(now()->diffInDays($endsAt, false)))
                    <div class="mb-period">
                        <div><small>Subscribed on</small><b>{{ \App\Models\SubscriptionRequest::day($current->starts_at) }}</b></div>
                        <i class="bi bi-arrow-right" aria-hidden="true"></i>
                        <div><small>Expires on</small><b>{{ \App\Models\SubscriptionRequest::day($endsAt) }}</b></div>
                        <span @class(['mb-days', 'mb-days-low' => $daysLeft <= 5])>{{ $daysLeft }} {{ Str::plural('day', $daysLeft) }} left</span>
                    </div>
                @endif
                <form method="POST" action="{{ route('member.logout') }}">
                    @csrf
                    <button type="submit" class="mb-logout"><i class="bi bi-box-arrow-right"></i> Logout</button>
                </form>
            </div>
        </div>
        @if($current && $endsAt && $daysLeft <= 5)
            <div class="sp-demo" role="status">Your subscription expires in {{ $daysLeft }} {{ Str::plural('day', $daysLeft) }}. <a href="{{ route('subscription') }}" style="font-weight:800;color:var(--blue)">Renew now →</a></div>
        @endif

        @if(session('success'))<div class="mb-ok" role="status">{{ session('success') }}</div>@endif

        @if(!$active)
            <div class="sp-plan mb-auth">
                <div class="sp-plan-body" style="text-align:center">
                    <h3 style="font-size:18px;text-transform:none;letter-spacing:0;color:var(--navy)">Your subscription is not active</h3>
                    <p style="color:var(--muted);font-size:14px">Subscribe to see the daily stocks with CMP, support and resistance levels (for education only).</p>
                    <a class="sp-cta" style="display:block;text-decoration:none" href="{{ route('subscription') }}"><i class="bi bi-star-fill"></i> Subscribe now →</a>
                </div>
            </div>
        @else
            {{-- Date search --}}
            <form class="mb-search" method="GET" action="{{ route('member.dashboard') }}">
                <label for="mb-date"><i class="bi bi-calendar3"></i> Search by date</label>
                <input id="mb-date" type="date" name="date" value="{{ $search?->format('Y-m-d') }}" max="{{ now('Asia/Kolkata')->format('Y-m-d') }}" required>
                <button type="submit"><i class="bi bi-search"></i> Search</button>
                @if($search)<a href="{{ route('member.dashboard') }}"><i class="bi bi-x-lg"></i> Back to latest</a>@endif
                @error('date')<small class="sp-err">Please pick a valid date.</small>@enderror
            </form>

            {{-- Latest picks (today's once added), or the searched date --}}
            <section class="pk-day pk-latest">
                <div class="pk-day-head">
                    <h2>{{ $search ? 'Stock levels for ' . $longDay($search) : "Today's Stock Levels" }}</h2>
                    @if(!$search)<span class="pk-date">{{ $longDay($latestDate) }}</span>@endif
                </div>

                @if($picks->isEmpty())
                    @if($search)
                        <div class="pk-empty">No stocks were added for {{ $longDay($search) }}. It may be a market holiday or weekend.</div>
                    @elseif($isWeekend)
                        <div class="pk-soon">
                            <i class="bi bi-calendar-x"></i>
                            <div><b>Market is closed today</b>The stock market is closed on weekends. See the previous day data below.</div>
                        </div>
                    @else
                        {{-- The admin hasn't added today's stocks yet --}}
                        <div class="pk-soon">
                            <i class="bi bi-hourglass-split"></i>
                            <div><b>Today's data is updating soon…</b>Today's stocks with CMP, support and resistance will appear here shortly. Please check back in a little while.@if($previous->isNotEmpty()) Meanwhile, see the previous day data below.@endif</div>
                        </div>
                    @endif
                @else
                    <div class="pk-grid">
                        @foreach($picks as $pick)
                            @include('member.partials.pick-card', ['pick' => $pick])
                        @endforeach
                    </div>
                @endif
            </section>

            {{-- The 7 dates before, behind the "Previous" button --}}
            @if(!$search && $previous->isNotEmpty())
                <div class="pk-prev-bar">
                    <button type="button" class="pk-prev-btn" id="pk-prev-btn" aria-expanded="false" aria-controls="pk-previous">
                        <i class="bi bi-clock-history"></i> <span>Previous day data</span> <i class="bi bi-chevron-down pk-chev"></i>
                    </button>
                </div>
                <div id="pk-previous" hidden>
                    @foreach($previous as $day => $dayPicks)
                        <section class="pk-day">
                            <div class="pk-day-head"><h2>{{ $longDay($day) }}</h2></div>
                            <div class="pk-grid">
                                @foreach($dayPicks as $pick)
                                    @include('member.partials.pick-card', ['pick' => $pick])
                                @endforeach
                            </div>
                        </section>
                    @endforeach
                    <p class="mb-note" style="margin-top:4px">Looking for an older day? Use <b>Search by date</b> above.</p>
                </div>
            @endif

            <p class="mb-note">🎯 Educational purposes only · ❌ No buy/sell recommendations</p>
        @endif
    </div>
</main>

{{-- Full description of one stock: opened by clicking its box (member.partials.pick-card). Outside <main>,
     so the live admin updates that refresh the boxes don't close it. --}}
<dialog class="pk-dialog" id="pk-dialog" aria-labelledby="pk-dialog-title">
    <div class="pk-dialog-head">
        <div>
            <h2 id="pk-dialog-title"></h2>
            <div class="pk-time" id="pk-dialog-time"></div>
        </div>
        <button type="button" class="pk-dialog-close" id="pk-dialog-close" aria-label="Close"><i class="bi bi-x-lg"></i></button>
    </div>
    <div class="pk-dialog-scroll">
        <div class="pk-dialog-levels" id="pk-dialog-levels"></div>
        <h3 class="pk-dialog-label">Description</h3>
        <div class="pk-rich" id="pk-dialog-body"></div>
        <p class="pk-disc"><b><i class="bi bi-exclamation-triangle-fill"></i> Disclaimer:</b> This is for educational purposes only, not a buy, sell or hold recommendation. Read the full <a href="{{ route('disclaimer') }}" target="_blank" rel="noopener">disclaimer</a>.</p>
    </div>
</dialog>
@endsection

@section('extra-js')
<script>
// A stock's box with a description opens it in full. Listening on the document: the boxes are replaced
// when the admin saves something (live updates), so listeners on the boxes themselves would be lost.
(function () {
    const dialog = document.getElementById('pk-dialog');
    if (!dialog || !dialog.showModal) return;
    const $ = id => document.getElementById(id);

    function open(card) {
        $('pk-dialog-title').textContent = card.querySelector('h3').textContent;
        const time = card.querySelector('.pk-time');
        $('pk-dialog-time').innerHTML = time ? time.innerHTML : '';
        const achieved = card.querySelector('.pk-ach');
        $('pk-dialog-levels').innerHTML = card.querySelector('.pk-levels').innerHTML
            + (achieved ? '<div class="pk-a">' + achieved.innerHTML + '</div>' : '');
        $('pk-dialog-body').innerHTML = card.querySelector('.pk-desc-src').innerHTML;
        dialog.showModal();
        dialog.querySelector('.pk-dialog-scroll').scrollTop = 0;
    }

    document.addEventListener('click', function (e) {
        const card = e.target.closest('.pk-card[data-pick-open]');
        if (card && !e.target.closest('a')) open(card);          // the disclaimer link still works as a link
    });
    document.addEventListener('keydown', function (e) {
        if ((e.key === 'Enter' || e.key === ' ') && e.target.matches && e.target.matches('.pk-card[data-pick-open]')) {
            e.preventDefault();
            open(e.target);
        }
    });
    $('pk-dialog-close').addEventListener('click', () => dialog.close());
    dialog.addEventListener('click', e => { if (e.target === dialog) dialog.close(); }); // click on the dark area around it
})();
</script>
<script>
// "Previous" button: show / hide the 7 earlier days
(function () {
    const btn = document.getElementById('pk-prev-btn');
    const box = document.getElementById('pk-previous');
    if (!btn || !box) return;
    const label = btn.querySelector('span'), text = label.textContent;
    btn.addEventListener('click', function () {
        const open = box.hidden;
        box.hidden = !open;
        btn.setAttribute('aria-expanded', String(open));
        btn.classList.toggle('open', open);
        label.textContent = open ? 'Hide previous day data' : text;
    });
})();
</script>
@endsection

@section('extra-css')
@include('partials.subscription-styles')
@include('partials.member-styles')
@endsection
