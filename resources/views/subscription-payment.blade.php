{{-- Subscription payment + receipt. $subscription, $mode (demo|test|live|off), $orderId, $razorpayKey, $error, $plan --}}
@extends('layouts.app')

@section('title', ($subscription->isPaid() ? 'Subscription Confirmed' : 'Complete Payment') . ' | Investment Point')
@section('meta_robots', 'noindex, nofollow')

@section('content')
@php
    $price = '₹' . number_format($subscription->price);
    $day = fn ($d) => \App\Models\SubscriptionRequest::day($d);
    $months = $subscription->months ?: (int) $plan['months'];
    $length = $months % 12 === 0 ? ($months / 12) . ' ' . Str::plural('year', $months / 12) : $months . ' ' . Str::plural('month', $months);
@endphp

<main class="sp-page" data-live="off">
    <div class="sp-shell">
        <div class="sp-head">
            <span class="tag">Investment Point Subscription</span>
            <h1>{{ $subscription->isPaid() ? 'Subscription Confirmed' : 'Complete Your Payment' }}</h1>
            <p>{{ $subscription->isPaid() ? 'Thank you, ' . $subscription->name . '! Your subscription is active.' : 'One last step: pay to start your subscription.' }}</p>
        </div>

        <div class="sp-plan">
            <div class="sp-plan-top">
                <small>{{ $subscription->plan }}</small>
                <div class="sp-price"><strong>{{ $price }}</strong><span>/ {{ $subscription->period }}</span></div>
                <p class="sp-permonth">{{ $subscription->isPaid() ? 'Paid' : ($subscription->period === 'year' ? 'Billed yearly' : 'Billed monthly') }}</p>
            </div>

            <div class="sp-plan-body">
                @if($subscription->isPaid())
                    <div class="sp-done" role="status">
                        <b>✅ Payment successful</b>
                        <p>Your {{ $subscription->plan }} subscription is now active.</p>
                    </div>

                    <div class="sp-period" style="margin-top:16px">
                        <div><small>From</small><b>{{ $day($subscription->starts_at) }}</b></div>
                        <i class="bi bi-arrow-right"></i>
                        <div><small>Expires on</small><b>{{ $day($subscription->expires_at) }}</b></div>
                    </div>

                    <div class="sp-summary">
                        <div><span>Name</span><b>{{ $subscription->name }}</b></div>
                        <div><span>Mobile</span><b class="notranslate" translate="no">{{ $subscription->phone }}</b></div>
                        <div><span>Payment ID</span><b class="notranslate" translate="no">{{ $subscription->razorpay_payment_id }} @if($subscription->payment_mode !== 'live')<span class="sp-badge">{{ strtoupper($subscription->payment_mode) }}</span>@endif</b></div>
                        <div><span>Paid on</span><b>{{ $subscription->paid_at->timezone('Asia/Kolkata')->format('d M Y, h:i A') }}</b></div>
                        <div class="sp-total"><span>Amount paid</span><b>{{ $price }}</b></div>
                    </div>

                    @if($subscription->isByPost())
                    <div class="sp-done">
                        <p class="sp-wa" style="margin:0"><i class="bi bi-mailbox2" style="color:#067a4c"></i> The Weekly Gujarati News Paper will be posted every week to:<br><b style="white-space:pre-line">{{ $subscription->address }}</b><br>A confirmation is also sent to your WhatsApp <b class="notranslate" translate="no">{{ $subscription->whatsapp_number }}</b>.</p>
                    </div>
                    @else
                    <div class="sp-done">
                        <p class="sp-wa" style="margin:0"><i class="bi bi-whatsapp"></i> A confirmation with these details will be sent to your WhatsApp <b class="notranslate" translate="no">{{ $subscription->whatsapp_number }}</b>. {{ $plan['contact_name'] }} will share your {{ $subscription->plan }} there.</p>
                    </div>
                    @endif
                    {{-- Next step: the member page (login = this WhatsApp number + WhatsApp OTP), for plans that include it --}}
                    @if($subscription->givesMemberAccess())
                    <div class="sp-contact" style="margin-top:16px">
                        @if(auth('member')->check())
                            <b>Your member page is ready</b>
                            <a class="sp-cta" style="display:block;margin-top:10px;text-decoration:none;color:#141f55" href="{{ route('member.dashboard') }}"><i class="bi bi-speedometer2"></i> Open my member page →</a>
                        @else
                            <b>Next: see your premium technical analysis</b>
                            <small style="margin-top:4px">Log in with this WhatsApp number. We will send you an OTP on WhatsApp.</small>
                            <a class="sp-cta" style="display:block;margin-top:10px;text-decoration:none;color:#141f55" href="{{ route('member.login') }}"><i class="bi bi-whatsapp"></i> Login with OTP →</a>
                        @endif
                    </div>
                    @else
                    <div class="sp-contact" style="margin-top:16px">
                        <small>Questions about your paper? Contact Us</small>
                        <b>{{ $plan['contact_name'] }}</b>
                        <a class="notranslate" translate="no" href="tel:{{ $plan['contact_phone'] }}">📞 {{ preg_replace('/^\+91(\d{5})(\d{5})$/', '+91 $1 $2', $plan['contact_phone']) }}</a>
                    </div>
                    @endif
                    <a class="sp-back" href="{{ route('home') }}">← Back to Investment Point</a>
                @else
                    <div class="sp-summary">
                        <div><span>Name</span><b>{{ $subscription->name }}</b></div>
                        <div><span>Mobile</span><b class="notranslate" translate="no">{{ $subscription->phone }}</b></div>
                        @if($subscription->email)<div><span>Email</span><b>{{ $subscription->email }}</b></div>@endif
                        <div><span>Plan</span><b>{{ $subscription->plan }} ({{ $length }})</b></div>
                        @if($subscription->address)<div><span>Post to</span><b style="word-break:normal;white-space:pre-line">{{ $subscription->address }}</b></div>@endif
                        <div class="sp-total"><span>Total</span><b>{{ $price }}</b></div>
                    </div>

                    @if($msg = session('payment_error') ?? $error)
                        <div class="sp-error" role="alert"><i class="bi bi-exclamation-triangle-fill"></i> {{ $msg }}</div>
                    @endif

                    @if($mode === 'demo')
                        <div class="sp-demo">
                            <b>Demo mode</b>
                            Razorpay keys are not added yet, so no money is charged. Click below to simulate a successful payment.
                        </div>
                        <form method="POST" action="{{ route('subscription.demo', $subscription) }}">
                            @csrf
                            <button type="submit" class="sp-cta"><i class="bi bi-credit-card-fill"></i> Complete demo payment ({{ $price }}) →</button>
                        </form>
                    @elseif($orderId)
                        @if($mode === 'test')
                            <div class="sp-demo"><b>Razorpay test mode</b> Use Razorpay's test cards or UPI. No money is charged.</div>
                        @endif
                        <button type="button" class="sp-cta" id="rzp-pay"><i class="bi bi-lock-fill"></i> Pay {{ $price }} with Razorpay →</button>
                        <small class="sp-secure"><i class="bi bi-shield-lock-fill"></i> Secure payment by Razorpay: UPI, cards, net banking, wallets</small>
                        <form method="POST" action="{{ route('subscription.verify', $subscription) }}" id="rzp-form" hidden>
                            @csrf
                            <input type="hidden" name="razorpay_payment_id">
                            <input type="hidden" name="razorpay_order_id">
                            <input type="hidden" name="razorpay_signature">
                        </form>
                    @elseif(!$error)
                        <div class="sp-error">Online payment is not available right now. Please contact us to complete your subscription.</div>
                    @endif

                    @if($error)
                        <a class="sp-back" href="{{ route('subscription.pay', $subscription) }}"><i class="bi bi-arrow-clockwise"></i> Try again</a>
                    @endif

                    <div class="sp-contact">
                        <small>Need help? Contact Us</small>
                        <b>{{ $plan['contact_name'] }}</b>
                        <a class="notranslate" translate="no" href="tel:{{ $plan['contact_phone'] }}">📞 {{ preg_replace('/^\+91(\d{5})(\d{5})$/', '+91 $1 $2', $plan['contact_phone']) }}</a>
                    </div>
                @endif
            </div>
        </div>
    </div>
</main>
@endsection

@if(!$subscription->isPaid() && $orderId)
@section('extra-js')
<script src="https://checkout.razorpay.com/v1/checkout.js"></script>
<script>
(function () {
    const btn = document.getElementById('rzp-pay');
    const form = document.getElementById('rzp-form');
    if (!btn || typeof Razorpay === 'undefined') return;

    const rzp = new Razorpay({
        key: @json($razorpayKey),
        amount: {{ $subscription->price * 100 }},
        currency: 'INR',
        name: 'Investment Point',
        description: @json($subscription->plan . ' subscription'),
        order_id: @json($orderId),
        prefill: { name: @json($subscription->name), email: @json($subscription->email ?? ''), contact: @json($subscription->phone) },
        notes: { subscription: @json((string) $subscription->id) },
        theme: { color: '#2b3f9e' },
        // Paid: send the proof to the server, which checks the signature before activating
        handler: function (res) {
            form.razorpay_payment_id.value = res.razorpay_payment_id;
            form.razorpay_order_id.value = res.razorpay_order_id;
            form.razorpay_signature.value = res.razorpay_signature;
            btn.disabled = true;
            btn.textContent = 'Confirming payment…';
            form.submit();
        },
        modal: { ondismiss: function () { btn.disabled = false; } }
    });
    rzp.on('payment.failed', function (res) {
        alert('Payment failed: ' + ((res.error && res.error.description) || 'please try again.'));
    });
    btn.addEventListener('click', function () { btn.disabled = true; rzp.open(); });
})();
</script>
@endsection
@endif

@section('extra-css')
@include('partials.subscription-styles')
@endsection
