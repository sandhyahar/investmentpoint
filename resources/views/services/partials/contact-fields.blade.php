{{-- Name, mobile, age and the address with City (type to search) -> its PIN code from India Post's list (the state comes
     with the city, in a hidden field), as for the Weekly News Paper's postal address (subscription page). $bag: the form's error bag
     (product | adviser); old values are only put back into the form that was sent. The script is in services.insurance. --}}
@php
  $errs = $errors->getBag($bag);
  $old = fn ($field) => old('kind') === $bag ? old($field) : '';
  $err = fn ($field) => $errs->has($field) ? '<div class="ins-error">' . e($errs->first($field)) . '</div>' : '';
@endphp
<div class="ins-fields">
  <div>
    <label for="{{ $bag }}_name">Your name *</label>
    <input type="text" id="{{ $bag }}_name" name="name" value="{{ $old('name') }}" maxlength="100" autocomplete="name" required @class(['is-invalid' => $errs->has('name')])>
    {!! $err('name') !!}
  </div>
  <div class="ins-two">
    <div>
      <label for="{{ $bag }}_contact">Mobile number *</label>
      <input type="tel" id="{{ $bag }}_contact" name="contact" value="{{ $old('contact') }}" maxlength="20" inputmode="tel" autocomplete="tel" placeholder="10-digit mobile number" required @class(['is-invalid' => $errs->has('contact')])>
      {!! $err('contact') !!}
    </div>
    <div>
      <label for="{{ $bag }}_age">Age *</label>
      <input type="number" id="{{ $bag }}_age" name="age" value="{{ $old('age') }}" min="18" max="80" inputmode="numeric" placeholder="Years" required @class(['is-invalid' => $errs->has('age')])>
      {!! $err('age') !!}
    </div>
  </div>
  <div class="ins-wide">
    <label for="{{ $bag }}_address">Address *</label>
    <textarea id="{{ $bag }}_address" name="address" rows="2" maxlength="400" autocomplete="street-address" placeholder="House / flat, street, area" required @class(['is-invalid' => $errs->has('address')])>{{ $old('address') }}</textarea>
    {!! $err('address') !!}
  </div>
  <div class="ins-wide ins-place" data-old-state="{{ $old('state') }}" data-old-city="{{ $old('city') }}" data-old-pincode="{{ $old('pincode') }}">
    <div>
      <label for="{{ $bag }}_city_pick">City *</label>
      <input id="{{ $bag }}_city_pick" type="text" list="ins-city-list" autocomplete="off" maxlength="80" placeholder="Type your city" required data-city-pick @class(['is-invalid' => $errs->has('city') || $errs->has('state')])>
      <input type="hidden" name="city" data-city>
      {!! $err('city') ?: $err('state') !!}
    </div>
    <div>
      <label for="{{ $bag }}_pincode">PIN code *</label>
      <select id="{{ $bag }}_pincode" name="pincode" disabled required data-pincode @class(['is-invalid' => $errs->has('pincode')])><option value="">Select PIN code</option></select>
      {!! $err('pincode') !!}
    </div>
    {{-- Not shown (owner, 27 Oct 2026): filled from the chosen city; it tells apart cities of the same name and checks the PIN code --}}
    <input type="hidden" name="state" data-state>
  </div>
</div>
