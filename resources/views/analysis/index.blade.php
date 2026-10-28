@extends('layouts.app')

@section('title', 'Technical Analysis | Investment Point')
@section('meta_description', 'Technical analysis of Indian stocks by Investment Point: support and resistance levels, chart trends and momentum for NSE and BSE shares.')

@section('content')
<main class="np-page">
    <div class="np-wrap">
        <section>
            <div class="np-crumbs" aria-label="Breadcrumb">
                <a href="{{ route('home') }}">Home</a><i>›</i>Technical Analysis
            </div>

            <div class="np-card-box np-listhead">
                <h1>
                    @if($search !== '')
                        Search results for “{{ $search }}”
                    @else
                        Technical Analysis
                    @endif
                </h1>
            </div>

            @if($analyses->isEmpty())
                <div class="np-card-box np-empty">
                    <p class="np-muted">No analysis found.</p>
                    <a class="btn-custom primary" href="{{ route('analysis.index') }}">View all analysis</a>
                </div>
            @else
                <div class="analysis ta-grid">
                    @foreach($analyses as $analysis)
                        @include('analysis.partials.card', ['analysis' => $analysis])
                    @endforeach
                </div>
                <div class="np-pagination">{{ $analyses->links('pagination::bootstrap-5') }}</div>
            @endif
        </section>

        @include('analysis.partials.sidebar', ['analysis' => null])
    </div>
</main>
@endsection

@section('extra-css')
@include('news.partials.styles')
@include('analysis.partials.styles')
@endsection
