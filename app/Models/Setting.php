<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/**
 * Simple site-wide key/value settings (e.g. the logo), editable from Admin → Site Settings.
 */
class Setting extends Model
{
    public const DEFAULT_LOGO = 'images/logo-default.png';

    protected $fillable = ['key', 'value'];

    public static function get(string $key, $default = null)
    {
        $all = Cache::rememberForever('site_settings', function () {
            // Settings table may not exist yet on a fresh install before migrations run
            return Schema::hasTable('settings') ? static::pluck('value', 'key')->all() : [];
        });

        return $all[$key] ?? $default;
    }

    public static function set(string $key, $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value]);
        Cache::forget('site_settings');
    }

    /** The Google Gemini key (text + pictures): pasted in Admin -> Settings (stored encrypted), or GEMINI_API_KEY in .env */
    public static function geminiKey(): ?string
    {
        if ($saved = static::get('gemini_api_key')) {
            try {
                return \Illuminate\Support\Facades\Crypt::decryptString($saved);
            } catch (\Throwable $e) {
                return null; // saved with another APP_KEY: paste the key again
            }
        }

        return config('services.gemini_ai.key') ?: null;
    }

    /** The Gemini text model that worked when the key was saved (otherwise the first one in the list) */
    public static function geminiModel(): string
    {
        return static::get('gemini_model') ?: (config('services.gemini_ai.models')[0] ?? 'gemini-3.1-flash-lite');
    }

    /** Admin "Generate with AI" pictures allowed per day (Admin -> Settings -> Gemini AI; the pictures come from free AI services) */
    public static function aiImageDailyLimit(): int
    {
        return (int) (static::get('ai_image_daily_limit') ?: config('services.gemini_ai.daily_images', 30));
    }

    /** The free Groq key for AI writing: pasted in Admin -> Settings (stored encrypted), or GROQ_API_KEY in .env */
    public static function groqKey(): ?string
    {
        if ($saved = static::get('groq_api_key')) {
            try {
                return \Illuminate\Support\Facades\Crypt::decryptString($saved);
            } catch (\Throwable $e) {
                return null; // saved with another APP_KEY: paste the key again
            }
        }

        return config('services.groq_ai.key') ?: null;
    }

    /** The Groq model that worked when the key was saved (otherwise the first one in the list) */
    public static function groqModel(): string
    {
        return static::get('groq_model') ?: (config('services.groq_ai.models')[0] ?? 'llama-3.3-70b-versatile');
    }

    /** The Gmail App Password of the website's email: saved in Admin -> Settings -> Website Email (stored encrypted) */
    public static function mailPassword(): ?string
    {
        if ($saved = static::get('mail_password')) {
            try {
                return \Illuminate\Support\Facades\Crypt::decryptString($saved);
            } catch (\Throwable $e) {
                return null; // saved with another APP_KEY: enter it again
            }
        }

        return null;
    }

    /** A password saved in admin replaces MAIL_PASSWORD from .env for every email the website sends */
    public static function applyMailPassword(): void
    {
        if ($password = static::mailPassword()) {
            config(['mail.mailers.smtp.password' => $password]);
        }
    }

    /** Google Search Console verification code: Admin -> Settings -> Google, or GOOGLE_SITE_VERIFICATION in .env */
    public static function googleVerification(): ?string
    {
        return static::get('google_site_verification') ?: config('seo.google_verification');
    }

    /** Google Analytics 4 Measurement ID (G-XXXXXXXXXX): Admin -> Settings -> Google, or GOOGLE_ANALYTICS_ID in .env */
    public static function googleAnalyticsId(): ?string
    {
        return static::get('google_analytics_id') ?: config('seo.google_analytics_id');
    }

    /**
     * Public URL of the current site logo (uploaded one, or the bundled default).
     */
    public static function logoUrl(): string
    {
        $logo = static::get('logo');

        return $logo ? asset('storage/' . $logo) : asset(self::DEFAULT_LOGO);
    }

    /**
     * Social networks shown as icons in the top bar.
     * The link for each is set in Admin -> Site Settings; networks without a link are hidden.
     */
    public const SOCIAL = [
        'facebook'  => ['label' => 'Facebook',  'icon' => 'bi-facebook',  'color' => '#1877f2', 'default' => 'https://www.facebook.com/NBipoint', 'placeholder' => 'https://www.facebook.com/yourpage'],
        'instagram' => ['label' => 'Instagram', 'icon' => 'bi-instagram', 'color' => '#e1306c', 'default' => 'https://www.instagram.com/nikhilbbhatt', 'placeholder' => 'https://www.instagram.com/yourprofile'],
        'linkedin'  => ['label' => 'LinkedIn',  'icon' => 'bi-linkedin',  'color' => '#0a66c2', 'default' => 'https://www.linkedin.com/in/investmentpoint/', 'placeholder' => 'https://www.linkedin.com/in/yourprofile'],
        'x'         => ['label' => 'X (Twitter)', 'icon' => 'bi-twitter-x', 'color' => '#000000', 'default' => null, 'placeholder' => 'https://x.com/yourhandle'],
        // Default: the contact email shown on the Subscription page (config/subscription.php)
        'email'     => ['label' => 'Email',     'icon' => 'bi-envelope-fill', 'color' => '#ea4335', 'default' => 'shreeinvestmentpoint@gmail.com', 'placeholder' => 'name@example.com'],
        'telegram'  => ['label' => 'Telegram',  'icon' => 'bi-telegram',  'color' => '#26a5e4', 'default' => 'https://t.me/nikhilbhatt', 'placeholder' => 'https://t.me/yourchannel'],
        'youtube'   => ['label' => 'YouTube',   'icon' => 'bi-youtube',   'color' => '#ff0000', 'default' => 'https://www.youtube.com/c/NikhilBhattResearchAnalystSEBI', 'placeholder' => 'https://www.youtube.com/@yourchannel'],
        'whatsapp'  => ['label' => 'WhatsApp',  'icon' => 'bi-whatsapp',  'color' => '#25d366', 'default' => 'https://wa.me/919939080808', 'placeholder' => 'https://wa.me/91XXXXXXXXXX'],
    ];

    /** @return array<int, array{key: string, label: string, icon: string, color: string, url: string, mail: ?string}> */
    public static function socialLinks(): array
    {
        $links = [];
        foreach (self::SOCIAL as $key => $meta) {
            $value = trim((string) static::get('social_' . $key, $meta['default']));
            if ($value === '') {
                continue;
            }
            // Email: the plain address too, so computers can open a Gmail "new message" window (see layouts.app)
            $mail = $key === 'email' ? preg_replace('/^mailto:/i', '', $value) : null;
            $url = $mail !== null ? 'mailto:' . $mail : $value;
            $links[] = ['key' => $key, 'label' => $mail ?? $meta['label'], 'icon' => $meta['icon'], 'color' => $meta['color'], 'url' => $url, 'mail' => $mail];
        }

        return $links;
    }

    /**
     * Square version of the logo for round avatars (e.g. blog writers without a photo).
     * Uses the bull symbol of the default logo; an uploaded custom logo is used as-is.
     */
    public static function logoIconUrl(): string
    {
        $logo = static::get('logo');

        return $logo ? asset('storage/' . $logo) : asset('images/logo-icon.png');
    }
}
