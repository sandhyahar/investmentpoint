{{-- "Disclaimer" + "About Me" block shown under the Media Articles and Digital News Paper pages.
     $registration: who granted the registration named in the disclaimer (default SEBI) --}}
<section class="ma-about">
    <div class="ma-about-card">
        <h3><i class="bi bi-person-circle"></i> About Me</h3>
        <div class="ma-me">
            <img src="{{ config('profile.photo') }}" alt="Nikhil Bhatt" loading="lazy">
            <div>
                <b>Investment Point</b>
                <small>Nikhil Bhatt · Founder, Editor &amp; Owner</small>
            </div>
        </div>
        <p>{{ config('profile.introduction') }}</p>
        <a class="ma-profile-btn" href="{{ route('profile') }}">View my complete profile <i class="bi bi-arrow-right"></i></a>
    </div>

    <div class="ma-about-card ma-disc">
        <h3><i class="bi bi-exclamation-triangle-fill"></i> Disclaimer</h3>
        <p>Before refer our articles, video, newsletters, blog and website or any digital or print media please accepts &amp; agree Disclaimer, Terms &amp; condition, Privacy Policy &amp; agreement mention on <a href="https://www.nikhilbhatt.in" target="_blank" rel="noopener noreferrer">www.nikhilbhatt.in</a>. No content on our blog and website and digital or print media should be construed to be investment or trading advice. All information is a point of view, and is for educational and informational use only. We accepts no liability for any interpretation of articles or comments on this blog and website being used for actual investments &amp; Trading.</p>
        <p>Registration granted by {{ $registration ?? 'SEBI' }} certification does not provide any guarantee performance of the intermediary or provides any assurance of returns to investors. The investor is requested to take into consideration all the risk factors including their financial condition, suitability to risk return profile and the like and take professional advice before investing. Please do thorough research or consult your financial manager/advisor before making any investment and trading.</p>
        <p>Any views and/or opinions stated are personal. Past Performance is not an Indicator of future returns. The securities quoted are for illustration only and are not recommendatory. Investment in securities market are subject to market risks. Read Disclaimer and related all the documents carefully before investing, mentioned on <a href="https://www.nikhilbhatt.in" target="_blank" rel="noopener noreferrer">www.nikhilbhatt.in</a></p>
    </div>
</section>
