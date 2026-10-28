<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\BloggerPublisher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class SettingController extends Controller
{
    public function edit()
    {
        return view('admin.settings.edit', [
            'logoUrl' => Setting::logoUrl(),
            'hasCustomLogo' => (bool) Setting::get('logo'),
            'newsSource' => Setting::get('news_source', 'live'),
            'groqConnected' => (bool) Setting::groqKey(),
            'groqKeyEnd' => ($groqKey = Setting::groqKey()) ? substr($groqKey, -4) : null,
            'groqModel' => Setting::groqModel(),
            'geminiConnected' => (bool) Setting::geminiKey(),
            'geminiKeyEnd' => ($geminiKey = Setting::geminiKey()) ? substr($geminiKey, -4) : null,
            'geminiModel' => Setting::geminiModel(),
            'aiImageDailyLimit' => Setting::aiImageDailyLimit(),
            'googleVerification' => Setting::get('google_site_verification'),
            'googleAnalyticsId' => Setting::get('google_analytics_id'),
            'googleFromEnv' => ['verification' => (bool) config('seo.google_verification'), 'analytics' => (bool) config('seo.google_analytics_id')],
            'social' => collect(Setting::SOCIAL)->map(fn ($m, $k) => $m + ['value' => Setting::get('social_' . $k, $m['default'])])->all(),
            'plans' => \App\Models\SubscriptionRequest::plans(),
            'mail' => [
                'account' => config('mail.mailers.smtp.username'),
                'saved' => (bool) Setting::mailPassword(),
                'savedAt' => ($at = Setting::get('mail_password_saved_at')) ? \Illuminate\Support\Carbon::parse($at)->timezone('Asia/Kolkata') : null,
            ],
            'blogger' => [
                'clientId' => BloggerPublisher::clientId(),
                'hasSecret' => (bool) BloggerPublisher::clientSecret(),
                'connected' => app(BloggerPublisher::class)->isConnected(),
                // Saved "Post using email" addresses, the secret word hidden: gujarati => "name.••••@blogger.com"
                'emails' => collect(['gujarati', 'hindi', 'english'])->mapWithKeys(fn ($e) => [$e => ($a = \App\Services\EpaperByEmail::address($e))
                    ? preg_replace('/^([^.@]+)\.[^@]+@/', '$1.••••@', $a) : null])->all(),
                'connectedAt' => ($at = Setting::get('blogger_connected_at')) ? \Illuminate\Support\Carbon::parse($at)->timezone('Asia/Kolkata') : null,
                'sections' => BloggerPublisher::sections(),
                'blogs' => BloggerPublisher::blogs(),
                'error' => Setting::get('blogger_error'),
                'redirect' => route('admin.settings.blogger.callback'),
            ],
        ]);
    }

    /**
     * Website Email: the Gmail App Password (16 letters, shown by Google as "abcd efgh ijkl mnop"). It is tried
     * on Gmail first and saved (encrypted) only if Gmail accepts it; then a test email goes to the website's own
     * address. Removing it goes back to MAIL_PASSWORD in the server's .env.
     */
    public function updateMail(Request $request, \App\Services\MailAccount $account)
    {
        $back = redirect()->to(route('admin.settings.edit') . '#website-email');
        if ($request->boolean('remove')) {
            Setting::set('mail_password', '');

            return $back->with('success', 'The saved email password was removed. The website uses the password in the server settings (.env) again.');
        }

        $request->merge(['mail_password' => preg_replace('/\s+/', '', (string) $request->input('mail_password'))]);
        $request->validate(['mail_password' => ['required', 'regex:/^[A-Za-z]{16}$/']], [
            'mail_password.required' => 'Paste the 16-letter App Password.',
            'mail_password.regex' => 'A Gmail App Password is exactly 16 letters (spaces are fine, they are removed). Your normal Gmail password does not work here.',
        ]);

        try {
            $account->check($request->input('mail_password'));
        } catch (\RuntimeException $e) {
            return $back->withErrors(['mail_password' => 'Not saved: ' . $e->getMessage()]);
        }

        Setting::set('mail_password', \Illuminate\Support\Facades\Crypt::encryptString($request->input('mail_password')));
        Setting::set('mail_password_saved_at', now()->toIso8601String());
        Setting::applyMailPassword();
        app('mail.manager')->purge('smtp'); // a mailer made earlier in this request still has the old password

        try {
            \Illuminate\Support\Facades\Mail::raw('The website email works (' . now('Asia/Kolkata')->format('d M Y, h:i A') . ').',
                fn ($m) => $m->to(config('mail.from.address'))->subject('Investment Point: test email'));
            $sent = ' A test email was sent to ' . config('mail.from.address') . '.';
        } catch (\Throwable $e) {
            $sent = ' (The test email could not be sent: ' . \Illuminate\Support\Str::limit($e->getMessage(), 120) . ')';
        }

        return $back->with('success', 'Email password saved and accepted by Gmail. Website emails work again.' . $sent);
    }

    /**
     * Blogger auto-post, step 1: the Client ID and Client Secret of the Google Cloud "Web application" client
     * (the secret is stored encrypted and never shown again). Changing them means connecting again.
     */
    public function updateBlogger(Request $request)
    {
        $data = $request->validate([
            'blogger_client_id' => ['required', 'string', 'regex:/^[0-9]+-[a-z0-9]+\.apps\.googleusercontent\.com$/'],
            'blogger_client_secret' => [BloggerPublisher::clientSecret() ? 'nullable' : 'required', 'string', 'regex:/^[A-Za-z0-9_\-]{20,80}$/'],
        ], [
            'blogger_client_id.regex' => 'The Client ID ends with .apps.googleusercontent.com. Copy it again from Google Cloud -> Clients.',
            'blogger_client_secret.required' => 'Paste the Client Secret too.',
            'blogger_client_secret.regex' => 'That does not look like a Client Secret (it usually starts with GOCSPX- and has no spaces).',
        ]);

        // Saving the same ID and secret again keeps the connection
        $changed = $data['blogger_client_id'] !== BloggerPublisher::clientId()
            || (!empty($data['blogger_client_secret']) && $data['blogger_client_secret'] !== BloggerPublisher::clientSecret());
        if ($changed) {
            app(BloggerPublisher::class)->disconnect(); // a token belongs to the old client
        }
        Setting::set('blogger_client_id', $data['blogger_client_id']);
        if (!empty($data['blogger_client_secret'])) {
            Setting::set('blogger_client_secret', \Illuminate\Support\Facades\Crypt::encryptString($data['blogger_client_secret']));
        }

        return redirect()->to(route('admin.settings.edit') . '#blogger')->with('success', 'Google client saved. Now click "Connect Blogger" and sign in with ' . config('blogger.account') . '.');
    }

    /** Step 2: off to Google's sign-in page (the state stops a forged callback) */
    public function connectBlogger(Request $request, BloggerPublisher $blogger)
    {
        if (!BloggerPublisher::clientId() || !BloggerPublisher::clientSecret()) {
            return redirect()->to(route('admin.settings.edit') . '#blogger')->withErrors(['blogger_client_id' => 'Save the Client ID and Client Secret first.']);
        }
        $state = \Illuminate\Support\Str::random(40);
        $request->session()->put('blogger_oauth_state', $state);

        return redirect()->away($blogger->authUrl(route('admin.settings.blogger.callback'), $state));
    }

    /** Step 3: back from Google with a code: saved as access, and each section's blog is looked up */
    public function bloggerCallback(Request $request, BloggerPublisher $blogger)
    {
        $back = redirect()->to(route('admin.settings.edit') . '#blogger');
        $state = $request->session()->pull('blogger_oauth_state');
        if (!$state || !hash_equals($state, (string) $request->query('state'))) {
            return $back->withErrors(['blogger_client_id' => 'The Google sign-in expired. Please click "Connect Blogger" again.']);
        }
        if ($request->query('error') || !$request->query('code')) {
            return $back->withErrors(['blogger_client_id' => 'Google sign-in was cancelled (' . ($request->query('error') ?: 'no code') . ').']);
        }

        try {
            $blogs = $blogger->connect((string) $request->query('code'), route('admin.settings.blogger.callback'));
        } catch (\RuntimeException $e) {
            return $back->withErrors(['blogger_client_id' => $e->getMessage()]);
        }

        return $back->with('success', 'Blogger is connected (' . count($blogs) . ' blogs found). New E-Paper editions and Media Articles you upload from now on are posted to Blogger automatically.');
    }

    /** Daily E-Paper: each blog's secret "Post using email" address (saved encrypted; empty = keep the saved one) */
    public function updateBloggerEmails(Request $request)
    {
        $rule = ['nullable', 'string', 'max:120', 'regex:/^[A-Za-z0-9._-]+\.[A-Za-z0-9_-]+@blogger\.com$/i'];
        $data = $request->validate(['blogger_email_gujarati' => $rule, 'blogger_email_hindi' => $rule, 'blogger_email_english' => $rule], [
            'regex' => 'Copy the whole address from Blogger → Settings → Email → Post using email (name.secretword@blogger.com).',
        ]);
        foreach (array_filter($data) as $key => $address) {
            Setting::set($key, \Illuminate\Support\Facades\Crypt::encryptString(strtolower(trim($address))));
        }

        return redirect()->to(route('admin.settings.edit') . '#blogger-emails')->with('success', 'Blogger email addresses saved.');
    }

    public function disconnectBlogger(BloggerPublisher $blogger)
    {
        $blogger->disconnect();

        return redirect()->to(route('admin.settings.edit') . '#blogger')->with('success', 'Blogger is disconnected. Uploads are no longer posted there (posts already made stay on Blogger).');
    }

    /**
     * Plan prices (rupees): the Daily Digital News Letter (per month) and the Weekly Gujarati News Paper
     * (per year). Used for new subscriptions from now on: the subscription page, the payment (Razorpay)
     * and the receipt. Subscriptions already paid keep their own price.
     */
    public function updateSubscriptionPrice(Request $request)
    {
        $plans = \App\Models\SubscriptionRequest::plans();
        $rules = $messages = $names = [];
        foreach ($plans as $key => $plan) {
            $rules["price_{$key}"] = 'required|integer|min:1|max:1000000';
            $messages["price_{$key}.integer"] = 'Please enter the price in whole rupees (no paise), e.g. 8000.';
            $names["price_{$key}"] = $plan['name'] . ' price';
        }
        $data = $request->validate($rules, $messages, $names);

        foreach ($plans as $key => $plan) {
            Setting::set("price_{$key}", (string) $data["price_{$key}"]);
        }

        return redirect()->to(route('admin.settings.edit') . '#subscription-price')->with('success', 'Subscription prices saved: '
            . collect($plans)->map(fn ($p, $k) => $p['name'] . ' ₹' . number_format($data["price_{$k}"]) . ' / ' . $p['period'])->implode(', ') . '.');
    }

    /**
     * Free AI for writing (Groq): the admin pastes the key from console.groq.com. Before it is saved it is
     * tried once, on each model in config('services.groq_ai.models') until one answers, and that model is
     * remembered. The key is stored encrypted and never shown again in full.
     */
    public function updateAi(Request $request, \App\Services\NewsWriter $writer)
    {
        if ($request->boolean('remove')) {
            Setting::set('groq_api_key', '');
            Setting::set('groq_model', '');

            return redirect()->to(route('admin.settings.edit') . '#free-ai')->with('success', 'The Groq key was removed.');
        }

        $data = $request->validate(
            ['groq_api_key' => ['required', 'string', 'regex:/^gsk_[A-Za-z0-9_\-]{20,200}$/']],
            ['groq_api_key.required' => 'Paste the API key from console.groq.com.',
                'groq_api_key.regex' => 'That does not look like a Groq key. It starts with gsk_ and has no spaces.']
        );

        $error = 'No Groq model answered.';
        foreach (config('services.groq_ai.models') as $model) {
            try {
                $writer->groq([['role' => 'user', 'content' => 'Reply with the single word OK.']], 400, 0, $data['groq_api_key'], $model);

                Setting::set('groq_api_key', \Illuminate\Support\Facades\Crypt::encryptString($data['groq_api_key']));
                Setting::set('groq_model', $model);

                return redirect()->to(route('admin.settings.edit') . '#free-ai')
                    ->with('success', 'Groq is connected (model ' . $model . '). It writes articles, titles and translations when Gemini is not set up or not available.');
            } catch (\RuntimeException $e) {
                $error = $e->getMessage();
                if (!str_starts_with($error, 'MODEL: ')) {
                    break; // wrong key, no connection, limit reached: another model would not help
                }
            }
        }

        return back()->withErrors(['groq_api_key' => 'Not saved: ' . preg_replace('/^MODEL: /', '', $error)]);
    }

    /**
     * Google Gemini (paid): the admin pastes the key from aistudio.google.com. Before it is saved it is tried
     * once, on each model in config('services.gemini_ai.models') until one answers, and that model is
     * remembered. The key is stored encrypted and never shown again in full. The daily AI picture limit
     * can be saved on its own (key box left empty).
     */
    public function updateGemini(Request $request, \App\Services\NewsWriter $writer)
    {
        if ($request->boolean('remove')) {
            Setting::set('gemini_api_key', '');
            Setting::set('gemini_model', '');

            return redirect()->to(route('admin.settings.edit') . '#gemini-ai')->with('success', 'The Gemini key was removed. Text is written by Groq (if set up). AI pictures still come from the free AI services.');
        }

        $data = $request->validate([
            'gemini_api_key' => ['nullable', 'string', 'regex:/^[A-Za-z0-9_\-.]{30,120}$/'],
            'ai_image_daily_limit' => ['required', 'integer', 'min:0', 'max:1000'],
        ], [
            'gemini_api_key.regex' => 'That does not look like a Gemini key. Copy it again from aistudio.google.com (it starts with AQ. or AIza and has no spaces).',
            'ai_image_daily_limit.integer' => 'Enter a whole number of pictures, e.g. 30.',
        ]);
        Setting::set('ai_image_daily_limit', (string) $data['ai_image_daily_limit']);
        $limit = ' Daily AI picture limit: ' . $data['ai_image_daily_limit'] . '.';

        if (empty($data['gemini_api_key'])) {
            return redirect()->to(route('admin.settings.edit') . '#gemini-ai')->with('success', 'Saved.' . $limit);
        }

        $error = 'No Gemini model answered.';
        foreach (config('services.gemini_ai.models') as $model) {
            try {
                $writer->gemini([['role' => 'user', 'content' => 'Reply with the single word OK.']], 50, 0, $data['gemini_api_key'], $model);

                Setting::set('gemini_api_key', \Illuminate\Support\Facades\Crypt::encryptString($data['gemini_api_key']));
                Setting::set('gemini_model', $model);

                return redirect()->to(route('admin.settings.edit') . '#gemini-ai')
                    ->with('success', 'Gemini is connected (model ' . $model . '). Articles, titles and translations are written by Gemini (AI pictures come from free AI services).' . $limit);
            } catch (\RuntimeException $e) {
                $error = $e->getMessage();
                if (!str_starts_with($error, 'MODEL: ')) {
                    break; // wrong key, no connection, limit reached: another model would not help
                }
            }
        }

        return back()->withErrors(['gemini_api_key' => 'Key not saved: ' . preg_replace('/^MODEL: /', '', $error)]);
    }

    /**
     * Google Search Console code and Google Analytics ID, so they can be pasted here instead of editing .env.
     * The whole <meta ...> tag from Search Console may be pasted: only its code is kept. Empty = not connected.
     */
    public function updateGoogle(Request $request)
    {
        $code = trim((string) $request->input('google_site_verification'));
        if (preg_match('/content\s*=\s*["\']([^"\']+)["\']/i', $code, $m)) {
            $code = trim($m[1]);
        }
        $request->merge(['google_site_verification' => $code, 'google_analytics_id' => strtoupper(trim((string) $request->input('google_analytics_id')))]);

        $data = $request->validate([
            'google_site_verification' => ['nullable', 'string', 'regex:/^[A-Za-z0-9_\-]{20,120}$/'],
            'google_analytics_id' => ['nullable', 'string', 'regex:/^G-[A-Z0-9]{6,20}$/'],
        ], [
            'google_site_verification.regex' => 'That does not look like a Search Console code. Copy the HTML tag from Search Console (or only the part inside content="...").',
            'google_analytics_id.regex' => 'The Measurement ID starts with G- followed by letters and numbers, e.g. G-AB12CD34EF.',
        ]);

        Setting::set('google_site_verification', $data['google_site_verification'] ?? '');
        Setting::set('google_analytics_id', $data['google_analytics_id'] ?? '');

        return redirect()->to(route('admin.settings.edit') . '#google')->with('success', 'Google settings saved. They are on every page of the website now.');
    }

    /** Social media links shown as icons across the website (empty = hidden). */
    public function updateSocial(Request $request)
    {
        $rules = [];
        foreach (Setting::SOCIAL as $key => $meta) {
            $rules['social_' . $key] = $key === 'email' ? 'nullable|email|max:255' : 'nullable|url|max:500';
        }
        $data = $request->validate($rules, [], collect(Setting::SOCIAL)->mapWithKeys(fn ($m, $k) => ['social_' . $k => $m['label'] . ' link'])->all());

        foreach (array_keys(Setting::SOCIAL) as $key) {
            // Save '' (not null) so a cleared default link stays hidden
            Setting::set('social_' . $key, $data['social_' . $key] ?? '');
        }

        return redirect()->route('admin.settings.edit')->with('success', 'Social media links updated.');
    }

    /** Homepage "Latest News": admin news, live headlines, or both. */
    public function updateNewsSource(Request $request)
    {
        $data = $request->validate(['news_source' => 'required|in:both,live,admin']);
        Setting::set('news_source', $data['news_source']);

        return redirect()->route('admin.settings.edit')->with('success', 'Latest News source updated.');
    }

    public function update(Request $request)
    {
        $request->validate([
            // No SVG: an uploaded SVG opened directly can run scripts
            'logo' => 'required|image|mimes:jpg,jpeg,png,webp|max:2048',
        ], [
            'logo.required' => 'Please choose a logo image to upload.',
        ]);

        $old = Setting::get('logo');
        $path = $request->file('logo')->store('settings', 'public');
        Setting::set('logo', $path);

        if ($old) {
            Storage::disk('public')->delete($old);
        }

        return redirect()->route('admin.settings.edit')->with('success', 'Logo updated. It now shows across the whole website.');
    }

    public function resetLogo()
    {
        $old = Setting::get('logo');
        Setting::set('logo', null);

        if ($old) {
            Storage::disk('public')->delete($old);
        }

        return redirect()->route('admin.settings.edit')->with('success', 'Logo reset to the default Investment Point logo.');
    }
}
