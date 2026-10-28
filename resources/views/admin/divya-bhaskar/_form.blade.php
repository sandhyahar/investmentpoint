{{-- Shared fields for Add / Edit Divya Bhaskar column. $article is null when adding. --}}
<div class="mb-3">
  <label class="form-label" for="headline">Headline *</label>
  <textarea class="form-control @error('headline') is-invalid @enderror" id="headline" name="headline" rows="3" required placeholder="e.g. ઈન્વેસ્ટમેન્ટ પોઈન્ટ - શેરબજારમાં ...">{{ old('headline', $article?->headline) }}</textarea>
  @error('headline')<div class="invalid-feedback">{{ $message }}</div>@enderror
  <small class="text-muted">Start with "ઈન્વેસ્ટમેન્ટ પોઈન્ટ - " or "માર્કેટ વ્યૂ : " to show it as a label above the headline.</small>
</div>
<div class="mb-3">
  <label class="form-label" for="link">Divya Bhaskar Link *</label>
  <input type="url" class="form-control @error('link') is-invalid @enderror" id="link" name="link" value="{{ old('link', $article?->link) }}" placeholder="https://divya.bhaskar.com/..." required>
  @error('link')<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>
<div class="row">
  <div class="col-md-6 mb-3">
    <label class="form-label" for="published_on">Column Date *</label>
    <input type="date" class="form-control @error('published_on') is-invalid @enderror" id="published_on" name="published_on" value="{{ old('published_on', $article?->published_on?->format('Y-m-d')) }}" required>
    @error('published_on')<div class="invalid-feedback">{{ $message }}</div>@enderror
  </div>
  <div class="col-md-6 mb-3">
    <label class="form-label" for="status">Status *</label>
    <select class="form-select" id="status" name="status">
      <option value="active" @selected(old('status', $article?->status ?? 'active') === 'active')>Active (shown on the website)</option>
      <option value="inactive" @selected(old('status', $article?->status) === 'inactive')>Inactive (hidden)</option>
    </select>
  </div>
</div>
