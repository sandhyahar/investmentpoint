{{-- Schedule Date / Schedule Time (App\Support\Schedule): readers, and Blogger, get it from this date and time
     (India time) on; both empty = as soon as it is saved. $item: the edition / article being edited, or null. --}}
@php $when = $item?->publish_at?->copy()->timezone('Asia/Kolkata'); @endphp
<div class="row">
  <div class="col-md-4 mb-3">
    <label class="form-label" for="publish_date"><i class="bi bi-calendar-event me-1"></i> Schedule Date</label>
    <input type="date" class="form-control @error('publish_date') is-invalid @enderror" id="publish_date" name="publish_date" value="{{ old('publish_date', $when?->format('Y-m-d')) }}">
    @error('publish_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
  </div>
  <div class="col-md-4 mb-3">
    <label class="form-label" for="publish_time"><i class="bi bi-clock me-1"></i> Schedule Time</label>
    <input type="time" class="form-control @error('publish_time') is-invalid @enderror" id="publish_time" name="publish_time" value="{{ old('publish_time', $when?->format('H:i')) }}">
    @error('publish_time')<div class="invalid-feedback">{{ $message }}</div>@enderror
  </div>
  <div class="col-md-4 mb-3 d-flex flex-column justify-content-end">
    <button type="button" class="btn btn-outline-secondary" style="border-radius:8px" onclick="document.getElementById('publish_date').value='';document.getElementById('publish_time').value=''"><i class="bi bi-x-lg me-1"></i> Clear schedule</button>
  </div>
  <div class="col-12" style="margin-top:-8px;margin-bottom:12px">
    <small class="text-muted">India time. Leave empty to publish as soon as you save; with a date (and time) it appears on the website{{ app(\App\Services\BloggerPublisher::class)->isConnected() ? ' and on Blogger' : '' }} automatically from then on. A date without a time = 12:00 AM.</small>
    @if($item?->isScheduled())
      <div class="mt-1" style="font-size:12.5px;font-weight:600;color:#8a5a00"><i class="bi bi-clock-history me-1"></i>Scheduled: goes live {{ $when->format('d M Y, h:i A') }}</div>
    @endif
  </div>
</div>
