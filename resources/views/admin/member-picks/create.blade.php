@extends('admin.layouts.app')

@section('page-title', 'Add Member Picks')

@section('content')
<div class="mb-3">
  <a href="{{ route('admin.member-picks.index') }}" class="btn btn-sm btn-outline-secondary" style="border-radius:8px;font-size:12px"><i class="bi bi-arrow-left me-1"></i> Back to Member Picks</a>
</div>

<div class="form-card" style="max-width:980px">
  <form action="{{ route('admin.member-picks.store') }}" method="POST">
    @csrf
    <div class="mb-3" style="max-width:240px">
      <label class="form-label" for="pick_date">Date *</label>
      <input type="date" class="form-control @error('pick_date') is-invalid @enderror" id="pick_date" name="pick_date" value="{{ old('pick_date', $date) }}" required>
      @error('pick_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <p class="text-muted" style="font-size:12px">Fill in one row per stock (empty rows are skipped). CMP = current market price on that date. Achieved = the level the stock reached: leave it empty now and fill it in later with Edit. Description = the full write-up members read when they click the stock. Prices can be a price like <code>2755</code> or a range like <code>2750-2760</code>; ₹ and commas are added automatically.</p>
    @error('rows')<div class="alert alert-danger py-2" style="font-size:13px">{{ $message }}</div>@enderror

    <div class="table-responsive">
      <table class="table align-middle table-stack" style="min-width:820px">
        <thead>
          <tr style="font-size:12px">
            <th style="width:30px">#</th>
            <th>Stock name</th>
            <th style="width:130px">CMP</th>
            <th style="width:130px">Support</th>
            <th style="width:130px">Resistance</th>
            <th style="width:130px">Achieved</th>
            <th style="width:120px">Description</th>
          </tr>
        </thead>
        <tbody>
          @for($i = 0; $i < $rows; $i++)
          <tr>
            <td class="text-muted">{{ $i + 1 }}</td>
            <td><input type="text" class="form-control form-control-sm" name="rows[{{ $i }}][stock_name]" value="{{ old("rows.$i.stock_name") }}" list="known-stocks" maxlength="120" placeholder="{{ $i === 0 ? 'e.g. Reliance Industries Ltd' : '' }}"></td>
            <td><input type="text" class="form-control form-control-sm" name="rows[{{ $i }}][cmp]" value="{{ old("rows.$i.cmp") }}" maxlength="60"></td>
            <td><input type="text" class="form-control form-control-sm @error("rows.$i.support") is-invalid @enderror" name="rows[{{ $i }}][support]" value="{{ old("rows.$i.support") }}" maxlength="60">@error("rows.$i.support")<div class="invalid-feedback">{{ $message }}</div>@enderror</td>
            <td><input type="text" class="form-control form-control-sm" name="rows[{{ $i }}][resistance]" value="{{ old("rows.$i.resistance") }}" maxlength="60"></td>
            <td><input type="text" class="form-control form-control-sm" name="rows[{{ $i }}][achieved]" value="{{ old("rows.$i.achieved") }}" maxlength="60"></td>
            <td><button type="button" class="btn btn-sm btn-outline-primary text-nowrap" style="border-radius:8px;font-size:12px" data-desc-toggle="{{ $i }}" aria-expanded="{{ old("rows.$i.description") ? 'true' : 'false' }}"><i class="bi bi-card-text me-1"></i> <span>{{ old("rows.$i.description") ? 'Hide' : 'Add' }}</span></button></td>
          </tr>
          {{-- Full description of this stock (rich text): opens with the button; members read it by clicking the stock's box --}}
          <tr data-desc-row="{{ $i }}" @if(!old("rows.$i.description")) hidden @endif>
            <td></td>
            <td colspan="6">
              <label class="form-label" for="desc-{{ $i }}" style="font-size:12px">Description of stock {{ $i + 1 }}</label>
              <textarea class="form-control rich-editor" id="desc-{{ $i }}" name="rows[{{ $i }}][description]" rows="6">{{ old("rows.$i.description") }}</textarea>
              @error("rows.$i.description")<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            </td>
          </tr>
          @endfor
        </tbody>
      </table>
    </div>
    <datalist id="known-stocks">
      @foreach($names as $name)<option value="{{ $name }}"></option>@endforeach
    </datalist>

    @include('admin.member-picks.partials.notify')

    <button type="submit" class="btn btn-primary" style="border-radius:8px;font-weight:600"><i class="bi bi-check-lg me-1"></i> Save Stocks</button>
  </form>
</div>

<script>
// "Description" button of each row: show / hide that stock's editor
document.querySelectorAll('[data-desc-toggle]').forEach(function (button) {
  button.addEventListener('click', function () {
    var row = document.querySelector('[data-desc-row="' + button.dataset.descToggle + '"]');
    row.hidden = !row.hidden;
    button.setAttribute('aria-expanded', String(!row.hidden));
    button.querySelector('span').textContent = row.hidden ? 'Add' : 'Hide';
  });
});
</script>
@endsection
