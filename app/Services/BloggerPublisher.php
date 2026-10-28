<?php

namespace App\Services;

use App\Models\DigitalNewspaper;
use App\Models\MediaArticle;
use App\Models\Setting;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Auto-post to Blogger: an E-Paper edition (Gujarati / Hindi / English / Weekly) or Media Article uploaded in admin
 * is posted to the blogspot that section is imported from, in the same style as the posts made there by hand
 * (title + one full-size picture per page). The pictures are the website's own (Blogger has no picture upload in
 * its API). The post id is saved in blogger_id, so the hourly newspaper:import / media:import skip the post.
 *
 * Google sign-in (OAuth 2.0, Blogger API v3): the admin pastes the Client ID and Client Secret of a Google Cloud
 * "Web application" client in Admin -> Site Settings -> Blogger and clicks "Connect", signs in as the blogs' owner,
 * and the refresh token is stored encrypted. Only uploads made after connecting are posted.
 */
class BloggerPublisher
{
    public const SCOPE = 'https://www.googleapis.com/auth/blogger';

    private const AUTH = 'https://accounts.google.com/o/oauth2/v2/auth';
    private const TOKEN = 'https://oauth2.googleapis.com/token';
    private const REVOKE = 'https://oauth2.googleapis.com/revoke';
    private const API = 'https://www.googleapis.com/blogger/v3/';

    public static function clientId(): ?string
    {
        return Setting::get('blogger_client_id') ?: null;
    }

    public static function clientSecret(): ?string
    {
        return self::decrypted('blogger_client_secret');
    }

    private static function refreshToken(): ?string
    {
        return self::decrypted('blogger_refresh_token');
    }

    private static function decrypted(string $key): ?string
    {
        try {
            return ($saved = Setting::get($key)) ? Crypt::decryptString($saved) : null;
        } catch (Throwable $e) {
            return null; // saved with another APP_KEY: connect again
        }
    }

    public function isConnected(): bool
    {
        return self::clientId() && self::clientSecret() && self::refreshToken();
    }

    /** The sections posted to Blogger: key => [label, blog address] (key = E-Paper edition, or "media") */
    public static function sections(): array
    {
        $sections = [];
        foreach (DigitalNewspaper::EDITIONS as $edition => $label) {
            $sections[$edition] = ['label' => $edition === 'weekly' ? $label : $label . ' E-Paper',
                'blog' => $edition === 'weekly' ? config('epaper.weekly_blog') : config("epaper.editions.{$edition}.blog")];
        }
        $sections['media'] = ['label' => 'Media Articles', 'blog' => config('blogger.media_blog')];

        return array_filter($sections, fn ($s) => !empty($s['blog']));
    }

    /** The blogs found when connecting: section => [id, name, url] */
    public static function blogs(): array
    {
        return json_decode((string) Setting::get('blogger_blogs'), true) ?: [];
    }

    /** Google's sign-in page; it comes back to $redirect with a code (and $state, checked by the caller) */
    public function authUrl(string $redirect, string $state): string
    {
        return self::AUTH . '?' . http_build_query([
            'client_id' => self::clientId(),
            'redirect_uri' => $redirect,
            'response_type' => 'code',
            'scope' => self::SCOPE,
            'access_type' => 'offline',
            'prompt' => 'consent', // always hand out a refresh token
            'include_granted_scopes' => 'true',
            'login_hint' => config('blogger.account'),
            'state' => $state,
        ]);
    }

    /**
     * Back from Google's sign-in: the code becomes a refresh token (saved encrypted), then the blog of each
     * section is looked up. Returns the blogs found.
     *
     * @throws RuntimeException with a message for the admin
     */
    public function connect(string $code, string $redirect): array
    {
        $response = Http::asForm()->timeout(20)->post(self::TOKEN, [
            'code' => $code,
            'client_id' => self::clientId(),
            'client_secret' => self::clientSecret(),
            'redirect_uri' => $redirect,
            'grant_type' => 'authorization_code',
        ]);
        if (!$response->successful() || !$response->json('refresh_token')) {
            throw new RuntimeException('Google did not give access (' . ($response->json('error_description') ?? $response->json('error') ?? 'HTTP ' . $response->status()) . '). Check the Client ID and Client Secret, and that the redirect address is added in Google Cloud.');
        }

        Setting::set('blogger_refresh_token', Crypt::encryptString($response->json('refresh_token')));
        Cache::put('blogger.access_token', $response->json('access_token'), now()->addSeconds(max(60, (int) $response->json('expires_in', 3600) - 300)));
        Setting::set('blogger_connected_at', now()->toIso8601String());
        Setting::set('blogger_error', '');

        return $this->findBlogs();
    }

    /**
     * The blog id of each section's blogspot (Blogger "blogs/byurl"). A blog the signed-in account cannot post to
     * is left out, with the reason in the admin message.
     *
     * @throws RuntimeException when none is found
     */
    public function findBlogs(): array
    {
        $blogs = $missing = [];
        foreach (self::sections() as $key => $section) {
            $response = Http::withToken($this->accessToken())->timeout(20)->get(self::API . 'blogs/byurl', ['url' => rtrim($section['blog'], '/') . '/']);
            if ($response->successful() && $response->json('id')) {
                $blogs[$key] = ['id' => (string) $response->json('id'), 'name' => (string) $response->json('name'), 'url' => (string) $response->json('url')];
            } else {
                $missing[] = $section['label'] . ' (' . ($response->json('error.message') ?? 'HTTP ' . $response->status()) . ')';
            }
        }
        Setting::set('blogger_blogs', json_encode($blogs, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        Setting::set('blogger_error', $missing ? 'Blogs not found for this Google account: ' . implode('; ', $missing) : '');

        if (!$blogs) {
            throw new RuntimeException('Connected, but none of the blogs was found for this Google account. Sign in with the account that owns the blogspots (' . config('blogger.account') . ').');
        }

        return $blogs;
    }

    /** Remove the Google access (also on Google's side) and forget the blogs. Posts already made stay on Blogger. */
    public function disconnect(): void
    {
        if ($token = self::refreshToken()) {
            try {
                Http::asForm()->timeout(10)->post(self::REVOKE, ['token' => $token]);
            } catch (Throwable $e) {
                // already gone on Google's side
            }
        }
        foreach (['blogger_refresh_token', 'blogger_blogs', 'blogger_connected_at', 'blogger_error'] as $key) {
            Setting::set($key, '');
        }
        Cache::forget('blogger.access_token');
    }

    /** A short-lived access token from the refresh token (kept for about 55 minutes) */
    public function accessToken(): string
    {
        if ($token = Cache::get('blogger.access_token')) {
            return $token;
        }
        if (!$this->isConnected()) {
            throw new RuntimeException('Blogger is not connected.');
        }

        $response = Http::asForm()->timeout(20)->post(self::TOKEN, [
            'client_id' => self::clientId(),
            'client_secret' => self::clientSecret(),
            'refresh_token' => self::refreshToken(),
            'grant_type' => 'refresh_token',
        ]);
        if (!$response->successful() || !($token = $response->json('access_token'))) {
            $error = $response->json('error');
            throw new RuntimeException($error === 'invalid_grant'
                ? 'Google access has expired or was removed. Click "Connect Blogger" in Admin -> Site Settings again.'
                : 'Could not get Google access (' . ($response->json('error_description') ?? $error ?? 'HTTP ' . $response->status()) . ').');
        }
        Cache::put('blogger.access_token', $token, now()->addSeconds(max(60, (int) $response->json('expires_in', 3600) - 300)));

        return $token;
    }

    /**
     * Post every upload that is waiting: active (and live, for a scheduled Weekly edition), made in admin after
     * Blogger was connected, not posted yet. One run at a time, so nothing is posted twice.
     *
     * @return array{posted: int, failed: int, message: ?string}
     */
    public function publishPending(): array
    {
        $result = ['posted' => 0, 'failed' => 0, 'message' => null];
        if (!config('blogger.enabled') || !$this->isConnected() || !($since = Setting::get('blogger_connected_at'))) {
            return ['message' => 'Blogger is not connected.'] + $result;
        }
        $blogs = self::blogs();

        $lock = Cache::lock('blogger:publish', 600);
        if (!$lock->get()) {
            return ['message' => 'Already posting.'] + $result;
        }
        try {
            // active() = live on the website: a scheduled one waits for its date and time
            $waiting = fn ($query) => $query->whereNull('blogger_id')
                ->where('created_at', '>=', \Illuminate\Support\Carbon::parse($since))
                ->where('blogger_tries', '<', (int) config('blogger.max_tries', 5))->orderBy('id');

            $items = $waiting(DigitalNewspaper::active()->whereIn('edition', array_keys($blogs)))->get();
            if (isset($blogs['media'])) {
                $items = $items->concat($waiting(MediaArticle::active())->get());
            }

            foreach ($items as $item) {
                try {
                    $this->post($item, $blogs[self::sectionOf($item)]['id']);
                    $result['posted']++;
                } catch (Throwable $e) {
                    // A query of its own: the model may still hold the values that could not be saved
                    $item->newQuery()->whereKey($item->getKey())->update(['blogger_error' => Str::limit($e->getMessage(), 490), 'blogger_tries' => $item->blogger_tries + 1]);
                    Log::warning('Blogger: could not post ' . class_basename($item) . ' #' . $item->id . ': ' . $e->getMessage());
                    $result['failed']++;
                    if (str_contains($e->getMessage(), 'Google access')) {
                        Setting::set('blogger_error', $e->getMessage());
                        $result['message'] = $e->getMessage();
                        break; // the account itself: no use trying the rest
                    }
                }
            }
        } finally {
            $lock->release();
        }

        return $result;
    }

    /** After an upload is saved in admin: post it once the page has been sent (the admin does not wait for Blogger) */
    public static function soon(): void
    {
        if (config('blogger.enabled') && Setting::get('blogger_refresh_token')) {
            dispatch(fn () => app(self::class)->publishPending())->afterResponse();
        }
    }

    /** E-Paper edition (gujarati / hindi / english / weekly) or "media" */
    public static function sectionOf(Model $item): string
    {
        return $item instanceof DigitalNewspaper ? ($item->edition ?? 'weekly') : 'media';
    }

    /**
     * One Blogger post for an upload; its post id goes into blogger_id and its address into blogger_url.
     *
     * @throws RuntimeException
     */
    public function post(Model $item, string $blogId): void
    {
        $response = Http::withToken($this->accessToken())->timeout(30)
            ->post(self::API . 'blogs/' . $blogId . '/posts/', ['kind' => 'blogger#post', 'title' => $item->title, 'content' => self::postHtml($item), 'published' => self::postDate($item)]);
        if (!$response->successful() || !$response->json('id')) {
            throw new RuntimeException('Blogger did not accept the post (' . ($response->json('error.message') ?? 'HTTP ' . $response->status()) . ').');
        }

        $item->forceFill(['blogger_id' => (string) $response->json('id'), 'blogger_url' => $response->json('url'), 'blogger_error' => null])->save();
        Log::info('Blogger: posted ' . class_basename($item) . ' #' . $item->id . ' to ' . $response->json('url'));
    }

    /**
     * An edition on the website whose title was changed in admin: its Blogger post gets the same title (and pictures).
     *
     * @throws RuntimeException
     */
    public function updatePost(DigitalNewspaper $paper): void
    {
        $blogId = self::blogs()[$paper->edition]['id'] ?? throw new RuntimeException('The blog was not found.');
        $response = Http::withToken($this->accessToken())->timeout(30)
            ->patch(self::API . 'blogs/' . $blogId . '/posts/' . $paper->blogger_id, ['title' => $paper->title, 'content' => self::postHtml($paper)]);
        if (!$response->successful()) {
            throw new RuntimeException('Blogger did not take the change (' . ($response->json('error.message') ?? 'HTTP ' . $response->status()) . ').');
        }
    }

    /**
     * When the post shows as published on the blog: its edition date (6:00 AM India time), so the blog lists the
     * newest date first whatever order a bulk upload went in. Today's edition (or one without a date): now.
     */
    public static function postDate(Model $item): string
    {
        $date = $item->published_on ? $item->published_on->copy()->shiftTimezone('Asia/Kolkata')->setTime(6, 0) : null;

        return (!$date || $date->isFuture() || $date->isSameDay(now('Asia/Kolkata')) ? now() : $date)->toRfc3339String();
    }

    /**
     * Give an existing post its edition date (postDate), e.g. a daily e-paper made in Blogger days later.
     *
     * @throws RuntimeException
     */
    public function redate(Model $item, string $blogId): void
    {
        $response = Http::withToken($this->accessToken())->timeout(30)
            ->patch(self::API . 'blogs/' . $blogId . '/posts/' . $item->blogger_id, ['published' => self::postDate($item)]);
        if (!$response->successful()) {
            throw new RuntimeException($response->json('error.message') ?? 'HTTP ' . $response->status());
        }
    }

    /**
     * Posts made before postDate() carry the time they were uploaded: move each one posted from here (blogger_url
     * set; imported posts have none) to its edition date. Returns how many were moved / failed.
     *
     * @return array{moved: int, failed: int}
     */
    public function fixDates(): array
    {
        $result = ['moved' => 0, 'failed' => 0];
        $blogs = self::blogs();
        foreach ([DigitalNewspaper::class, MediaArticle::class] as $model) {
            foreach ($model::whereNotNull('blogger_id')->whereNotNull('blogger_url')->orderBy('id')->get() as $item) {
                if (!($blogId = $blogs[self::sectionOf($item)]['id'] ?? null)) {
                    continue;
                }
                try {
                    $this->redate($item, $blogId);
                    $result['moved']++;
                } catch (RuntimeException $e) {
                    $result['failed']++;
                    Log::warning('Blogger: could not re-date ' . class_basename($item) . ' #' . $item->id . ': ' . $e->getMessage());
                }
            }
        }

        return $result;
    }

    /**
     * The post, in the style of the posts made in Blogger by hand: one centred full-size picture per page
     * (linked to itself), the Media Article's description first, and a link to the page on the website.
     * All links use the live address (config app.public_url), also when posted from a test computer.
     */
    public static function postHtml(Model $item): string
    {
        $pictures = $item instanceof DigitalNewspaper
            ? array_map(fn ($p) => DigitalNewspaper::imageUrl($p, 's0'), $item->pages ?? [])
            : array_column($item->pageImages(), 'full');

        $html = [];
        if ($item instanceof MediaArticle && $item->description) {
            $html[] = '<p>' . nl2br(e($item->description)) . '</p>';
        }
        foreach (array_values($pictures) as $i => $url) {
            $url = e(self::publicUrl($url));
            $html[] = '<div class="separator" style="clear: both; text-align: center;"><a href="' . $url . '" style="margin-left: 1em; margin-right: 1em;">'
                . '<img border="0" src="' . $url . '" width="640" alt="' . e($item->title . ' - page ' . ($i + 1)) . '" /></a></div>';
        }
        // Emailed to Blogger (not on the website yet): the paper's page on the website
        $link = $item->exists ? $item->url : route('epaper.index', ['language' => $item->edition]);
        $html[] = '<p style="text-align: center;"><a href="' . e(self::publicUrl($link)) . '">Read on Investment Point</a></p>';

        return implode("\n", $html);
    }

    private static function publicUrl(string $url): string
    {
        $public = config('app.public_url');
        $roots = array_unique(array_filter([rtrim(url('/'), '/'), rtrim((string) config('app.url'), '/')]));

        return $public ? str_replace($roots, $public, $url) : $url;
    }
}
