{{-- Red "Disclaimer" box under articles (news, business news, technical analysis, Expert's View, blogs). Styles: news.partials.styles
     $blog: true on the blog read page - adds the writer's "personal views" note before the market risk line --}}
<div class="np-disclaimer" role="note">
    <h2><i class="bi bi-exclamation-triangle-fill"></i> Disclaimer :</h2>
    @if(!empty($blog))
    <p>The views expressed in this article are based solely on my personal observations and experience. Neither Investment Point nor Nikhil Bhatt has any involvement in its preparation or content.</p>
    @endif
    <p>Investments in the securities market are subject to market risks. All content and Index / securities mentioned are strictly for educational and journalistic purposes and do not constitute personalized buy, sell, or hold investment recommendations, Please read all related documents and full disclosures at <a href="https://www.nikhilbhatt.in" target="_blank" rel="noopener noreferrer">nikhilbhatt.in</a> before investing.</p>
</div>
