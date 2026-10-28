{{-- One stock: name, when it was added / last changed, the achieved level (top right, once filled in), CMP,
     support, resistance and a short disclaimer (educational levels only). With a description from the
     admin, the whole box opens it (the dialog and its script are in member.dashboard). $pick: MemberPick --}}
@php($described = $pick->hasDescription())
<article class="pk-card" @if($described) data-pick-open tabindex="0" role="button" aria-haspopup="dialog" aria-label="{{ $pick->stock_name }}: read the description" @endif>
    <div class="pk-head">
        <h3>{{ $pick->stock_name }}</h3>
        @if($pick->achieved)<span class="pk-ach"><small>Achieved</small> <b class="notranslate" translate="no">{{ $pick->level('achieved') }}</b></span>@endif
    </div>
    @if($pick->updatedLabel())<div class="pk-time"><i class="bi bi-clock"></i> Updated <time datetime="{{ ($pick->updated_at ?? $pick->created_at)->toIso8601String() }}" class="notranslate" translate="no">{{ $pick->updatedLabel() }}</time></div>@endif
    <div class="pk-levels">
        <div class="pk-c"><small>CMP <span>(current market price)</span></small><b class="notranslate" translate="no">{{ $pick->level('cmp') ?? '-' }}</b></div>
        <div class="pk-s"><small>Support</small><b class="notranslate" translate="no">{{ $pick->level('support') ?? '-' }}</b></div>
        <div class="pk-r"><small>Resistance</small><b class="notranslate" translate="no">{{ $pick->level('resistance') ?? '-' }}</b></div>
    </div>
    @if($described)
        <div class="pk-more">Read full description <i class="bi bi-arrow-right"></i></div>
        {{-- Shown in the dialog; sanitised like every rich text on the site --}}
        <div class="pk-desc-src" hidden>{!! \App\Support\Html::clean($pick->description) !!}</div>
    @endif
    <p class="pk-disc"><b><i class="bi bi-exclamation-triangle-fill"></i> Disclaimer:</b> These levels are for educational purposes only, not a buy, sell or hold recommendation. Read the full <a href="{{ route('disclaimer') }}" target="_blank" rel="noopener">disclaimer</a>.</p>
</article>
