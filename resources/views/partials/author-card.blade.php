{{--
    Writer card at the top of blogs and Expert's View articles that carry the writer's photo + details:
    photo, label, name, roles, optional contact chips, then date / views, and a Share button.
    Styles: partials.author-card-styles.
    $photo, $name                    required
    $isLogo                          photo is the site logo (shown whole, not cropped)
    $label                           small pill above the name (default "Written by")
    $roles    [['text' => , 'sebi' => bool], ...]
    $contacts [['type' => phone|email|web, 'href' => , 'text' => ], ...]
    $date (Carbon), $views (int)     meta row, each optional
    $reactions ['url' =>, 'likes' =>, 'dislikes' =>, 'mine' => 'like'|'dislike'|null]  like / dislike buttons (optional)
    $shareUrl, $shareTitle           Share button (top-right)
--}}
@php
    $roles = $roles ?? [];
    $contacts = $contacts ?? [];
@endphp
<header class="ac-card">
    <img @class(['ac-photo', 'is-logo' => $isLogo ?? false]) src="{{ $photo }}" alt="{{ $name }}">
    <div class="ac-info">
        <span class="ac-label">{{ $label ?? 'Written by' }}</span>
        <h2 class="ac-name">{{ $name }}</h2>
        @foreach($roles as $role)
            <p @class(['ac-role', 'is-sebi' => $role['sebi'] ?? false])>@if($role['sebi'] ?? false)<i class="bi bi-patch-check-fill"></i> @endif{{ $role['text'] }}</p>
        @endforeach
        @if($contacts)
            <div class="ac-contacts notranslate" translate="no">
                @foreach($contacts as $contact)
                    <a href="{{ $contact['href'] }}" @if($contact['type'] === 'web') target="_blank" rel="noopener nofollow" @endif>
                        <i class="bi {{ ['phone' => 'bi-telephone-fill', 'email' => 'bi-envelope-fill', 'web' => 'bi-globe2'][$contact['type']] }}"></i> {{ $contact['text'] }}
                    </a>
                @endforeach
            </div>
        @endif
        <div class="ac-meta">
            @if(!empty($date))<span><i class="bi bi-calendar3"></i> {{ $date->timezone('Asia/Kolkata')->format('d M Y') }}</span>@endif
            @isset($views)<span><i class="bi bi-eye"></i> {{ number_format($views) }} {{ Str::plural('view', $views) }}</span>@endisset
            @isset($reactions)
                {{-- Like / dislike (blogs): handled by the script in partials.author-card-styles --}}
                <span class="ac-react" data-react-url="{{ $reactions['url'] }}">
                    @foreach(['like' => ['bi-hand-thumbs-up', 'Like'], 'dislike' => ['bi-hand-thumbs-down', 'Dislike']] as $type => [$icon, $text])
                        <button type="button" @class(['ac-react-btn', 'is-' . $type, 'active' => $reactions['mine'] === $type]) data-reaction="{{ $type }}"
                                aria-pressed="{{ $reactions['mine'] === $type ? 'true' : 'false' }}" title="{{ $text }}" aria-label="{{ $text }}">
                            <i class="bi {{ $icon }}{{ $reactions['mine'] === $type ? '-fill' : '' }}"></i><b>{{ number_format($reactions[$type . 's']) }}</b>
                        </button>
                    @endforeach
                </span>
            @endisset
        </div>
    </div>
    @if(!empty($shareUrl))
        {{-- 'label' set here: this card's own $label (e.g. "સંકલન") would otherwise become the button text --}}
        @include('partials.share-button', ['url' => $shareUrl, 'title' => $shareTitle ?? $name, 'class' => 'ac-share', 'label' => 'Share'])
    @endif
</header>
