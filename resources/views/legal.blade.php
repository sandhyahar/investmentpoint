{{-- Disclaimer, Privacy Policy, Terms and Refund Policy: text from Admin -> Legal Pages (LegalPage) --}}
@extends('layouts.app')

@section('title', $page->title . ' | Investment Point')
@section('meta_description', $page->info()['description'])

@section('content')
<main class="tc-page">
    {{-- HERO --}}
    <section class="tc-hero">
        <div class="container">
            <div class="tc-crumbs">
                <a href="{{ route('home') }}">Home</a><span>›</span>{{ $page->title }}
            </div>
            <span class="tc-kicker">Legal</span>
            <h1>{{ $page->title }}</h1>
            @if($page->subtitle)<p>{{ $page->subtitle }}</p>@endif
            @if($meta = $page->metaItems())
                <div class="tc-meta">
                    @foreach($meta as [$label, $value])
                        <span>@if($label){{ $label }}: <b>{{ $value }}</b>@else<b>{{ $value }}</b>@endif</span>
                    @endforeach
                </div>
            @endif
        </div>
    </section>

    <section class="container tc-body">
        {{-- SIDEBAR: contents, built from the section headings --}}
        <aside class="tc-side">
            <div class="tc-card tc-toc">
                <h3>Contents</h3>
                @foreach($toc as $item)
                    @if($item['type'] === 'group')
                        <b>{{ $item['text'] }}</b>
                    @else
                        <a href="#{{ $item['id'] }}">@if($item['num'] !== '')<span class="tc-num">{{ $item['num'] }}</span>@endif {{ $item['text'] }}</a>
                    @endif
                @endforeach
                @if($page->slug === 'disclaimer')
                    <a href="#ask"><span class="tc-num">?</span> Ask a question</a>
                @endif
            </div>
        </aside>

        <div class="dc-main">
            <article class="tc-doc np-justify">
                {{-- Cleaned when saved in the admin (App\Support\Html::cleanLegal) --}}
                {!! $content !!}
            </article>

            @if($page->slug === 'disclaimer')
                @include('legal.ask')
            @endif
        </div>
    </section>
</main>
@endsection

@section('extra-css')
@include('partials.legal-styles')
@endsection
