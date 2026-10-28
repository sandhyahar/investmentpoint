{{-- $member: User or null, $period: the SubscriptionRequest the dashboard dates come from, or null --}}
@php($ist = fn ($d) => $d?->copy()->timezone('Asia/Kolkata')->format('Y-m-d'))
<h6 class="mb-1" style="font-weight:700">Member</h6>
<p class="text-muted mb-3" style="font-size:12px">Members log in with this WhatsApp number and a one-time password (OTP) sent on WhatsApp. No username or password needed.</p>
<div class="row">
  <div class="col-md-6 mb-3">
    <label class="form-label" for="phone">WhatsApp number *</label>
    <input type="tel" class="form-control @error('phone') is-invalid @enderror" id="phone" name="phone" value="{{ old('phone', isset($member) ? substr((string) $member->phone, -10) : '') }}" maxlength="16" required placeholder="10-digit WhatsApp number">
    @error('phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
  </div>
  <div class="col-md-6 mb-3">
    <label class="form-label" for="name">Full name *</label>
    <input type="text" class="form-control @error('name') is-invalid @enderror" id="name" name="name" value="{{ old('name', $member->name ?? '') }}" maxlength="100" required>
    @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
  </div>
  <div class="col-md-6 mb-3">
    <label class="form-label" for="email">Email <span class="text-muted fw-normal">(optional)</span></label>
    <input type="email" class="form-control @error('email') is-invalid @enderror" id="email" name="email" value="{{ old('email', $member->email ?? '') }}" maxlength="150">
    @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
  </div>
</div>

<hr>
<h6 class="mb-1" style="font-weight:700">Subscription period</h6>
<p class="text-muted mb-3" style="font-size:12px">Shown on the member's dashboard. The member can see the prices from the "Subscribed on" date until the end of the "Expires on" date.</p>
<div class="row">
  <div class="col-md-4 mb-3">
    <label class="form-label" for="starts_on">Subscribed on</label>
    <input type="date" class="form-control @error('starts_on') is-invalid @enderror" id="starts_on" name="starts_on" value="{{ old('starts_on', $ist($period?->starts_at) ?? ($member ? '' : now('Asia/Kolkata')->format('Y-m-d'))) }}">
    @error('starts_on')<div class="invalid-feedback">{{ $message }}</div>@enderror
  </div>
  <div class="col-md-4 mb-3">
    <label class="form-label" for="expires_on">Expires on</label>
    <input type="date" class="form-control @error('expires_on') is-invalid @enderror" id="expires_on" name="expires_on" value="{{ old('expires_on', $ist($period?->expires_at) ?? ($member ? '' : now('Asia/Kolkata')->addMonthNoOverflow()->format('Y-m-d'))) }}">
    @error('expires_on')<div class="invalid-feedback">{{ $message }}</div>@enderror
  </div>
  @if(!$period)
  <div class="col-md-4 mb-3">
    <label class="form-label" for="amount">Amount received (₹)</label>
    <input type="number" class="form-control @error('amount') is-invalid @enderror" id="amount" name="amount" value="{{ old('amount', config('subscription.plan.price')) }}" min="0">
    @error('amount')<div class="invalid-feedback">{{ $message }}</div>@enderror
    <div class="form-text">Saved as a manual payment in Subscriptions.</div>
  </div>
  @else
  <div class="col-md-4 mb-3">
    <label class="form-label">Payment</label>
    <div class="form-control-plaintext" style="font-size:13px">₹{{ number_format($period->price) }} · {{ strtoupper($period->payment_mode ?? '-') }}<br><span class="text-muted">{{ $period->razorpay_payment_id ?? 'Added by admin' }}</span></div>
  </div>
  @endif
</div>
@if($member && !$period)
  <div class="alert alert-light border" style="font-size:12px">This member has no subscription yet. Fill in both dates to give access.</div>
@endif
