@extends('admin.layouts.app')

@section('page-title', 'Edit Member Pick')

@section('content')
<div class="mb-3">
  <a href="{{ route('admin.member-picks.index', ['date' => $pick->pick_date->format('Y-m-d')]) }}" class="btn btn-sm btn-outline-secondary" style="border-radius:8px;font-size:12px"><i class="bi bi-arrow-left me-1"></i> Back to Member Picks</a>
</div>

<div class="form-card" style="max-width:720px">
  <form action="{{ route('admin.member-picks.update', $pick) }}" method="POST">
    @csrf @method('PUT')
    <div class="row">
      <div class="col-md-4 mb-3">
        <label class="form-label" for="pick_date">Date *</label>
        <input type="date" class="form-control @error('pick_date') is-invalid @enderror" id="pick_date" name="pick_date" value="{{ old('pick_date', $pick->pick_date->format('Y-m-d')) }}" required>
        @error('pick_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
      </div>
      <div class="col-md-8 mb-3">
        <label class="form-label" for="stock_name">Stock name *</label>
        <input type="text" class="form-control @error('stock_name') is-invalid @enderror" id="stock_name" name="stock_name" value="{{ old('stock_name', $pick->stock_name) }}" list="known-stocks" maxlength="120" required>
        @error('stock_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
      </div>
    </div>
    <div class="row">
      <div class="col-md-3 mb-3">
        <label class="form-label" for="cmp">CMP <small class="text-muted">(current market price)</small></label>
        <input type="text" class="form-control" id="cmp" name="cmp" value="{{ old('cmp', $pick->cmp) }}" maxlength="60">
      </div>
      <div class="col-md-3 mb-3">
        <label class="form-label" for="support">Support</label>
        <input type="text" class="form-control @error('support') is-invalid @enderror" id="support" name="support" value="{{ old('support', $pick->support) }}" maxlength="60">
        @error('support')<div class="invalid-feedback">{{ $message }}</div>@enderror
      </div>
      <div class="col-md-3 mb-3">
        <label class="form-label" for="resistance">Resistance</label>
        <input type="text" class="form-control" id="resistance" name="resistance" value="{{ old('resistance', $pick->resistance) }}" maxlength="60">
      </div>
      <div class="col-md-3 mb-3">
        <label class="form-label" for="achieved">Achieved</label>
        <input type="text" class="form-control" id="achieved" name="achieved" value="{{ old('achieved', $pick->achieved) }}" maxlength="60">
      </div>
    </div>
    <div class="mb-3">
      <label class="form-label" for="description">Description <small class="text-muted">(members read it by clicking the stock's box)</small></label>
      <textarea class="form-control rich-editor @error('description') is-invalid @enderror" id="description" name="description" rows="10">{{ old('description', $pick->description) }}</textarea>
      @error('description')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    </div>
    <div class="mb-3" style="max-width:160px">
      <label class="form-label" for="sort_order">Order</label>
      <input type="number" class="form-control" id="sort_order" name="sort_order" value="{{ old('sort_order', $pick->sort_order) }}" min="0" max="9999">
      <div class="form-text">Lowest shows first.</div>
    </div>
    <datalist id="known-stocks">
      @foreach($names as $name)<option value="{{ $name }}"></option>@endforeach
    </datalist>

    @include('admin.member-picks.partials.notify')
    {{-- A new or changed Achieved price is news for the subscribers: the reminder box ticks itself (the admin can untick it) --}}
    <script>
    (function () {
      const achieved = document.getElementById('achieved'), notify = document.getElementById('notify');
      if (!achieved || !notify) return;
      const saved = @json((string) $pick->achieved);
      achieved.addEventListener('input', function () {
        if (achieved.value.trim() !== '' && achieved.value.trim() !== saved) notify.checked = true;
      });
    })();
    </script>

    <button type="submit" class="btn btn-primary" style="border-radius:8px;font-weight:600"><i class="bi bi-check-lg me-1"></i> Update Stock</button>
  </form>
</div>
@endsection
