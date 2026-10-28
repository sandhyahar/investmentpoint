@extends('layouts.app')

@section('title', 'About Us | Investment Point')
@section('meta_description', 'About Investment Point: Nikhil Bhatt\'s stock market research, daily market trends, technical analysis and investment insights for Indian investors.')

@section('content')
@php
    // Awards (config/awards.php) with their photo from public/images/awards/<key>.(jpg|jpeg|png|webp), if added
    $awards = collect(config('awards'))->map(function ($award, $key) {
        $file = collect(['jpg', 'jpeg', 'png', 'webp'])->map(fn ($ext) => "images/awards/{$key}.{$ext}")->first(fn ($path) => is_file(public_path($path)));

        return $award + ['image' => $file ? asset($file) : null];
    });
@endphp
<main class="ab-page">
    {{-- HERO --}}
    <section class="ab-hero">
        <div class="container ab-hero-in">
            <div class="ab-hero-text">
                <span class="ab-kicker">About Us</span>
                <h1>Knowledge, not noise.</h1>
                <p>Investment Point simplifies financial information for every investor - from the curious first-timer to the seasoned market participant - with clear, ethical and data-driven content on the Indian markets.</p>
                <span class="ab-rni notranslate" translate="no">📰 RNI No. <b>GUJGUJ/2010/35414</b></span>
            </div>

            <div class="ab-person">
                <img class="ab-avatar" src="{{ asset('images/nikhil-bhatt.png') }}" alt="Nikhil Bhatt">
                <div>
                    <b>Nikhil Bhatt</b>
                    <span>Business Editor &amp; Publisher</span>
                    <small>Founder, Investment Point</small>
                </div>
            </div>
        </div>
    </section>

    {{-- VALUES --}}
    <section class="container ab-values">
        <div class="ab-value">
            <span>🛡️</span>
            <b>Integrity</b>
            <p>Independent analysis over sensationalism, rooted in credible data.</p>
        </div>
        <div class="ab-value">
            <span>📘</span>
            <b>Education</b>
            <p>Newsletters, market commentary and learning content for every reader.</p>
        </div>
        <div class="ab-value">
            <span>💡</span>
            <b>Awareness</b>
            <p>Perspectives that equip readers with knowledge to think critically.</p>
        </div>
    </section>

    {{-- LETTER + SIDEBAR --}}
    <section class="container ab-body">
        <article class="ab-letter">
            <h2>A note from the Editor</h2>
            <p class="ab-byline"><strong>By Nikhil Bhatt</strong> · Business Editor &amp; Publisher</p>

            <p>As the editor and founder of Investment Point, it is my privilege to share our vision and commitment with our readers, followers, and subscribers. Since its inception, Investment Point has stood for integrity, education, and awareness in the financial domain - especially within the dynamic and rapidly evolving Indian markets.</p>

            <p>Our primary goal has always been to simplify financial information and make it accessible to every segment of society - from the curious first-time investor to seasoned market participants. In an age where misinformation can lead to misguided financial decisions, Investment Point remains steadfast in its mission to deliver content that is clear, concise, ethical, and rooted in credible data.</p>

            <p>Our weekly newspaper and associated digital media platforms serve as reliable sources for newsletters, market commentary, and educational content. We emphasize independent analysis over sensationalism, and offer perspectives aimed at equipping our readers with knowledge, not noise.</p>

            <div class="ab-caution">
                <b>⚠️ Invest with care</b>
                <p>I strongly urge all readers and subscribers to approach investment and trading decisions with caution, supported by professional advice and comprehensive due diligence.</p>
                <p>The financial markets carry inherent risks, and no publication - ours included - can guarantee results. Instead, our role is to guide, inform, and spark critical thought.</p>
            </div>

            <p>Before interacting with our platforms or using any of our content, I kindly ask that you read and agree to our Disclaimer, Terms and Conditions, and Privacy Policy available at <a href="https://www.nikhilbhatt.in/" target="_blank" rel="noopener noreferrer">www.nikhilbhatt.in</a>. This ensures transparency in our relationship with our audience.</p>

            <p>Thank you for your trust and continued support.</p>

            <p class="ab-signoff">Warm regards,<br><strong>Nikhil Bhatt</strong><br>Business Editor<br>Investment Point</p>
        </article>

        <aside class="ab-side">
            <div class="ab-card">
                <h3>Registration</h3>
                <p class="ab-reg">Investment Point is an RNI registered Gujarati weekly newspaper.</p>
                <div class="ab-reg-no notranslate" translate="no"><small>RNI No.</small><b>GUJGUJ/2010/35414</b></div>
            </div>

            <div class="ab-card">
                <h3>Recognition</h3>
                <ul class="ab-awards">
                    @foreach($awards as $award)
                        <li>
                            {{-- Photo from public/images/awards/<key>.jpg when added, otherwise the icon --}}
                            @if($award['image'])
                                <a href="{{ $award['image'] }}" target="_blank" rel="noopener"><img class="ab-award-thumb" src="{{ $award['image'] }}" alt="{{ $award['title'] }} - Nikhil Bhatt" loading="lazy"></a>
                            @else
                                <span>{{ $award['icon'] }}</span>
                            @endif
                            <div>
                                <b>{{ $award['title'] }}@if($award['year']) {{ $award['year'] }}@endif</b>
                                @if($line = implode(' · ', array_filter([$award['detail'], $award['by']])))<small>{{ $line }}</small>@endif
                            </div>
                        </li>
                    @endforeach
                </ul>
            </div>

            <div class="ab-card ab-contact" id="contact" style="scroll-margin-top:110px">
                <h3>Get in touch</h3>
                <p>Questions about our content or subscription?</p>
                <a class="ab-phone notranslate" translate="no" href="tel:+919939080808">📞 +91 99390 80808</a>
                <a class="ab-link" href="https://www.nikhilbhatt.in/" target="_blank" rel="noopener noreferrer">🌐 www.nikhilbhatt.in</a>
                <a class="btn-custom gold ab-cta" href="{{ route('subscription') }}">View Subscription →</a>
            </div>
        </aside>
    </section>

</main>
@endsection

@section('extra-css')
{{-- Raw CSS: layouts.app yields extra-css inside its own <style> tag --}}
.ab-page{background:var(--bg);padding-bottom:50px}

/* Hero */
.ab-hero{background:var(--premium-grad);color:#fff;padding:48px 0 70px}
.ab-hero-in{display:flex;align-items:center;justify-content:space-between;gap:30px}
.ab-hero-text{max-width:620px}
.ab-kicker{display:inline-block;background:rgba(255,255,255,.12);color:#6ee7b7;font-size:11px;font-weight:800;letter-spacing:1px;text-transform:uppercase;padding:6px 12px;border-radius:20px}
.ab-hero h1{font-size:clamp(28px,4.2vw,46px);font-weight:900;line-height:1.1;margin:14px 0 12px;color:#fff;letter-spacing:-.5px}
.ab-hero p{font-size:15px;line-height:1.7;color:#c9d7e4;margin:0}
.ab-rni{display:inline-block;margin-top:16px;background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.16);border-radius:30px;padding:7px 14px;font-size:13px;color:#dfe6f5;letter-spacing:.3px}
.ab-rni b{color:#ffd36a;letter-spacing:.8px}
.ab-person{display:flex;align-items:center;gap:16px;background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.16);border-radius:16px;padding:18px 22px;flex-shrink:0}
.ab-avatar{width:78px;height:78px;border-radius:50%;object-fit:cover;object-position:50% 25%;background:#fff;border:3px solid var(--gold);flex-shrink:0}
.ab-person b{display:block;font-size:18px;color:#fff}
.ab-person span{display:block;font-size:13px;color:#ffd36a;font-weight:700}
.ab-person small{display:block;font-size:12px;color:#aebfd0;margin-top:2px}

/* Values */
.ab-values{display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin-top:-38px;position:relative}
.ab-value{background:#fff;border:1px solid var(--line);border-radius:14px;box-shadow:var(--shadow);padding:20px}
.ab-value span{font-size:24px}
.ab-value b{display:block;font-size:16px;color:var(--navy);margin:8px 0 4px}
.ab-value p{font-size:13px;color:var(--muted);margin:0;line-height:1.6}

/* Letter + sidebar */
.ab-body{display:grid;grid-template-columns:minmax(0,1fr) 320px;gap:24px;margin-top:28px;align-items:start}
.ab-letter{background:#fff;border:1px solid var(--line);border-radius:14px;box-shadow:var(--shadow);padding:34px 40px;color:#293f51;font-size:15px;line-height:1.8}
.ab-letter h2{font-size:24px;font-weight:800;color:var(--navy);margin:0 0 4px}
.ab-byline{font-size:13px;color:var(--muted);margin:0 0 20px;padding-bottom:16px;border-bottom:1px solid var(--line)}
.ab-letter p{margin:0 0 16px}
.ab-letter a{color:var(--blue);font-weight:700}
.ab-caution{background:#fff8e6;border-left:4px solid var(--gold);border-radius:10px;padding:16px 18px;margin:6px 0 20px}
.ab-caution b{display:block;color:#8a5a00;margin-bottom:6px}
.ab-caution p{font-size:14px;margin:0 0 8px}
.ab-caution p:last-child{margin:0}
.ab-signoff{margin-top:26px !important;color:var(--navy)}

.ab-side{display:flex;flex-direction:column;gap:16px}
.ab-card{background:#fff;border:1px solid var(--line);border-radius:14px;box-shadow:var(--shadow);padding:20px}
.ab-card h3{font-size:13px;font-weight:800;text-transform:uppercase;letter-spacing:.6px;color:var(--muted);margin:0 0 12px}
.ab-reg{font-size:13px;color:var(--muted);margin:0 0 10px;line-height:1.6}
.ab-reg-no{background:var(--blue2);border-radius:10px;padding:10px 14px}
.ab-reg-no small{display:block;font-size:11px;font-weight:800;text-transform:uppercase;letter-spacing:.5px;color:var(--muted)}
.ab-reg-no b{font-size:16px;color:var(--navy);letter-spacing:.8px}
.ab-awards{list-style:none;margin:0;padding:0}
.ab-awards li{display:flex;gap:12px;align-items:flex-start;padding:10px 0;border-bottom:1px solid var(--line)}
.ab-awards li:last-child{border:0;padding-bottom:0}
.ab-awards li span{font-size:22px;line-height:1}
.ab-awards b{display:block;font-size:14px;color:var(--text)}
.ab-awards small{font-size:12px;color:var(--muted)}
.ab-award-thumb{display:block;width:44px;height:44px;border-radius:8px;object-fit:cover;flex-shrink:0;border:1px solid var(--line)}
.ab-awards li>span{width:26px;text-align:center;flex-shrink:0}

.ab-contact p{font-size:13px;color:var(--muted);margin:0 0 10px}
.ab-phone{display:block;font-size:18px;font-weight:800;color:var(--navy);margin-bottom:6px}
.ab-link{display:block;font-size:13px;font-weight:700;color:var(--blue);margin-bottom:14px}
.ab-cta{display:block;text-align:center}

@media(max-width:900px){
  .ab-hero-in{flex-direction:column;align-items:flex-start}
  .ab-body{grid-template-columns:1fr}
  .ab-side{position:static}
}
@media(max-width:640px){
  .ab-hero{padding:32px 0 60px}
  .ab-values{grid-template-columns:1fr}
  .ab-letter{padding:24px 20px;font-size:14px}
}
@endsection
