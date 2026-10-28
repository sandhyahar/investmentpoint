{{-- Member login: WhatsApp number -> WhatsApp OTP. $paid (just-paid subscription or null), $otpPhone (step 2 when set), $prefill, $mode (whatsapp|demo|off) --}}
@extends('layouts.app')

@section('title', 'Member Login | Investment Point')
@section('meta_robots', 'noindex, nofollow')

@section('content')
@php($digits = config('subscription.otp.length'))
<main class="sp-page" data-live="off">
    <div class="sp-shell">
        <div class="sp-head">
            <span class="tag">Investment Point Premium</span>
            <h1>Member Login</h1>
            <p>Log in with the WhatsApp number you gave when subscribing. We will send you a one-time password (OTP) on WhatsApp.</p>
        </div>

        <div class="sp-plan mb-auth">
            <div class="sp-plan-top"><h2><i class="bi bi-whatsapp"></i> {{ $otpPhone ? 'Enter OTP' : 'Login with WhatsApp' }}</h2></div>
            <div class="sp-plan-body">
                @if(session('success'))<div class="mb-ok" role="status">{{ session('success') }}</div>@endif
                @if($paid)
                    <div class="mb-ok" role="status">
                        <b style="display:block;font-size:15px">✅ Payment successful</b>
                        ₹{{ number_format($paid->price) }} paid · Subscription {{ \App\Models\SubscriptionRequest::day($paid->starts_at) }} to {{ \App\Models\SubscriptionRequest::day($paid->expires_at) }}<br>
                        <small class="notranslate" translate="no">Payment ID: {{ $paid->razorpay_payment_id }}</small>
                    </div>
                @endif

                @if($mode === 'off')
                    <div class="sp-error" role="alert">WhatsApp login is not available right now. Please contact us.</div>
                @elseif(!$otpPhone)
                    {{-- Step 1: WhatsApp number --}}
                    <form class="sp-form" method="POST" action="{{ route('member.otp.send') }}" novalidate>
                        @csrf
                        <label for="mb-phone">WhatsApp number</label>
                        <div class="mb-phone">
                            <span>+91</span>
                            <input id="mb-phone" type="tel" name="phone" value="{{ $prefill }}" maxlength="16" required autocomplete="tel-national" inputmode="numeric" placeholder="10-digit WhatsApp number" autofocus @class(['is-invalid' => $errors->has('phone')])>
                        </div>
                        @error('phone')<small class="sp-err">{{ $message }}</small>@enderror

                        <button type="submit" class="sp-cta"><i class="bi bi-whatsapp"></i> Send OTP on WhatsApp →</button>
                        <small class="sp-secure"><i class="bi bi-shield-lock-fill"></i> Only the WhatsApp number given for your subscription can log in.</small>
                    </form>
                @else
                    {{-- Step 2: the OTP --}}
                    @if(session('otp_sent'))<div class="mb-ok" role="status">{{ session('otp_sent') }}</div>@endif
                    @if($code = session('otp_demo_code'))
                        <div class="sp-demo" role="status">
                            <b>Demo mode</b>
                            WhatsApp is not set up yet, so no message is sent. Your OTP is <b class="notranslate" translate="no" style="display:inline;font-size:16px;letter-spacing:2px">{{ $code }}</b>
                        </div>
                    @endif

                    <form class="sp-form" method="POST" action="{{ route('member.otp.verify') }}" novalidate>
                        @csrf
                        <label for="mb-otp">OTP sent to <span class="notranslate" translate="no" style="color:var(--navy);font-weight:800">{{ \App\Http\Controllers\MemberAuthController::masked($otpPhone) }}</span></label>
                        <input id="mb-otp" class="mb-otp @error('otp') is-invalid @enderror" type="text" name="otp" inputmode="numeric" pattern="[0-9]*" maxlength="{{ $digits }}" required autocomplete="one-time-code" autofocus placeholder="{{ str_repeat('•', $digits) }}">
                        @error('otp')<small class="sp-err">{{ $message }}</small>@enderror

                        <button type="submit" class="sp-cta"><i class="bi bi-box-arrow-in-right"></i> Verify &amp; Login →</button>
                    </form>

                    <div class="mb-otp-actions">
                        <form method="POST" action="{{ route('member.otp.send') }}">
                            @csrf
                            <input type="hidden" name="phone" value="{{ substr($otpPhone, -10) }}">
                            <button type="submit" id="mb-resend" data-wait="{{ app(\App\Services\LoginOtp::class)->waitSeconds($otpPhone) }}">Resend OTP</button>
                        </form>
                        <form method="POST" action="{{ route('member.otp.change') }}">
                            @csrf
                            <button type="submit">Change number</button>
                        </form>
                    </div>
                @endif

                <p class="mb-switch">Not subscribed yet? <a href="{{ route('subscription') }}">Subscribe</a></p>
            </div>
        </div>
    </div>
</main>
@endsection

@section('extra-js')
<script>
// "Resend OTP" stays off for a few seconds after an OTP is sent
(function () {
    const btn = document.getElementById('mb-resend');
    if (!btn) return;
    let wait = parseInt(btn.dataset.wait || '0', 10);
    const tick = function () {
        btn.disabled = wait > 0;
        btn.textContent = wait > 0 ? 'Resend OTP in ' + wait + 's' : 'Resend OTP';
        if (wait-- > 0) setTimeout(tick, 1000);
    };
    tick();
})();
</script>
@endsection

@section('extra-css')
@include('partials.subscription-styles')
@include('partials.member-styles')
@endsection
