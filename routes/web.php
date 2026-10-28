<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\MarketDataController;
use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\NewsController;
use App\Http\Controllers\Admin\MarketIndexController;
use App\Http\Controllers\Admin\TechnicalAnalysisController;
use App\Http\Controllers\Admin\YoutubeVideoController;
use App\Http\Controllers\Admin\PremiumContentController;
use App\Http\Controllers\BlogController;
use App\Http\Controllers\NewsPageController;
use App\Http\Controllers\TechnicalAnalysisPageController;
use App\Http\Controllers\Admin\UserBlogController;
use App\Http\Controllers\Admin\SettingController;

/*
|--------------------------------------------------------------------------
| Public Routes
|--------------------------------------------------------------------------
*/

Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/subscription', [HomeController::class, 'subscription'])->name('subscription');
// Open to everyone, so max 5 per minute per visitor
Route::post('/subscription', [HomeController::class, 'subscribe'])->middleware('throttle:site')->name('subscription.store');
// Payment, receipt and member pages are 'private': never cached by browsers or indexed
// Payment + receipt page (secret link per subscription): Razorpay checkout, or the demo button while no keys are set
Route::middleware('private')->group(function () {
    Route::get('/subscription/pay/{subscription:token}', [\App\Http\Controllers\SubscriptionPaymentController::class, 'show'])->name('subscription.pay');
    Route::post('/subscription/pay/{subscription:token}/verify', [\App\Http\Controllers\SubscriptionPaymentController::class, 'verify'])->middleware('throttle:site')->name('subscription.verify');
    Route::post('/subscription/pay/{subscription:token}/demo', [\App\Http\Controllers\SubscriptionPaymentController::class, 'demo'])->middleware('throttle:site')->name('subscription.demo');
    // Member (subscriber) login: own mobile number + SMS OTP, then the member dashboard
    Route::get('/member/login', [\App\Http\Controllers\MemberAuthController::class, 'showLogin'])->name('member.login');
    Route::post('/member/login/otp', [\App\Http\Controllers\MemberAuthController::class, 'sendOtp'])->middleware('throttle:site')->name('member.otp.send');
    Route::post('/member/login/verify', [\App\Http\Controllers\MemberAuthController::class, 'verifyOtp'])->middleware('throttle:site')->name('member.otp.verify');
    Route::post('/member/login/change-number', [\App\Http\Controllers\MemberAuthController::class, 'changeNumber'])->name('member.otp.change');
    Route::post('/member/logout', [\App\Http\Controllers\MemberAuthController::class, 'logout'])->name('member.logout');
    Route::get('/member/dashboard', \App\Http\Controllers\MemberDashboardController::class)->middleware('auth:member,web')->name('member.dashboard');
});
Route::redirect('/member/register', '/member/login'); // old link: accounts are created by the first OTP login
Route::get('/about', [HomeController::class, 'about'])->name('about');
Route::get('/terms-and-conditions', [HomeController::class, 'terms'])->name('terms');
Route::get('/refund-cancellation-policy', [HomeController::class, 'refund'])->name('refund');
Route::get('/contact-us', [HomeController::class, 'contact'])->name('contact');
// Services menu: Stock Market (= the Subscription page), Insurance (= Life Insurance; General Insurance once shown in Admin -> Insurance Products)
Route::redirect('/services/stock-market', '/subscription')->name('services.stock-market');
Route::get('/services/insurance/{insurance}', [\App\Http\Controllers\ServicesController::class, 'insurance'])->whereIn('insurance', ['life', 'general'])->name('services.insurance');
Route::post('/services/insurance/{insurance}', [\App\Http\Controllers\ServicesController::class, 'enquire'])->whereIn('insurance', ['life', 'general'])->middleware('throttle:site')->name('services.insurance.enquire');
Route::get('/privacy-policy', [HomeController::class, 'privacy'])->name('privacy');
Route::get('/disclaimer', [HomeController::class, 'disclaimer'])->name('disclaimer');
Route::get('/cookie-policy', [HomeController::class, 'cookies'])->name('cookies');
Route::view('/profile', 'profile')->name('profile');
Route::get('/sitemap.xml', [\App\Http\Controllers\SeoController::class, 'sitemap'])->name('seo.sitemap');
Route::get('/robots.txt', [\App\Http\Controllers\SeoController::class, 'robots'])->name('seo.robots');
Route::get('/experts-view', [\App\Http\Controllers\ExpertViewController::class, 'index'])->name('experts-view.index');
Route::get('/experts-view/{expertPost}/{slug?}', [\App\Http\Controllers\ExpertViewController::class, 'show'])->whereNumber('expertPost')->name('experts-view.show');
Route::get('/divya-bhaskar',[\App\Http\Controllers\DivyaBhaskarController::class, 'index'])->name('divya-bhaskar.index');
Route::get('/media-articles',[\App\Http\Controllers\MediaArticleController::class, 'index'])->name('media-articles.index');
Route::get('/media-articles/{mediaArticle}/{slug?}', [\App\Http\Controllers\MediaArticleController::class, 'show'])->whereNumber('mediaArticle')->name('media-articles.show');
Route::get('/e-paper/digital-newspaper', [\App\Http\Controllers\DigitalNewspaperController::class, 'index'])->name('digital-newspaper.index');
Route::get('/e-paper/digital-newspaper/{id}/{slug?}', [\App\Http\Controllers\DigitalNewspaperController::class, 'show'])->whereNumber('id')->name('digital-newspaper.show');
Route::get('/e-paper/{language}',[\App\Http\Controllers\EpaperController::class, 'index'])->whereIn('language', ['gujarati', 'hindi', 'english'])->name('epaper.index');
Route::get('/e-paper/{language}/{id}', [\App\Http\Controllers\EpaperController::class, 'show'])->whereIn('language', ['gujarati', 'hindi', 'english'])->whereNumber('id')->name('epaper.show');
// "Ask anything" box on the Disclaimer page: open to everyone, so max 5 per minute per visitor
Route::post('/disclaimer/ask', [\App\Http\Controllers\ReaderQueryController::class, 'store'])->middleware('throttle:site')->name('queries.store');
// Background polling from open pages: no session, cookies or visit tracking (nothing to save,
// and skipping them avoids a session-file write on every poll)
$polling = [
    \App\Http\Middleware\EncryptCookies::class,
    \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
    \Illuminate\Session\Middleware\StartSession::class,
    \Illuminate\View\Middleware\ShareErrorsFromSession::class,
    \App\Http\Middleware\VerifyCsrfToken::class,
    \App\Http\Middleware\TrackPageView::class,
    \App\Http\Middleware\RunNewsAutomation::class,
];
// Open pages poll this to notice admin changes and refresh their content without a reload
Route::get('/content-version', fn () => response()->json(['v' => \App\Support\ContentVersion::current()])->header('Cache-Control', 'no-store'))
    ->middleware('throttle:site')->withoutMiddleware($polling)->name('content.version');
Route::get('/latest-news',[HomeController::class, 'latestNewsFragment'])->middleware('throttle:site')->withoutMiddleware($polling)->name('news.latest');
// Homepage "Latest News": counts a click on a live (external) headline
Route::post('/latest-news/click', [HomeController::class, 'newsClick'])->middleware('throttle:site')->name('news.click');
Route::get('/market-data', MarketDataController::class)->middleware('throttle:site')->withoutMiddleware($polling)->name('market.data');
Route::get('/news', [NewsPageController::class, 'index'])->name('news.index');
Route::get('/news/{news}/{slug?}', [NewsPageController::class, 'show'])->whereNumber('news')->name('news.show');
Route::get('/news/latest/{id}/{slug?}', [NewsPageController::class, 'showLatest'])->whereNumber('id')->name('latest-news.show');
Route::get('/news/popular/{id}/{slug?}', [NewsPageController::class, 'showPopular'])->whereNumber('id')->name('popular-news.show');
Route::get('/technical-analysis', [TechnicalAnalysisPageController::class, 'index'])->name('analysis.index');
Route::get('/technical-analysis/{analysis}/{slug?}', [TechnicalAnalysisPageController::class, 'show'])->whereNumber('analysis')->name('analysis.show');Route::get('/blogs', [BlogController::class, 'index'])->name('blog.index');
Route::get('/blog/create', [BlogController::class, 'create'])->name('blog.create');
// Editor's Desk: Nikhil Bhatt's daily articles (Admin -> Editor's Desk)
Route::get('/editors-desk', [\App\Http\Controllers\EditorsDeskController::class, 'index'])->name('editors-desk.index');
Route::get('/editors-desk/{article}/{slug?}', [\App\Http\Controllers\EditorsDeskController::class, 'show'])->whereNumber('article')->name('editors-desk.show');
// Open to everyone, so limit submissions to 5 per minute per visitor
Route::post('/blog', [BlogController::class, 'store'])->middleware('throttle:site')->name('blog.store');
Route::get('/blog/{slug}', [BlogController::class, 'show'])->name('blog.show');
Route::post('/blog/{slug}/react', [BlogController::class, 'react'])->middleware('throttle:site')->name('blog.react');
// Old author login/register links now go straight to the blog form
Route::redirect('/author/login', '/blog/create');
Route::redirect('/author/register', '/blog/create');

/*
|--------------------------------------------------------------------------
| Admin Auth Routes
|--------------------------------------------------------------------------
*/

// Admin address comes from ADMIN_PATH in .env (default "admin"); see config/admin.php
$adminPath = config('admin.path');

Route::middleware('admin.security')->group(function () use ($adminPath) {
    Route::get('/' . $adminPath, [AuthController::class, 'showLogin'])->name('admin.login');
    Route::post('/' . $adminPath . '/login', [AuthController::class, 'login'])->name('admin.login.submit');
    Route::post('/' . $adminPath . '/logout', [AuthController::class, 'logout'])->name('admin.logout');
});

/*
|--------------------------------------------------------------------------
| Admin Protected Routes
|--------------------------------------------------------------------------
*/

// auth.session: changing the password logs out every other device
Route::prefix($adminPath)->name('admin.')->middleware(['auth:web', 'auth.session', 'admin', 'admin.security', 'content.version'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    // Team users without the Dashboard land here: hello, their role and username, and their pages
    Route::get('/welcome', [DashboardController::class, 'welcome'])->name('welcome');
    Route::get('/account', [\App\Http\Controllers\Admin\AccountController::class, 'edit'])->name('account.edit');
    Route::put('/account/password', [\App\Http\Controllers\Admin\AccountController::class, 'updatePassword'])->middleware('throttle:site')->name('account.password');
    Route::post('/ai-image', [\App\Http\Controllers\Admin\AiImageController::class, 'generate'])->middleware('throttle:site')->name('ai-image');
    // "Find real photo": a free Pexels stock photo for the same image fields
    Route::post('/stock-photo', [\App\Http\Controllers\Admin\AiImageController::class, 'photo'])->middleware('throttle:site')->name('stock-photo');
    // Media Library: pictures uploaded once, picked for any image field ("Media Library" button)
    Route::get('/media', [\App\Http\Controllers\Admin\MediaController::class, 'index'])->name('media.index');
    Route::post('/media', [\App\Http\Controllers\Admin\MediaController::class, 'store'])->name('media.store');
    Route::post('/media/bulk-delete', [\App\Http\Controllers\Admin\MediaController::class, 'bulkDestroy'])->name('media.bulk-delete');
    Route::put('/media/{media}', [\App\Http\Controllers\Admin\MediaController::class, 'update'])->name('media.update');
    Route::delete('/media/{media}', [\App\Http\Controllers\Admin\MediaController::class, 'destroy'])->name('media.destroy');
    // User & Role Management and Role & Permission Management (Super Admin only, see App\Support\AdminPages)
    Route::patch('/team/users/{teamUser}/status', [\App\Http\Controllers\Admin\AdminUserController::class, 'status'])->name('team.users.status');
    Route::resource('team/users', \App\Http\Controllers\Admin\AdminUserController::class)->parameters(['users' => 'teamUser'])->except(['show'])->names('team.users');
    Route::resource('team/roles', \App\Http\Controllers\Admin\AdminRoleController::class)->parameters(['roles' => 'adminRole'])->except(['show'])->names('team.roles');
    Route::get('/settings', [SettingController::class, 'edit'])->name('settings.edit');
    Route::post('/settings/logo', [SettingController::class, 'update'])->name('settings.update');
    Route::delete('/settings/logo', [SettingController::class, 'resetLogo'])->name('settings.logo.reset');
    Route::post('/settings/social', [SettingController::class, 'updateSocial'])->name('settings.social');
    Route::post('/settings/google', [SettingController::class, 'updateGoogle'])->name('settings.google');
    Route::post('/settings/free-ai', [SettingController::class, 'updateAi'])->middleware('throttle:site')->name('settings.ai');
    Route::post('/settings/gemini', [SettingController::class, 'updateGemini'])->middleware('throttle:site')->name('settings.gemini');
    // Website Email: the Gmail App Password (checked on Gmail before it is saved)
    Route::post('/settings/mail', [SettingController::class, 'updateMail'])->middleware('throttle:site')->name('settings.mail');
    // Blogger auto-post: Google client, sign-in (back to the callback) and disconnect
    Route::post('/settings/blogger', [SettingController::class, 'updateBlogger'])->name('settings.blogger');
    Route::post('/settings/blogger/emails', [SettingController::class, 'updateBloggerEmails'])->name('settings.blogger.emails');
    Route::get('/settings/blogger/connect', [SettingController::class, 'connectBlogger'])->name('settings.blogger.connect');
    Route::get('/settings/blogger/callback', [SettingController::class, 'bloggerCallback'])->name('settings.blogger.callback');
    Route::delete('/settings/blogger', [SettingController::class, 'disconnectBlogger'])->name('settings.blogger.disconnect');
    Route::post('/settings/subscription-price',[SettingController::class, 'updateSubscriptionPrice'])->name('settings.subscription-price');
    Route::post('/settings/news-source',[SettingController::class, 'updateNewsSource'])->name('settings.news-source');
    Route::get('/blogs', [UserBlogController::class, 'index'])->name('blogs.index');
    Route::get('/blogs/{blog}/edit', [UserBlogController::class, 'edit'])->name('blogs.edit');
    Route::put('/blogs/{blog}', [UserBlogController::class, 'update'])->name('blogs.update');
    Route::delete('/blogs/{blog}', [UserBlogController::class, 'destroy'])->name('blogs.destroy');
    Route::get('/subscriptions', [\App\Http\Controllers\Admin\SubscriptionRequestController::class, 'index'])->name('subscriptions.index');
    Route::post('/subscriptions/bulk-delete', [\App\Http\Controllers\Admin\SubscriptionRequestController::class, 'bulkDestroy'])->name('subscriptions.bulk-delete');
    Route::put('/subscriptions/{subscription}', [\App\Http\Controllers\Admin\SubscriptionRequestController::class, 'update'])->name('subscriptions.update');
    Route::post('/subscriptions/{subscription}/whatsapp', [\App\Http\Controllers\Admin\SubscriptionRequestController::class, 'resendWhatsapp'])->name('subscriptions.whatsapp');
    Route::delete('/subscriptions/{subscription}', [\App\Http\Controllers\Admin\SubscriptionRequestController::class, 'destroy'])->name('subscriptions.destroy');
    Route::resource('members', \App\Http\Controllers\Admin\MemberController::class)->parameters(['members' => 'member'])->except(['show']);
    Route::post('/member-picks/bulk-delete', [\App\Http\Controllers\Admin\MemberPickController::class, 'bulkDestroy'])->name('member-picks.bulk-delete');
    Route::resource('member-picks', \App\Http\Controllers\Admin\MemberPickController::class)->parameters(['member-picks' => 'memberPick'])->except(['show']);
    // Services -> Insurance: the plan categories (and menu / caption settings), and what readers send from the pages
    Route::put('/insurance-products/settings', [\App\Http\Controllers\Admin\InsuranceProductController::class, 'settings'])->name('insurance-products.settings');
    Route::resource('insurance-products', \App\Http\Controllers\Admin\InsuranceProductController::class)->parameters(['insurance-products' => 'insuranceProduct'])->only(['index', 'store', 'update', 'destroy']);
    Route::post('/insurance-enquiries/bulk-delete', [\App\Http\Controllers\Admin\InsuranceEnquiryController::class, 'bulkDestroy'])->name('insurance-enquiries.bulk-delete');
    Route::resource('insurance-enquiries', \App\Http\Controllers\Admin\InsuranceEnquiryController::class)->parameters(['insurance-enquiries' => 'insuranceEnquiry'])->only(['index', 'destroy']);
    Route::get('/queries', [\App\Http\Controllers\Admin\ReaderQueryController::class, 'index'])->name('queries.index');
    Route::get('/queries/{readerQuery}', [\App\Http\Controllers\Admin\ReaderQueryController::class, 'show'])->name('queries.show');
    Route::get('/queries/{readerQuery}/files/{index}', [\App\Http\Controllers\Admin\ReaderQueryController::class, 'download'])->whereNumber('index')->name('queries.download');
    Route::delete('/queries/{readerQuery}', [\App\Http\Controllers\Admin\ReaderQueryController::class, 'destroy'])->name('queries.destroy');    Route::post('/news/bulk-delete', [NewsController::class, 'bulkDestroy'])->name('news.bulk-delete');
    Route::resource('news', NewsController::class)->except(['show']);
    Route::get('/news-inbox', [\App\Http\Controllers\Admin\NewsInboxController::class, 'index'])->name('news-inbox.index');
    Route::post('/news-inbox/refresh', [\App\Http\Controllers\Admin\NewsInboxController::class, 'refresh'])->middleware('throttle:site')->name('news-inbox.refresh');
    Route::post('/news-inbox/auto-publish', [\App\Http\Controllers\Admin\NewsInboxController::class, 'autoPublish'])->middleware('throttle:site')->name('news-inbox.auto-publish');
    Route::post('/news-inbox/bulk-dismiss', [\App\Http\Controllers\Admin\NewsInboxController::class, 'bulkDismiss'])->name('news-inbox.bulk-dismiss');
    Route::post('/news-inbox/{item}/dismiss', [\App\Http\Controllers\Admin\NewsInboxController::class, 'dismiss'])->name('news-inbox.dismiss');
    Route::post('/news-writer', [\App\Http\Controllers\Admin\NewsInboxController::class, 'write'])->middleware('throttle:site')->name('news-writer');
    Route::post('/title-suggestions', \App\Http\Controllers\Admin\TitleSuggestionController::class)->middleware('throttle:site')->name('title-suggestions');
    Route::post('/translate-to-english', \App\Http\Controllers\Admin\TranslationController::class)->middleware('throttle:site')->name('translate');
    Route::post('/latest-news/bulk-delete', [\App\Http\Controllers\Admin\LatestNewsController::class, 'bulkDestroy'])->name('latest-news.bulk-delete');
    Route::resource('latest-news', \App\Http\Controllers\Admin\LatestNewsController::class)->parameters(['latest-news' => 'item'])->except(['show']);
    Route::post('/popular-news/bulk-delete', [\App\Http\Controllers\Admin\PopularNewsController::class, 'bulkDestroy'])->name('popular-news.bulk-delete');
    Route::resource('popular-news', \App\Http\Controllers\Admin\PopularNewsController::class)->parameters(['popular-news' => 'item'])->except(['show']);
    Route::post('/market-indices/bulk-delete', [MarketIndexController::class, 'bulkDestroy'])->name('market-indices.bulk-delete');
    Route::resource('market-indices', MarketIndexController::class)->parameters(['market-indices' => 'marketIndex'])->except(['show']);
    Route::post('/technical-analysis/bulk-delete', [TechnicalAnalysisController::class, 'bulkDestroy'])->name('technical-analysis.bulk-delete');
    Route::post('/technical-analysis/write', [TechnicalAnalysisController::class, 'write'])->middleware('throttle:site')->name('technical-analysis.write');
    Route::resource('technical-analysis', TechnicalAnalysisController::class)->parameters(['technical-analysis' => 'technicalAnalysis'])->except(['show']);
    Route::post('/share-link/{type}/{id}', [\App\Http\Controllers\Admin\ShareLinkController::class, 'update'])->whereIn('type', ['news', 'analysis'])->whereNumber('id')->name('share-link.update');
    Route::post('/expert-posts/bulk-delete', [\App\Http\Controllers\Admin\ExpertPostController::class, 'bulkDestroy'])->name('expert-posts.bulk-delete');
    Route::resource('expert-posts', \App\Http\Controllers\Admin\ExpertPostController::class)->parameters(['expert-posts' => 'expertPost'])->except(['show']);
    Route::post('/divya-bhaskar/bulk-delete', [\App\Http\Controllers\Admin\DivyaBhaskarController::class, 'bulkDestroy'])->name('divya-bhaskar.bulk-delete');
    Route::resource('divya-bhaskar', \App\Http\Controllers\Admin\DivyaBhaskarController::class)->parameters(['divya-bhaskar' => 'divyaBhaskar'])->except(['show']);
    // Bulk PDF upload (many editions at once), like Media Articles
    Route::post('/digital-newspaper/bulk-check', [\App\Http\Controllers\Admin\DigitalNewspaperController::class, 'bulkCheck'])->name('digital-newspaper.bulk-check');
    Route::post('/digital-newspaper/bulk-store', [\App\Http\Controllers\Admin\DigitalNewspaperController::class, 'bulkStore'])->name('digital-newspaper.bulk-store');
    Route::post('/digital-newspaper/from-blogger', [\App\Http\Controllers\Admin\DigitalNewspaperController::class, 'fromBlogger'])->name('digital-newspaper.from-blogger');
    Route::post('/digital-newspaper/bulk-delete',[\App\Http\Controllers\Admin\DigitalNewspaperController::class, 'bulkDestroy'])->name('digital-newspaper.bulk-delete');
    Route::resource('digital-newspaper', \App\Http\Controllers\Admin\DigitalNewspaperController::class)->parameters(['digital-newspaper' => 'digitalNewspaper'])->except(['show']);
    Route::post('/editor-articles/bulk-delete', [\App\Http\Controllers\Admin\EditorArticleController::class, 'bulkDestroy'])->name('editor-articles.bulk-delete');
    Route::resource('editor-articles', \App\Http\Controllers\Admin\EditorArticleController::class)->parameters(['editor-articles' => 'editorArticle'])->except(['show']);
    // Newspaper Logos: the official logo of each paper for the home page "Trusted by" band
    Route::get('/media-articles/logos', [\App\Http\Controllers\Admin\NewspaperLogoController::class, 'index'])->name('media-articles.logos');
    Route::post('/media-articles/logos', [\App\Http\Controllers\Admin\NewspaperLogoController::class, 'store'])->name('media-articles.logos.store');
    Route::put('/media-articles/logos', [\App\Http\Controllers\Admin\NewspaperLogoController::class, 'update'])->name('media-articles.logos.update');
    Route::delete('/media-articles/logos', [\App\Http\Controllers\Admin\NewspaperLogoController::class, 'destroy'])->name('media-articles.logos.destroy');
    // Bulk PDF upload (Add Media Article -> Bulk upload): duplicate check, then one article per request
    Route::post('/media-articles/bulk-check', [\App\Http\Controllers\Admin\MediaArticleController::class, 'bulkCheck'])->name('media-articles.bulk-check');
    Route::post('/media-articles/bulk-store', [\App\Http\Controllers\Admin\MediaArticleController::class, 'bulkStore'])->name('media-articles.bulk-store');
    Route::post('/media-articles/bulk-delete', [\App\Http\Controllers\Admin\MediaArticleController::class, 'bulkDestroy'])->name('media-articles.bulk-delete');
    Route::resource('media-articles', \App\Http\Controllers\Admin\MediaArticleController::class)->parameters(['media-articles' => 'mediaArticle'])->except(['show']);
    Route::post('/youtube/bulk-delete', [YoutubeVideoController::class, 'bulkDestroy'])->name('youtube.bulk-delete');
    Route::resource('youtube', YoutubeVideoController::class)->except(['show']);
    Route::get('/legal-pages', [\App\Http\Controllers\Admin\LegalPageController::class, 'index'])->name('legal-pages.index');
    Route::get('/legal-pages/{slug}/edit', [\App\Http\Controllers\Admin\LegalPageController::class, 'edit'])->name('legal-pages.edit');
    Route::put('/legal-pages/{slug}', [\App\Http\Controllers\Admin\LegalPageController::class, 'update'])->name('legal-pages.update');
    Route::delete('/legal-pages/{slug}', [\App\Http\Controllers\Admin\LegalPageController::class, 'reset'])->name('legal-pages.reset');
    Route::post('/premium/bulk-delete', [PremiumContentController::class, 'bulkDestroy'])->name('premium.bulk-delete');
    Route::resource('premium', PremiumContentController::class)->except(['show']);
});

// A blog's short address, else an article's short share link (investmentpoint.in/nifty -> the newest Nifty article)
Route::get('/{slug}', [\App\Http\Controllers\ShortLinkController::class, 'show'])->name('blog.short');
