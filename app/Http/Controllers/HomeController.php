<?php

namespace App\Http\Controllers;

use App\Models\ExpertPost;
use App\Models\News;
use App\Models\LatestNews;
use App\Models\LegalPage;
use App\Models\NewsClick;
use App\Models\MarketIndex;
use App\Models\TechnicalAnalysis;
use App\Models\YoutubeVideo;
use App\Models\PremiumContent;
use App\Models\Setting;
use App\Models\UserBlog;
use App\Services\LiveNewsService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class HomeController extends Controller
{
    private function ensureFallbackHomepageData(): void
    {
        if (MarketIndex::count() < 14) {
            $indices = [
                ['name' => 'NIFTY 50', 'value' => 25562.40, 'change_percent' => 0.82, 'direction' => 'up'],
                ['name' => 'SENSEX', 'value' => 81216.24, 'change_percent' => 0.74, 'direction' => 'up'],
                ['name' => 'BANK NIFTY', 'value' => 55243.10, 'change_percent' => -0.21, 'direction' => 'down'],
                ['name' => 'INDIA VIX', 'value' => 14.32, 'change_percent' => 1.42, 'direction' => 'up'],
                ['name' => 'Tata Motors', 'value' => 982.40, 'change_percent' => 4.82, 'direction' => 'up'],
                ['name' => 'Reliance Ind.', 'value' => 2945.20, 'change_percent' => 3.91, 'direction' => 'up'],
                ['name' => 'HDFC Bank', 'value' => 1762.10, 'change_percent' => 3.25, 'direction' => 'up'],
                ['name' => 'Infosys', 'value' => 1540.80, 'change_percent' => 2.98, 'direction' => 'up'],
                ['name' => 'ICICI Bank', 'value' => 1324.50, 'change_percent' => 2.76, 'direction' => 'up'],
                ['name' => 'Bharti Airtel', 'value' => 1642.30, 'change_percent' => 3.42, 'direction' => 'down'],
                ['name' => 'Sun Pharma', 'value' => 1685.90, 'change_percent' => 2.84, 'direction' => 'down'],
                ['name' => 'TCS', 'value' => 3380.00, 'change_percent' => 2.31, 'direction' => 'down'],
                ['name' => 'Wipro', 'value' => 512.40, 'change_percent' => 1.98, 'direction' => 'down'],
                ['name' => 'ITC', 'value' => 458.15, 'change_percent' => 1.76, 'direction' => 'down'],
            ];

            foreach ($indices as $index) {
                MarketIndex::firstOrCreate(['name' => $index['name']], $index);
            }
        }

        // News, Technical Analysis and YouTube videos are never filled in automatically: they come only
        // from the admin panel. Empty sections show their own "nothing yet" text or stay hidden.

        if (PremiumContent::count() === 0) {
            $defaultPlans = [
                [
                    'badge' => 'INVESTMENT POINT PREMIUM',
                    'title' => 'Daily News Letter',
                    'description' => 'Daily market insights, research, and investment ideas.',
                    'price' => 100.00,
                    'billing_period' => 'MONTH',
                    'benefits' => ['📊 Market Trend', '📰 Daily News Letter', '📈 Index / Stock Alerts & Levels', '📑 Financial Awareness'],
                    'button_text' => 'Subscription',
                    'button_url' => route('subscription'),
                    'status' => 'active',
                ],
                [
                    'badge' => 'INVESTMENT POINT PREMIUM',
                    'title' => 'Gujarati News Paper',
                    'description' => 'Weekly market commentary and strategic updates in Gujarati.',
                    'price' => 100.00,
                    'billing_period' => 'YEAR',
                    'benefits' => ['Weekly Market Trend', 'Derivative Index Range', 'Investment Idea', 'Business News Updates'],
                    'button_text' => 'Subscription',
                    'button_url' => route('subscription'),
                    'status' => 'active',
                ],
            ];

            foreach ($defaultPlans as $plan) {
                PremiumContent::firstOrCreate(['title' => $plan['title']], $plan);
            }
        }
    }

    public function index(LiveNewsService $liveNews)
    {
        $this->ensureFallbackHomepageData();

        $marketIndices = MarketIndex::all();
        $tickerNames = ['NIFTY 50', 'SENSEX', 'BANK NIFTY', 'INDIA VIX'];
        $tickerIndices = $marketIndices->whereIn('name', $tickerNames)->sortBy(function ($index) use ($tickerNames) {
            return array_search($index->name, $tickerNames, true);
        })->values();
        $stockMovers = $marketIndices->whereNotIn('name', $tickerNames);
        $gainers = $stockMovers->where('direction', 'up')->sortByDesc('change_percent')->take(5)->values();
        $losers = $stockMovers->where('direction', 'down')->sortByDesc('change_percent')->take(5)->values();
        $news = News::where('status', 'active')->latest()->take(8)->get();
        $featuredNews = News::where('status', 'active')->where('is_featured', true)->orderByDesc('id')->first() ?? $news->first();
        $latestNews = $this->latestNews($liveNews);
        $technicalAnalyses = TechnicalAnalysis::where('status', 'active')->newest()->take(4)->get();
        $youtubeVideos = YoutubeVideo::latest()->take(3)->get();
        $premiumContent = PremiumContent::where('status', 'active')->latest()->first();
        $topExperts = $this->topExperts();
        // "Trusted by": the newspapers that publish Investment Point columns (Media Articles)
        $trustedBy = \App\Models\MediaArticle::trustedBy();

        return view('home', compact('marketIndices', 'tickerIndices', 'gainers', 'losers', 'news', 'featuredNews', 'latestNews', 'technicalAnalyses', 'youtubeVideos', 'premiumContent', 'topExperts', 'trustedBy'));
    }

    /**
     * "Experts View" box next to Top Gainers / Losers: the 10 newest items of the Expert's View page,
     * approved reader blogs (dated when the admin approved them) and Expert's View articles mixed,
     * newest first. So a blog shows up here as soon as it is approved.
     */
    private function topExperts()
    {
        $blogs = UserBlog::with('author')->where('status', 'approved')
            ->latest('approved_at')->latest('id')->take(10)->get()
            ->map(fn (UserBlog $blog) => (object) [
                'url' => route('blog.show', $blog->slug),
                'photo' => $blog->writer_photo_url,
                'is_logo' => !$blog->has_writer_photo,
                'name' => $blog->writer_name,
                'role' => $blog->author_designation ?: 'Guest Expert',
                'title' => $blog->title,
                'date' => $blog->approved_at ?? $blog->created_at,
            ]);

        $articles = ExpertPost::active()
            ->orderByDesc('published_on')->orderByDesc('id')->take(10)->get()
            ->map(fn (ExpertPost $post) => (object) [
                'url' => $post->url,
                'photo' => $post->imageUrl() ?? asset('images/logo-default.png'),
                'is_logo' => !$post->image,
                'name' => $post->writerName(),
                'role' => $post->writerRole(),
                'title' => $post->title,
                'date' => $post->published_on,
            ]);

        return $blogs->concat($articles)->sortByDesc(fn ($item) => $item->date?->timestamp ?? 0)->take(10)->values();
    }

    /** Just the "Latest News" list, fetched by the homepage every few minutes to stay live. */
    public function latestNewsFragment(LiveNewsService $liveNews)
    {
        return response()
            ->view('partials.latest-news-items', ['latestNews' => $this->latestNews($liveNews)])
            ->header('Cache-Control', 'no-store');
    }

    /**
     * "Latest News" box: the newest items from Admin -> Latest News.
     *
     * The live headlines (Economic Times / LiveMint via LiveNewsService) are switched off for now;
     * to bring them back, restore the commented block below and the "Homepage Latest News"
     * card in resources/views/admin/settings/edit.blade.php.
     */
    private function latestNews(LiveNewsService $liveNews)
    {
        return LatestNews::active()->ordered()->take((int) config('news.limit', 10))->get()->map(fn (LatestNews $n) => [
            'title' => $n->title,
            'url' => $n->url,
            'image' => $n->image_url,
            'excerpt' => \App\Support\Html::plain($n->description, 180),
            'date' => $n->display_date,
            'source' => null, // not shown: articles are Investment Point's own reports
            'external' => false,
        ]);

        /* Live API - disabled
        $mode = Setting::get('news_source', 'live');
        $items = collect();

        if ($mode !== 'live') {
            $items = News::where('status', 'active')->latest()->take(8)->get()->map(fn (News $n) => [
                'title' => $n->title,
                'url' => $n->url,
                'image' => $n->image,
                'excerpt' => \App\Support\Html::plain($n->description, 180),
                'date' => $n->created_at,
                'source' => null,
                'external' => false,
            ]);
        }

        if ($mode !== 'admin') {
            $items = $items->concat(collect($liveNews->latest())->map(fn ($n) => [
                'title' => $n['title'],
                'url' => $n['url'],
                'image' => $n['image'],
                'excerpt' => $n['excerpt'],
                'date' => Carbon::parse($n['published_at']),
                'source' => $n['source'],
                'external' => true,
            ]));
        }

        return $items->sortByDesc(fn ($n) => $n['date']?->timestamp ?? 0)->values();
        */
    }

    /** A reader opened a live headline from "Latest News": count it (only headlines we actually show). */
    public function newsClick(Request $request, LiveNewsService $liveNews)
    {
        $url = (string) $request->input('url');
        if ($url !== '' && collect($liveNews->latest())->contains('url', $url)) {
            NewsClick::record($url);
        }

        return response()->noContent();
    }

    public function subscription()
    {
        $this->ensureFallbackHomepageData();

        $marketIndices = MarketIndex::all();
        $tickerNames = ['NIFTY 50', 'SENSEX', 'BANK NIFTY', 'INDIA VIX'];
        $tickerIndices = $marketIndices->whereIn('name', $tickerNames)->sortBy(function ($index) use ($tickerNames) {
            return array_search($index->name, $tickerNames, true);
        })->values();

        $plan = config('subscription.plan'); // contact details
        $plans = \App\Models\SubscriptionRequest::plans();

        return view('subscription', compact('plan', 'plans', 'tickerIndices'));
    }

    /**
     * "Subscription" button: the reader must tick "I agree"; the request is saved with the
     * agreement (time, IP, browser) for Admin -> Subscriptions, then they go on to pay.
     */
    public function subscribe(Request $request)
    {
        $back = redirect()->to(route('subscription') . '#subscribe');

        // Honeypot: a field hidden from people; bots that fill every input get silently ignored
        if ($request->filled('website')) {
            return $back->with('subscribed', true);
        }

        $plans = \App\Models\SubscriptionRequest::plans();
        $byPost = ($plans[$request->input('plan')]['deliver'] ?? null) === 'post';

        $data = $request->validate([
            'plan' => ['required', \Illuminate\Validation\Rule::in(array_keys($plans))],
            'name' => 'required|string|max:100',
            'phone' => ['required', 'string', 'regex:/^(\+?91[\s-]?)?[6-9]\d{4}[\s-]?\d{5}$/'],
            // Digital plan: the newsletter and login OTPs go to WhatsApp - the mobile number, or a different
            // one when "same" is unticked (0). The printed paper goes by post instead.
            'whatsapp_same' => 'nullable|boolean',
            'whatsapp' => $byPost ? 'exclude' : ['exclude_unless:whatsapp_same,0', 'required', 'string', 'regex:/^(\+?91[\s-]?)?[6-9]\d{4}[\s-]?\d{5}$/'],
            // Printed paper: house / street / area typed, then State, City and PIN code from the India Post list
            'address' => $byPost ? 'required|string|min:5|max:400' : 'exclude',
            'state' => $byPost ? ['required', 'string', \Illuminate\Validation\Rule::in(array_keys(\App\Support\IndiaPincodes::all()))] : 'exclude',
            'city' => $byPost ? 'required|string|max:60' : 'exclude',
            'pincode' => $byPost ? ['required', 'digits:6', function ($attribute, $value, $fail) use ($request) {
                if (!\App\Support\IndiaPincodes::matches($request->input('state'), $request->input('city'), $value)) {
                    $fail('Please choose the PIN code from the list for your city.');
                }
            }] : 'exclude',
            'email' => 'nullable|email|max:150',
            'agree' => 'accepted',
        ], [
            'plan.required' => 'Please choose a plan.',
            'phone.regex' => 'Please enter a valid 10-digit Indian WhatsApp number.',
            'whatsapp.required' => 'Please enter your WhatsApp number, or tick "same as my mobile number".',
            'whatsapp.regex' => 'Please enter a valid 10-digit Indian WhatsApp number.',
            'address.required' => 'Please enter the postal address where the paper should be sent.',
            'address.min' => 'Please enter the house / flat, street and area.',
            'state.required' => 'Please choose your state.',
            'state.in' => 'Please choose your state from the list.',
            'city.required' => 'Please choose your city.',
            'pincode.required' => 'Please choose your PIN code.',
            'pincode.digits' => 'Please choose the PIN code from the list for your city.',
            'agree.accepted' => 'Please tick the box to agree to the Terms & Conditions, Privacy Policy, Refund Policy and Disclaimer.',
        ]);

        $plan = $plans[$data['plan']];
        $phone = \App\Models\SubscriptionRequest::normalizePhone($data['phone']);
        $subscription = \App\Models\SubscriptionRequest::create([
            // A logged-in member renewing: the new period goes straight to their account
            'user_id' => auth('member')->id(),
            'name' => trim($data['name']),
            'phone' => $phone,
            'whatsapp' => isset($data['whatsapp']) ? \App\Models\SubscriptionRequest::normalizePhone($data['whatsapp']) : $phone,
            'email' => $data['email'] ?? null,
            // One address text, as before ("12 Shanti Nagar, Ring Road, Surat, Gujarat - 395002") for the admin and the receipt
            'address' => isset($data['address'])
                ? rtrim(trim(preg_replace("/[ \t]+/", ' ', $data['address'])), " ,\r\n") . ", {$data['city']}, {$data['state']} - {$data['pincode']}"
                : null,
            'plan' => $plan['name'],
            'plan_key' => $data['plan'],
            'price' => $plan['price'],
            'months' => $plan['months'],
            'agreed_at' => now(),
            'agreed_to' => \App\Models\SubscriptionRequest::AGREED_TO,
            'ip' => $request->ip(),
            'user_agent' => \Illuminate\Support\Str::limit((string) $request->userAgent(), 250, ''),
        ]);

        return redirect()->route('subscription.pay', $subscription);
    }

    public function about()
    {
        $this->ensureFallbackHomepageData();

        $marketIndices = MarketIndex::all();
        $tickerNames = ['NIFTY 50', 'SENSEX', 'BANK NIFTY', 'INDIA VIX'];
        $tickerIndices = $marketIndices->whereIn('name', $tickerNames)->sortBy(function ($index) use ($tickerNames) {
            return array_search($index->name, $tickerNames, true);
        })->values();

        return view('about', compact('tickerIndices'));
    }

    public function terms()
    {
        return $this->legal('terms');
    }

    public function refund()
    {
        return $this->legal('refund');
    }

    public function contact()
    {
        return view('contact');
    }

    public function privacy()
    {
        return $this->legal('privacy');
    }

    public function disclaimer()
    {
        return $this->legal('disclaimer');
    }

    public function cookies()
    {
        return $this->legal('cookies');
    }

    /** Legal pages: text edited in Admin -> Legal Pages (or the built-in default until then). */
    private function legal(string $slug)
    {
        $page = LegalPage::for($slug);
        [$content, $toc] = LegalPage::withContents($page->contentHtml());

        return view('legal', compact('page', 'content', 'toc'));
    }
}
