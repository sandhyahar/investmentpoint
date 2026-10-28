{{-- "Send a WhatsApp reminder to subscribers" tick box of the Add / Edit forms. Needs $canRemind, $remindedAt --}}
<div class="form-check mb-3">
  {{-- Ticked by default until this date's reminder has gone out; after a form error it keeps what the admin chose --}}
  <input type="checkbox" class="form-check-input" id="notify" name="notify" value="1" @checked(session()->hasOldInput() ? old('notify') : !$remindedAt)>
  <label class="form-check-label" for="notify" style="font-weight:600"><i class="bi bi-whatsapp text-success me-1"></i> Send a WhatsApp reminder to subscribers when I click Save</label>
  <div class="form-text">
    Ticked = every active subscriber gets a WhatsApp message with the date and the names of the stocks you are saving (CMP, support and resistance stay on the member page). When you edit a stock that has an Achieved price, the message says the stock's name and its Achieved price. Untick it to only save.
    @if($remindedAt)<br><b>Already sent for this date at {{ $remindedAt->format('g:i A') }}</b> - tick the box to send it again.@endif
  </div>
  @unless($canRemind)
    <div class="text-warning-emphasis" style="font-size:12px"><i class="bi bi-exclamation-triangle-fill"></i> Reminders can't go out until the approved template name is added in <code>.env</code> (WHATSAPP_PICKS_TEMPLATE, next to the WhatsApp API keys).</div>
  @endunless
</div>
