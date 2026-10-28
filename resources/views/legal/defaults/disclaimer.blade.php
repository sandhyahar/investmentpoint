{{-- Built-in Disclaimer text, shown until the admin saves their own in Admin -> Legal Pages --}}
@php
    // Each part: [title, blocks]. Block types: p = paragraph, h = numbered sub-heading,
    // tags = short keywords shown as tags, ul = full-sentence bullets, terms = [label, sentence] points, the label shown as a tag.
    $parts = [
        ['About Investment Point', [
            ['p', 'Investment Point is an RNI Registered Gujarati Weekly Business & Financial Newspaper engaged in publishing financial news, corporate developments, investor education articles, business reports, market commentary and educational material relating to financial markets. The publication functions as an independent news and educational platform. Publication of financial content shall not automatically be interpreted as investment advice or research recommendation.'],
        ]],
        ['General Disclaimer', [
            ['h', '1. Informational Purpose'],
            ['p', 'All content published by Investment Point is intended only for'],
            ['tags', ['Financial Awareness', 'Investor Education', 'Business News', 'Market Information', 'Educational Discussion', 'Journalistic Reporting']],
            ['p', 'The content should not be construed as personalized financial advice or an invitation to trade in securities.'],
            ['h', '2. No Investment Advice'],
            ['p', 'Nothing published constitutes'],
            ['tags', ['Investment Advice', 'Investment Recommendation', 'Research Recommendation', 'Stock Tip', 'Portfolio Management Service', 'Investment Advisory Service', 'Wealth Management Service', 'Solicitation', 'Offer to Buy Securities', 'Offer to Sell Securities', 'Offer to Hold Securities']],
            ['h', '3. Editorial Independence'],
            ['p', 'Investment Point maintains complete editorial independence. Publication of'],
            ['tags', ['Company News', 'Technical Analysis', 'Corporate Announcements', 'Financial Statements', 'Industry Reports']],
            ['p', 'does not indicate endorsement of any company, security or financial product.'],
            ['h', '4. Public Information'],
            ['p', 'Unless otherwise stated, reports are prepared using publicly available information including'],
            ['tags', ['Stock Exchange Filings', 'Company Announcements', 'Annual Reports', 'Quarterly Results', 'Investor Presentations', 'Public Shareholding Pattern', 'Historical Price Data', 'Historical Volume Data', 'Government Publications', 'Public Databases']],
            ['p', 'Investment Point does not claim access to unpublished price sensitive information (UPSI).'],
            ['h', '5. Technical Analysis Disclaimer'],
            ['p', 'Technical analysis may include'],
            ['tags', ['Support Levels', 'Resistance Levels', 'Breakout Levels', 'Volume Analysis', 'RSI', 'MACD', 'Moving Averages', 'Fibonacci Levels', 'Candlestick Patterns', 'Chart Patterns', 'Trend Analysis']],
            ['p', 'These represent only technical interpretation based on historical market behaviour and are subject to change without notice.'],
            ['h', '6. No Guarantee'],
            ['p', 'Investment Point does not guarantee'],
            ['tags', ['Accuracy', 'Completeness', 'Timeliness', 'Future Performance', 'Future Returns', 'Target Achievement']],
            ['p', 'Past performance does not guarantee future results.'],
            ['h', '7. Independent Decision'],
            ['p', 'Readers must'],
            ['ul', ['Conduct their own research', 'Verify information independently', 'Evaluate risk profile', 'Consult qualified professionals wherever required']],
            ['p', 'before making financial decisions.'],
            ['h', '8. Limitation of Liability'],
            ['p', 'Investment Point, its Owner, Editor, Publisher, Employees, Contributors and Authors shall not be liable for any direct, indirect, incidental or consequential loss arising from reliance upon published content.'],
        ]],
        ['RNI Registered Newspaper Disclosure', [
            ['p', 'Investment Point is an RNI Registered Weekly Newspaper. The publication is engaged in'],
            ['tags', ['Business Journalism', 'Financial Journalism', 'Investor Awareness', 'Corporate News', 'Educational Publications', 'Market Commentary']],
            ['p', 'Publication of market-related content shall not be interpreted as investment recommendation merely because it discusses listed companies or securities.'],
        ]],
        ['SEBI Related Disclosure', [
            ['p', 'Investment Point itself is a newspaper/media publication. Unless specifically disclosed, Investment Point is not acting as'],
            ['tags', ['Research Analyst', 'Investment Adviser', 'Portfolio Manager', 'Stock Broker', 'Merchant Banker', 'Mutual Fund Distributor']],
            ['p', 'under applicable SEBI regulations. Where content is authored by a person registered with SEBI, such status will be disclosed separately in accordance with applicable requirements.'],
        ]],
        ['Disclosure (SEBI Research Analyst / Investment Adviser Guidelines)', [
            ['p', 'Unless otherwise disclosed in the article itself, the following applies to every technical analysis article published by Investment Point:'],
            ['terms', [
                ['Nature of the article', 'An educational technical study of the stock or index discussed, based only on historical price behaviour. It is not a buy, sell or hold recommendation, and no price target or stop loss is given.'],
                ['Financial interest', 'Investment Point, its associates and relatives do not have any financial interest in the company discussed, and do not hold actual or beneficial ownership of 1% or more of its securities as of the end of the month before the publication.'],
                ['Conflict of interest', 'There is no material conflict of interest at the time of publication. No compensation or other benefit has been received from the company discussed or any third party in connection with the article, and the company has not been a client in the past twelve months.'],
                ['Other relationships', 'Investment Point has not served as an officer, director or employee of the company discussed and is not engaged in market making activity in its securities.'],
                ['Use of AI', 'Articles may be drafted with the help of an AI writing tool and are reviewed by Investment Point. Investment Point is not responsible for any error, omission or interpretation arising from AI-assisted content.'],
            ]],
        ]],
        ['Former SEBI Registration Disclosure', [
            ['p', 'Where applicable: Mr. Nikhil Bhatt is a Former SEBI Registered Research Analyst.'],
            ['p', 'Any educational or journalistic content published through Investment Point is intended solely for financial awareness, journalism and investor education. Such publication should not be construed as regulated research recommendation or investment advisory services.'],
        ]],
        ['Market Commentary Policy', [
            ['p', 'Market outlooks are prepared using'],
            ['tags', ['Technical Analysis', 'Market Breadth', 'Price Behaviour', 'Volume Behaviour', 'Economic Indicators', 'Public Information', 'Global Events']],
            ['p', 'These opinions are educational and journalistic in nature.'],
        ]],
        ['Company Profile Policy', [
            ['p', 'Company Profiles may include'],
            ['tags', ['Business Overview', 'Financial Highlights', 'Shareholding Pattern', 'Corporate Developments', 'Technical Analysis', 'Historical Information']],
            ['p', 'They are prepared only for investor education and financial awareness.'],
        ]],
        ['Stock Specific Discussion', [
            ['p', 'Stock discussions are published only for'],
            ['tags', ['Educational Study', 'Technical Illustration', 'Financial Journalism', 'Investor Awareness']],
            ['p', 'They shall not be interpreted as'],
            ['tags', ['Buy Recommendation', 'Sell Recommendation', 'Hold Recommendation', 'Trading Signal']],
        ]],
        ['Market Misconduct Disclaimer', [
            ['p', 'Discussion regarding'],
            ['tags', ['Price Movement', 'Price Level', 'Support Level', 'Trading Volume', 'Delivery Percentage', 'Breakouts', 'Long Term / Short Term', 'Market Behaviour']],
            ['p', 'shall not be interpreted as allegations of'],
            ['tags', ['Price Manipulation', 'Insider Trading', 'Circular Trading', 'Fraud', 'Market Abuse', 'Buy, Sell, or Hold Recommendations']],
            ['p', 'Only competent statutory or regulatory authorities may determine such matters.'],
        ]],
        ['Guest Contributor Policy', [
            ['p', 'Investment Point may publish articles written by'],
            ['tags', ['Guest Authors', 'Guest Columnists', 'Industry Experts', 'Research Analysts', 'Investment Advisers', 'Chartered Accountants', 'Company Secretaries', 'Lawyers', 'Academicians']],
            ['p', 'Views expressed remain solely those of the respective author. Publication does not necessarily represent the views of Investment Point.'],
        ]],
        ['Research Analyst (RA) Policy', [
            ['p', 'Where articles are authored by a SEBI Registered Research Analyst, the following shall be disclosed:'],
            ['tags', ['Name', 'SEBI Registration Number', 'Official Website']],
            ['p', 'Investment Point merely acts as the publishing platform unless otherwise stated. Investment Point does not assume responsibility for advisory services independently provided by the (RA).'],
        ]],
        ['Conflict of Interest Policy', [
            ['p', 'Unless expressly disclosed'],
            ['tags', ['No consideration', 'No commission', 'No brokerage', 'No promotional fee']],
            ['p', 'has been received for publication. Where sponsored content exists, it shall be appropriately identified.'],
        ]],
        ['Third Party Content', [
            ['p', 'Investment Point may publish'],
            ['tags', ['Advertisements', 'Sponsored Content', 'Press Releases', 'Guest Articles']],
            ['p', 'Responsibility for such content remains with the originating party unless otherwise specified.'],
        ]],
        ['Copyright Policy', [
            ['p', 'All'],
            ['tags', ['Articles', 'Reports', 'Charts', 'Graphics', 'Research Formats', 'Logos', 'Layouts', 'Designs', 'Newsletters']],
            ['p', 'are protected under applicable copyright laws. Unauthorized reproduction or commercial use is prohibited without prior written permission.'],
        ]],
        ['Website Terms & Conditions', [
            ['p', 'Users agree'],
            ['ul', ['Not to misuse website content.', 'Not to copy articles without permission.', 'Not to publish misleading extracts.', 'Not to impersonate Investment Point.', 'Not to misuse the Investment Point name or logo.', 'Not to create fake social media accounts using the publication’s identity.', 'Not to alter published content.']],
        ]],
        ['User Responsibilities', [
            ['p', 'Users are responsible for'],
            ['ul', ['Independent verification of information.', 'Their own investment decisions.', 'Compliance with applicable laws.']],
        ]],
        ['Privacy Policy', [
            ['p', 'Investment Point respects user privacy. Personal information collected through the website shall be handled in accordance with the applicable laws and the website’s Privacy Policy.'],
        ]],
        ['Grievance Redressal', [
            ['p', 'For publication-related issues, users may contact the Editor through the official website.'],
            ['p', 'Where the grievance relates to research content authored by a SEBI Registered Research Analyst or Investment Adviser, readers may also approach the respective author directly using the contact details provided in the relevant article. Investors may additionally use SEBI’s official grievance redressal mechanisms where applicable.'],
        ]],
        ['Jurisdiction', [
            ['p', 'This publication and website shall be governed by the laws of India.'],
            ['p', 'Subject to applicable law, disputes relating to Investment Point shall be subject to the jurisdiction of the competent courts at Jamnagar, Gujarat.'],
        ]],
        ['Acceptance of Terms', [
            ['p', 'By accessing, reading or using the Investment Point newspaper, articles, newsletters, website or digital platforms, users acknowledge that they have read, understood and agreed to this:'],
            ['tags', ['Disclaimer', 'Disclosure Policy', 'Editorial Policy', 'Guest Contributor Policy', 'Privacy Policy', 'Copyright Policy', 'Terms & Conditions', 'Legal Notice']],
        ]],
    ];
    $roman = ['I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X', 'XI', 'XII', 'XIII', 'XIV', 'XV', 'XVI', 'XVII', 'XVIII', 'XIX', 'XX', 'XXI', 'XXII', 'XXIII', 'XXIV', 'XXV'];
@endphp
<div class="dc-applies">
    <b>Applicable To</b>
    <p>This Policy applies to Investment Point (RNI Registered Weekly Newspaper), Official Website, Mobile Website, Digital Editions, E-Paper, Social Media Platforms, Telegram, WhatsApp, YouTube, Facebook, Instagram, X (Twitter), LinkedIn, Articles, Newsletters, Reports, Market Commentary, Company Profiles, Technical Analysis, Guest Articles, Educational Publications.</p>
</div>

@foreach($parts as $i => [$title, $blocks])
<div class="tc-sec dc-part">
    <h3><em class="dc-num">{{ $roman[$i] }}</em> {{ $title }}</h3>
    @foreach($blocks as [$type, $value])
        @if($type === 'p')
    <p>{{ $value }}</p>
        @elseif($type === 'h')
    <h4 class="dc-h">{{ $value }}</h4>
        @elseif($type === 'tags')
    <div class="dc-tags">@foreach($value as $tag)<span>{{ $tag }}</span>@endforeach</div>
        @elseif($type === 'terms')
    <div class="dc-terms">@foreach($value as [$label, $item])<div><span>{{ $label }}</span><p>{{ $item }}</p></div>@endforeach</div>
        @else
    <ul>@foreach($value as $item)<li>{{ $item }}</li>@endforeach</ul>
        @endif
    @endforeach
</div>
@endforeach

<div class="tc-sec">
    <h3><em>👤</em> Guest Research Analyst Disclosure</h3>
    <p>Where applicable, we include:</p>
    <div class="tc-officer">
        <div><span>Author Name</span><b>Ravi Bhatt</b></div>
        <div><span>SEBI Registration Category</span><b>Research Analyst (RA)</b></div>
        <div><span>SEBI Registration Number</span><b class="notranslate">INH000012591</b></div>
        <div><span>Official Website</span><b><a href="https://www.capsavaj.com/policies" target="_blank" rel="noopener noreferrer">www.capsavaj.com/policies</a></b></div>
    </div>
</div>

<div class="tc-sec">
    <div class="tc-alert">
        <span>⚠️</span>
        <div>
            <b>Statutory Market Risk Warning</b>
            <ul class="dc-risk">
                <li>All content and securities mentioned are strictly for illustration and are not buy, sell, or hold recommendations.</li>
                <li>Investments in the securities market are subject to market risks. Carefully read and agree to all disclaimers and related documents on <a href="https://www.nikhilbhatt.in" target="_blank" rel="noopener noreferrer">www.nikhilbhatt.in</a> before investing.</li>
                <li>Past performance is not indicative of future results. Readers should consult a qualified professional before making any investment decision.</li>
            </ul>
        </div>
    </div>
</div>
