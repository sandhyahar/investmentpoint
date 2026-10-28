# Investment Point

Source code of **[investmentpoint.in](https://investmentpoint.in)**, a live Indian stock market news and insights website
(English, Gujarati and Hindi), built with **Laravel 12 / PHP 8.3 / MySQL** and running on Hostinger.

## Features

**Website**
- Home page with the daily Nifty *Market Trend* banner, Business News, Most Popular, Technical Analysis, Editor's View,
  Expert's View and live index tickers (Sensex, Nifty 50, Bank Nifty, India VIX)
- Daily E-Paper and Weekly News Paper (PDF editions), Media articles, reader blogs with likes and sharing
- Services: Stock Market subscription (Razorpay) and Insurance pages with enquiry / advisor forms
  (India Post city and PIN code lookup, email notification to the insurance contact)
- Google Translate language switch, SEO (sitemap, structured data, Open Graph), responsive layout for phones and tablets

**Automation**
- AI-written Business News and Most Popular articles from market news sources, fact-checked before publishing
- Nifty Market Trend article updated several times each trading day from real market figures, with an AI-made
  bull / bear picture (Cloudflare Workers AI FLUX, with free fallbacks)
- E-Paper editions posted to Blogger by email (daily limit and queue) and imported back by date
- Picture optimisation (GD), scheduled jobs through the Laravel scheduler

**Admin panel**
- Role and page permissions, rich text editor (TinyMCE), AI writing and title helpers
- Management of news, analysis, e-papers, blogs, subscribers, insurance plans and enquiries, site settings

## Tech

Laravel 12, PHP 8.3, MySQL / MariaDB, Blade, Bootstrap, vanilla JavaScript, TinyMCE, PHPUnit feature tests
(about 270 tests), Gmail SMTP, Cloudflare Workers AI, Google Gemini (text), Razorpay.

## Run locally

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan serve
```

Set the database and the optional API keys (mail, Cloudflare, Gemini, Razorpay) in `.env`.
No real keys or passwords are included in this repository.

### Sample database

`database/sample/investmentpoint-sample.sql` (MySQL, 25 MB) has the structure of all 37 tables and the public content
of the live site on 28 Oct 2026: news, Market Trend, Business News, Most Popular, technical analysis, Expert's View posts,
e-paper and media article listings, approved reader blogs and the insurance plans. Users, enquiries, subscriptions,
settings, page views and every other table with personal data are empty, and email addresses and phone numbers inside
articles are masked. To load it instead of running the migrations:

```bash
mysql -u root -p investmentpoint < database/sample/investmentpoint-sample.sql
php artisan db:seed --class=AdminUserSeeder   # creates an admin login
```

## Author

Nikhil Bhatt
