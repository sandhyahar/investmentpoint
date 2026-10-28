@extends('admin.layouts.app')

@section('page-title', 'Add Market Index')

@section('content')
<div class="mb-3">
  <a href="{{ route('admin.market-indices.index') }}" class="btn btn-sm btn-outline-secondary" style="border-radius:8px;font-size:12px"><i class="bi bi-arrow-left me-1"></i> Back to Indices</a>
</div>

<div class="form-card" style="max-width:600px">
  <form action="{{ route('admin.market-indices.store') }}" method="POST">
    @csrf
    <div class="mb-3">
      <label class="form-label" for="name">Stock / Index Name *</label>
      <input type="text" class="form-control @error('name') is-invalid @enderror" id="name" name="name" value="{{ old('name', $marketIndex->name ?? '') }}" placeholder="e.g. MRF, Tata Motors, NIFTY 50, SENSEX" required>
      @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
      <div class="form-text">This name is shown on the site. The live price is fetched automatically.</div>
    </div>
    <div class="mb-3">
      <label class="form-label" for="symbol">Symbol <span class="text-muted fw-normal">(optional)</span></label>
      <input type="text" class="form-control @error('symbol') is-invalid @enderror" id="symbol" name="symbol" value="{{ old('symbol', $marketIndex->symbol ?? '') }}" placeholder="Leave empty to find it from the name">
      @error('symbol')<div class="invalid-feedback">{{ $message }}</div>@enderror
      <div class="form-text">Yahoo Finance symbol, e.g. <code>MRF.NS</code>, <code>RELIANCE.NS</code>, <code>^NSEI</code>. Only needed if the name isn't found.</div>
    </div>
    <div class="alert alert-light border" style="font-size:12px">
      The first {{ config('market.ticker_count', 4) }} entries (lowest #) are shown in the live ticker bar at the top of the site.
      Value, change % and direction update automatically.
    </div>
    <button type="submit" class="btn btn-primary" style="border-radius:8px;font-weight:600"><i class="bi bi-check-lg me-1"></i> Create Index</button>
  </form>
</div>
@endsection
