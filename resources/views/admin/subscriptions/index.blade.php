{{-- Admin -> Subscriptions. $subscriptions, $search, $status, $payment, $paymentMode (demo|test|live|off), $whatsappReady --}}
@extends('admin.layouts.app')

@section('page-title', 'Subscriptions')

@section('content')
@php $day = fn ($d) => \App\Models\SubscriptionRequest::day($d); @endphp

{{-- Payment / WhatsApp setup status --}}
<div class="d-flex gap-2 flex-wrap mb-3" style="font-size:12px">
  @if($paymentMode === 'live')
    <span class="badge-active"><i class="bi bi-credit-card"></i> Razorpay: LIVE payments</span>
  @elseif($paymentMode === 'test')
    <span class="badge-pending"><i class="bi bi-credit-card"></i> Razorpay: TEST mode (no money charged)</span>
  @elseif($paymentMode === 'demo')
    <span class="badge-pending" title="Add RAZORPAY_KEY and RAZORPAY_SECRET in .env to take real payments"><i class="bi bi-credit-card"></i> Payments: DEMO (Razorpay keys not added)</span>
  @else
    <span class="badge-inactive"><i class="bi bi-credit-card"></i> Payments: OFF (add Razorpay keys)</span>
  @endif
  @if($whatsappReady)
    <span class="badge-active"><i class="bi bi-whatsapp"></i> WhatsApp: automatic</span>
  @else
    <span class="badge-pending" title="Add WHATSAPP_TOKEN and WHATSAPP_PHONE_NUMBER_ID in .env to send automatically"><i class="bi bi-whatsapp"></i> WhatsApp: manual (API not set up)</span>
  @endif
</div>

<div class="d-flex justify-content-between align-items-center gap-2 flex-wrap mb-3">
  <p class="text-muted mb-0" style="font-size:13px">Total: {{ number_format($subscriptions->total()) }}</p>
  <form method="GET" action="{{ route('admin.subscriptions.index') }}" class="d-flex gap-2 flex-wrap">
    <select name="payment" class="form-select form-select-sm" style="border-radius:8px;width:auto" onchange="this.form.submit()" aria-label="Payment">
      <option value="">All payments</option>
      @foreach(\App\Models\SubscriptionRequest::PAYMENT_FILTERS as $key => $label)<option value="{{ $key }}" @selected($payment === $key)>{{ $label }}</option>@endforeach
    </select>
    <select name="status" class="form-select form-select-sm" style="border-radius:8px;width:auto" onchange="this.form.submit()" aria-label="Status">
      <option value="">All statuses</option>
      @foreach(\App\Models\SubscriptionRequest::STATUSES as $key => $label)<option value="{{ $key }}" @selected($status === $key)>{{ $label }}</option>@endforeach
    </select>
    <input type="search" name="q" value="{{ $search }}" class="form-control form-control-sm" placeholder="Name, mobile, email or payment ID" style="border-radius:8px;min-width:240px">
    <button class="btn btn-sm btn-outline-secondary" style="border-radius:8px"><i class="bi bi-search"></i></button>
  </form>
</div>

<form id="bulk-delete-form" action="{{ route('admin.subscriptions.bulk-delete') }}" method="POST" data-bulk-form class="mb-3">
  @csrf
  <button type="submit" class="btn btn-sm btn-outline-danger" data-bulk-submit disabled><i class="bi bi-trash me-1"></i> Delete Selected</button>
</form>

<div class="table-card" style="overflow-x:auto">
  <table class="table">
    <thead>
      <tr>
        <th><input type="checkbox" data-select-all aria-label="Select all"></th>
        <th>Subscriber</th>
        <th>Plan / Payment</th>
        <th>Subscription period</th>
        <th>WhatsApp</th>
        <th>Agreement</th>
        <th>Status</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
      @forelse($subscriptions as $s)
      <tr>
        <td><input type="checkbox" name="ids[]" value="{{ $s->id }}" form="bulk-delete-form" data-select-item></td>
        <td style="font-size:13px">
          <strong>{{ $s->name }}</strong><br>
          <a href="tel:{{ $s->phone }}" style="color:#2b3f9e"><i class="bi bi-telephone"></i> {{ $s->phone }}</a>
          @if($s->whatsapp_number !== $s->phone)
            <br><a href="https://wa.me/{{ ltrim($s->whatsapp_number, '+') }}" target="_blank" rel="noopener" style="color:#128c7e" title="WhatsApp (login)"><i class="bi bi-whatsapp"></i> {{ $s->whatsapp_number }}</a>
          @else
            <a href="https://wa.me/{{ ltrim($s->phone, '+') }}" target="_blank" rel="noopener" class="ms-2" style="color:#128c7e" title="WhatsApp (same number, used for login)"><i class="bi bi-whatsapp"></i></a>
          @endif
          @if($s->email)<br><a href="mailto:{{ $s->email }}" style="font-size:12px;color:#66798d">{{ $s->email }}</a>@endif
          @if($s->address)<div style="font-size:12px;color:#7a2e1a;margin-top:4px;white-space:pre-line;max-width:240px" title="Postal address"><i class="bi bi-mailbox2"></i> {{ $s->address }}</div>@endif
        </td>
        <td style="font-size:12px;white-space:nowrap">
          {{ $s->plan }}<br><b>₹{{ number_format($s->price) }}</b> / {{ $s->period }}
          <div style="margin-top:4px">
            @if($s->isPaid())
              <span class="badge-active"><i class="bi bi-check-circle"></i> Paid</span>
              @if($s->payment_mode !== 'live')<span class="badge-pending" style="font-size:10px">{{ strtoupper($s->payment_mode) }}</span>@endif
              <div class="text-muted" style="font-size:11px;margin-top:3px">{{ $s->paid_at->timezone('Asia/Kolkata')->format('d M Y, h:i A') }}</div>
              <div class="text-muted" style="font-size:11px" title="Razorpay payment ID">{{ $s->razorpay_payment_id }}</div>
            @else
              <span class="badge-pending"><i class="bi bi-hourglass-split"></i> Awaiting payment</span>
            @endif
          </div>
        </td>
        <td style="font-size:12px;white-space:nowrap">
          @if($s->isPaid())
            <div><span class="text-muted">From</span> <b>{{ $day($s->starts_at) }}</b></div>
            <div><span class="text-muted">To</span> <b>{{ $day($s->expires_at) }}</b></div>
            @if($s->isActive())
              @php $left = (int) ceil(now()->diffInHours($s->expires_at) / 24); @endphp
              <span class="badge-active" style="display:inline-block;margin-top:4px">Active · {{ $left }} {{ Str::plural('day', $left) }} left</span>
            @else
              <span class="badge-inactive" style="display:inline-block;margin-top:4px">Expired</span>
            @endif
          @else
            <span class="text-muted">Starts after payment</span>
          @endif
        </td>
        <td style="font-size:12px">
          @if($s->isPaid())
            @if($s->whatsapp_status === 'sent')
              <span class="badge-active"><i class="bi bi-whatsapp"></i> Sent</span>
              <div class="text-muted" style="font-size:11px;margin-top:3px">{{ $s->whatsapp_sent_at?->timezone('Asia/Kolkata')->format('d M, h:i A') }}</div>
            @elseif($s->whatsapp_status === 'failed')
              <span class="badge-inactive" title="{{ $s->whatsapp_error }}"><i class="bi bi-exclamation-triangle"></i> Failed</span>
              <div class="text-muted" style="font-size:11px;max-width:160px">{{ Str::limit($s->whatsapp_error, 60) }}</div>
            @else
              <span class="badge-pending">Not sent</span>
            @endif
            <div class="d-flex gap-1 mt-1 flex-wrap">
              {{-- Opens WhatsApp with the confirmation already typed: works without the API --}}
              <a href="https://wa.me/{{ ltrim($s->whatsapp_number, '+') }}?text={{ rawurlencode($s->whatsappMessage()) }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-success" style="border-radius:6px;font-size:11px;padding:2px 8px"><i class="bi bi-whatsapp"></i> Send on WhatsApp</a>
              @if($whatsappReady)
                <form method="POST" action="{{ route('admin.subscriptions.whatsapp', $s) }}">
                  @csrf
                  <button class="btn btn-sm btn-outline-secondary" style="border-radius:6px;font-size:11px;padding:2px 8px" title="Send automatically again"><i class="bi bi-arrow-repeat"></i> Resend</button>
                </form>
              @endif
            </div>
          @else
            <span class="text-muted">After payment</span>
          @endif
        </td>
        <td style="font-size:12px;min-width:150px">
          {{-- Full agreement record (what was agreed to, browser) shows when pointing at it --}}
          <span class="badge-active" title="Agreed to: {{ $s->agreed_to }}"><i class="bi bi-check2-square"></i> Agreed</span>
          <div style="margin-top:4px;color:#10253f;white-space:nowrap">{{ $s->agreed_at->timezone('Asia/Kolkata')->format('d M Y, h:i:s A') }}</div>
          <div class="text-muted" style="font-size:11px" title="{{ $s->user_agent }}">IP {{ $s->ip ?: '-' }}</div>
          <details class="sub-agree"><summary>Details</summary>
            <div>To: {{ $s->agreed_to }}</div>
            <div>{{ $s->user_agent }}</div>
          </details>
        </td>
        <td>
          <form method="POST" action="{{ route('admin.subscriptions.update', $s) }}">
            @csrf @method('PUT')
            <select name="status" class="form-select form-select-sm" style="border-radius:8px;min-width:108px" onchange="this.form.submit()" aria-label="Status">
              @foreach(\App\Models\SubscriptionRequest::STATUSES as $key => $label)<option value="{{ $key }}" @selected($s->status === $key)>{{ $label }}</option>@endforeach
            </select>
          </form>
        </td>
        <td>
          <form action="{{ route('admin.subscriptions.destroy', $s) }}" method="POST" class="d-inline" onsubmit="return confirm('Delete this subscription? The agreement and payment record will be deleted too.')">
            @csrf @method('DELETE')
            <button class="btn btn-sm btn-outline-danger" style="border-radius:6px;font-size:11px" title="Delete"><i class="bi bi-trash"></i></button>
          </form>
        </td>
      </tr>
      @empty
      <tr><td colspan="8" class="text-center text-muted py-4">No subscriptions yet.</td></tr>
      @endforelse
    </tbody>
  </table>
</div>

<div class="mt-3">{{ $subscriptions->onEachSide(1)->links() }}</div>
@endsection

@section('extra-css')
<style>
.sub-agree{margin-top:3px;font-size:11px;color:var(--muted)}
.sub-agree summary{cursor:pointer;color:var(--blue);font-weight:600;width:max-content}
.sub-agree div{margin-top:3px;max-width:260px;overflow-wrap:anywhere}
</style>
@endsection
