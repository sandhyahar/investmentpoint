@if(\App\Support\Html::isHtml($text))
{{-- Written in the rich text editor: show its formatting, sanitised --}}
{{-- np-justify: every article's text justified, as the owner asked (28 Oct 2026); a paragraph aligned by hand keeps it --}}
<div class="np-content np-rich np-justify">{!! \App\Support\Html::clean($text) !!}</div>
@else
{{--
    Older plain text typed in a textarea: rendered as readable paragraphs.
    Blank line = new paragraph. A short line with no ending punctuation,
    followed by more text, is treated as a sub-heading (e.g. "Real stories").
--}}
@php
    $blocks = [];
    foreach (preg_split('/\R{2,}/', trim((string) $text)) as $block) {
        $lines = array_values(array_filter(array_map('trim', preg_split('/\R/', $block)), 'strlen'));
        $buffer = [];
        foreach ($lines as $i => $line) {
            $isHeading = isset($lines[$i + 1])
                && mb_strlen($line) <= 60
                && !preg_match('/[.,:;!?।…]$/u', $line);

            if ($isHeading) {
                if ($buffer) {
                    $blocks[] = ['p', $buffer];
                    $buffer = [];
                }
                $blocks[] = ['h3', $line];
            } else {
                $buffer[] = $line;
            }
        }
        if ($buffer) {
            $blocks[] = ['p', $buffer];
        }
    }
@endphp
<div class="np-content np-justify">
    @foreach($blocks as [$type, $value])
        @if($type === 'h3')
            <h3>{{ $value }}</h3>
        @else
            <p>{!! implode('<br>', array_map('e', $value)) !!}</p>
        @endif
    @endforeach
</div>
@endif
