<?php

namespace App\Services;

use App\Models\DigitalNewspaper;
use App\Models\Setting;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Daily E-Paper (Gujarati / Hindi / English) straight to Blogger. Blogger's API cannot take pictures, but Blogger's
 * own "Post using email" can: the page pictures are emailed to the blog's secret posting address and Blogger keeps
 * them on blogger.googleusercontent.com like a post made by hand. The post is then given the edition date and the
 * usual layout, and the website lists it like any edition imported from the blog (a row with the Blogger links only).
 *
 * Blogger's mail server blocks emails that come too quickly, so uploads wait in an outbox and go one at a time
 * (run(), every 20 seconds): send one, wait for Blogger's post, then the next, GAP_MINUTES apart and at most DAILY_LIMIT
 * a day (the rest go on the next days by themselves). When Gmail reports an
 * email blocked ("Message blocked" reply, GmailMailbox::bounced) the next one goes at once; the blocked one is not sent
 * again and leaves the list WAIT_MINUTES after sending unless Blogger posts it after all. The outbox
 * (storage/app/epaper-outbox: queue.json + the page pictures) is private and each upload's pictures are deleted as
 * soon as its post is made; it is a file so a deploy (cache:clear) does not lose it. The sent emails then go to the
 * Gmail Trash.
 */
class EpaperByEmail
{
    private const API = 'https://www.googleapis.com/blogger/v3/';

    /** At least this long between two emails to Blogger */
    public const GAP_MINUTES = 1;
    /** Not posted by Blogger this long after sending (e.g. Gmail's "Message blocked"): cleared from the list */
    public const WAIT_MINUTES = 5;
    /**
     * At most this many emails to Blogger a day (India time, all papers together): Blogger's mail server blocks the
     * ones after about five (owner, 27 Oct 2026). The rest stay in the queue and go on the next days, oldest first.
     */
    public const DAILY_LIMIT = 5;

    /** Emails sent to Blogger today (India time) */
    public static function sentToday(): int
    {
        $day = json_decode((string) Setting::get('blogger_email_day'), true) ?: [];

        return ($day['date'] ?? null) === now('Asia/Kolkata')->toDateString() ? (int) ($day['count'] ?? 0) : 0;
    }

    /** Today's emails are used up: the queue waits for tomorrow */
    public static function dailyLimitReached(): bool
    {
        return self::sentToday() >= self::DAILY_LIMIT;
    }

    public function __construct(private BloggerPublisher $blogger)
    {
    }

    /** The blog's secret "Post using email" address (Blogger -> Settings -> Email), saved in Admin -> Site Settings -> Blogger */
    public static function address(string $edition): ?string
    {
        try {
            return ($saved = Setting::get('blogger_email_' . $edition)) ? Crypt::decryptString($saved) : null;
        } catch (Throwable $e) {
            return null;
        }
    }

    /** What is missing before this paper can be uploaded, or null when it can */
    public function problem(string $edition): ?string
    {
        $label = DigitalNewspaper::EDITIONS[$edition] ?? $edition;
        if (!$this->blogger->isConnected()) {
            return 'Blogger is not connected. Open Admin → Site Settings → Blogger and click "Connect Blogger" first.';
        }
        if (!isset(BloggerPublisher::blogs()[$edition])) {
            return 'The ' . $label . ' blog was not found. Click "Connect again" in Admin → Site Settings → Blogger.';
        }

        return self::address($edition) ? null : 'The ' . $label . ' blog\'s "Post using email" address is not saved yet. Add it in Admin → Site Settings → Blogger.';
    }

    /**
     * Put an edition in the outbox and send it at once when it is its turn. $pages = [[bytes, file name, mime], ...]
     * in page order.
     *
     * @throws RuntimeException with a message for the admin
     */
    public function queue(DigitalNewspaper $paper, array $pages): void
    {
        if ($problem = $this->problem($paper->edition)) {
            throw new RuntimeException($problem);
        }
        // The code finds Blogger's post again
        $code = 'IPUPLOAD-' . Str::upper(Str::random(16));
        File::ensureDirectoryExists(self::dir($code));
        foreach ($pages as [$bytes, $name]) {
            if (File::put(self::dir($code) . '/' . $name, $bytes) !== strlen($bytes)) {
                File::deleteDirectory(self::dir($code));
                throw new RuntimeException('The pages could not be saved for sending (disk full?).');
            }
        }

        self::change(fn (array $all) => $all + [$code => [
            'edition' => $paper->edition,
            'title' => $paper->title,
            'published_on' => $paper->published_on->toDateString(),
            'status' => $paper->status,
            'publish_at' => $paper->publish_at?->toIso8601String(),
            'files' => array_map(fn ($p) => [$p[1], $p[2]], $pages),
            'state' => 'queued',
            'queued_at' => now()->toIso8601String(),
            'sent_at' => null,
            'message_id' => null,
            'tries' => 0,
            'retry_at' => null,
            'error' => null,
        ]]);

        try {
            $this->sendNext();
        } catch (Throwable $e) {
            Log::warning('Blogger: could not send the next e-paper email: ' . $e->getMessage()); // run() tries again
        }
    }

    /** The outbox, for the admin list: code => entry (queued, sent = waiting for Blogger's post, failed) */
    public static function waiting(?string $edition = null): array
    {
        return array_filter(self::read(), fn ($e) => !$edition || $e['edition'] === $edition);
    }

    /** The same edition already in the outbox (bulk upload: not twice), like DigitalNewspaper::findDuplicate */
    public static function waitingDuplicate(string $edition, string $title, ?string $date): ?array
    {
        $title = trim(preg_replace('/\s+/u', ' ', $title));
        foreach (self::waiting($edition) as $entry) {
            if ($entry['state'] !== 'failed' && (($date && $entry['published_on'] === $date) || ($title !== '' && $entry['title'] === $title))) {
                return $entry;
            }
        }

        return null;
    }

    /**
     * Every minute: finish the posts Blogger has made, put blocked emails back in the queue, give up on lost ones,
     * then send the next email when it is time.
     *
     * @return array{finished: int, failed: int, sent: int}
     */
    public function run(): array
    {
        $result = ['finished' => 0, 'failed' => 0, 'sent' => 0];
        if (!$this->blogger->isConnected()) {
            return $result;
        }

        $sent = array_filter(self::read(), fn ($e) => $e['state'] === 'sent');
        foreach (collect($sent)->groupBy('edition', true) as $edition => $entries) {
            $blogId = BloggerPublisher::blogs()[$edition]['id'] ?? null;
            try {
                $posts = $blogId ? $this->recentPosts($blogId) : [];
            } catch (Throwable $e) {
                Log::warning('Blogger: could not read the ' . $edition . ' posts: ' . $e->getMessage());
                continue;
            }
            foreach ($entries as $code => $entry) {
                if ($post = collect($posts)->first(fn ($p) => str_contains($p['content'] ?? '', $code))) {
                    try {
                        $this->finish($entry, $post, $blogId);
                        self::forget($code);
                        $result['finished']++;
                    } catch (Throwable $e) {
                        $this->fail($code, $e->getMessage());
                        $result['failed']++;
                    }
                }
            }
        }

        $this->noticeBlocked();

        // Not posted within WAIT_MINUTES (Blogger blocked the email or never made the post): cleared from the list,
        // its pages deleted, and the next edition goes; it is not sent again (the admin uploads it again)
        $late = array_filter(self::read(), fn ($e) => $e['state'] === 'sent' && Carbon::parse($e['sent_at'])->lt(now()->subMinutes(self::WAIT_MINUTES)));
        if ($late) {
            foreach ($late as $code => $entry) {
                self::forget($code);
                Log::warning('Blogger: e-paper email "' . $entry['title'] . '" was not posted within ' . self::WAIT_MINUTES . ' minutes; cleared');
                $result['failed']++;
            }
        }

        // Blogger has the pictures now: the sent emails are not needed in Gmail any more
        if ($result['finished']) {
            try {
                app(GmailMailbox::class)->trashBloggerEmails();
            } catch (Throwable $e) {
                Log::warning('Gmail: could not move the Blogger emails to the Trash: ' . $e->getMessage());
            }
        }

        $result['sent'] = $this->sendNext() ? 1 : 0;

        return $result;
    }

    /**
     * Gmail's "Message blocked" reply usually comes within a minute: the next edition need not wait the whole WAIT_MINUTES
     * for it. The blocked one stays on the list until then (Blogger has made a post after such a reply), but no longer
     * holds up the queue. Gmail is asked at most once a minute.
     */
    private function noticeBlocked(): void
    {
        $sent = array_filter(self::read(), fn ($e) => $e['state'] === 'sent' && empty($e['blocked_at']) && $e['message_id']
            && Carbon::parse($e['sent_at'])->lt(now()->subSeconds(20)));
        if (!$sent || !GmailMailbox::available() || !Cache::add('epaper-email.blocked-check', true, 50)) {
            return;
        }
        try {
            $blocked = app(GmailMailbox::class)->bounced(array_column($sent, 'message_id'));
        } catch (Throwable $e) {
            Log::warning('Gmail: could not check for "Message blocked" replies: ' . $e->getMessage());

            return;
        }
        foreach ($sent as $code => $entry) {
            if (in_array($entry['message_id'], $blocked, true)) {
                self::update($code, ['blocked_at' => now()->toIso8601String()]);
                Log::warning('Blogger: Gmail reports the e-paper email "' . $entry['title'] . '" blocked; the next edition goes now');
            }
        }
    }

    /**
     * Send the next queued email: one at a time (none waiting for Blogger's post, unless Gmail reported it blocked),
     * GAP_MINUTES apart and at most DAILY_LIMIT a day
     */
    private function sendNext(): bool
    {
        $all = self::read();
        $last = collect($all)->pluck('sent_at')->filter()->max() ?: Setting::get('blogger_email_last_sent');
        if (collect($all)->contains(fn ($e) => $e['state'] === 'sent' && empty($e['blocked_at']))
            || ($last && Carbon::parse($last)->gt(now()->subMinutes(self::GAP_MINUTES)))
            || self::dailyLimitReached()) {
            return false;
        }
        $code = collect($all)->filter(fn ($e) => $e['state'] === 'queued' && (!$e['retry_at'] || Carbon::parse($e['retry_at'])->lte(now())))
            ->sortBy('queued_at')->keys()->first();
        if (!$code) {
            return false;
        }

        $entry = $all[$code];
        $messageId = Str::lower($code) . '.' . ($entry['tries'] + 1) . '@investmentpoint.in';
        try {
            // Everything after #end (a signature) is left out by Blogger
            Mail::raw($code . "\n\n#end\n", function ($message) use ($entry, $code, $messageId) {
                $message->to(self::address($entry['edition']))->subject($entry['title']);
                $message->getSymfonyMessage()->getHeaders()->addIdHeader('Message-ID', $messageId);
                foreach ($entry['files'] as [$name, $mime]) {
                    $message->attach(self::dir($code) . '/' . $name, ['as' => $name, 'mime' => $mime]);
                }
            });
        } catch (Throwable $e) {
            $this->fail($code, 'The email to Blogger could not be sent (' . Str::limit($e->getMessage(), 200) . '). Check Admin → Site Settings → Website Email, then upload it again.');

            return false;
        }

        $now = now()->toIso8601String();
        Setting::set('blogger_email_last_sent', $now);
        Setting::set('blogger_email_day', json_encode(['date' => now('Asia/Kolkata')->toDateString(), 'count' => self::sentToday() + 1]));
        self::update($code, ['state' => 'sent', 'sent_at' => $now, 'message_id' => $messageId, 'tries' => $entry['tries'] + 1, 'retry_at' => null]);
        Log::info('Blogger: emailed ' . $entry['edition'] . ' e-paper "' . $entry['title'] . '" (' . count($entry['files']) . ' pages, try ' . ($entry['tries'] + 1) . ')');

        return true;
    }

    /** The latest posts of the blog, newest change first, drafts too (an emailed post may be saved as a draft) */
    private function recentPosts(string $blogId): array
    {
        $response = Http::withToken($this->blogger->accessToken())->timeout(30)
            ->get(self::API . 'blogs/' . $blogId . '/posts?' . http_build_query(['fetchBodies' => 'true', 'maxResults' => 30, 'orderBy' => 'updated', 'view' => 'ADMIN']) . '&status=live&status=draft&status=scheduled');
        if (!$response->successful()) {
            throw new RuntimeException($response->json('error.message') ?? 'HTTP ' . $response->status());
        }

        return $response->json('items') ?? [];
    }

    /** Blogger's post from the email: edition date, usual layout, schedule / draft as chosen, listed on the website */
    private function finish(array $entry, array $post, string $blogId): void
    {
        // The pictures Blogger made from the attachments (page-01.jpg, page-02.jpg, ...), full size, in page order
        preg_match_all('/(?:src|href)="(https:\/\/[^"]*(?:blogger\.googleusercontent\.com|bp\.blogspot\.com)[^"]+)"/i', $post['content'] ?? '', $m);
        $pages = collect($m[1])->map(fn ($u) => DigitalNewspaper::imageUrl(html_entity_decode($u), 's0'))->unique()
            ->sortBy(fn ($u) => preg_match('/page-(\d+)/', $u, $n) ? (int) $n[1] : 0)->values()->all();
        if (!$pages) {
            throw new RuntimeException('Blogger made the post without the pictures. Delete "' . $entry['title'] . '" in Blogger and upload it again.');
        }

        // The blog check (newspaper:import, every 5 minutes, or "Get all editions from Blogger") may have copied the post
        // to the website already: that row becomes this edition instead of a second one
        $existing = DigitalNewspaper::where('blogger_id', (string) $post['id'])->first();
        $paper = ($existing ?? new DigitalNewspaper())->fill(['edition' => $entry['edition'], 'title' => $entry['title'], 'published_on' => $entry['published_on'],
            'status' => $entry['status'], 'publish_at' => $entry['publish_at'] ? Carbon::parse($entry['publish_at']) : null, 'pages' => $pages]);
        $live = $paper->status === 'active' && !$paper->publish_at?->isFuture();

        $api = fn () => Http::withToken($this->blogger->accessToken())->timeout(30);
        $postUrl = self::API . 'blogs/' . $blogId . '/posts/' . $post['id'];
        $patched = $api()->patch($postUrl, ['title' => $paper->title, 'content' => BloggerPublisher::postHtml($paper), 'published' => BloggerPublisher::postDate($paper)]);
        if (!$patched->successful()) {
            throw new RuntimeException('Blogger did not take the edition date and layout (' . ($patched->json('error.message') ?? 'HTTP ' . $patched->status()) . ').');
        }

        $isDraft = ($post['status'] ?? 'LIVE') !== 'LIVE';
        if (!$live && !$isDraft) {
            $api()->post($postUrl . '/revert'); // inactive or scheduled: not on the blog yet
        }
        if ($paper->status === 'active' && ($isDraft || !$live)) {
            $when = $live ? BloggerPublisher::postDate($paper) : $paper->publish_at->toRfc3339String();
            $published = $api()->post($postUrl . '/publish?publishDate=' . urlencode($when));
            if (!$published->successful()) {
                throw new RuntimeException('The post is a draft in Blogger and could not be published (' . ($published->json('error.message') ?? 'HTTP ' . $published->status()) . '). Publish it in Blogger.');
            }
        }

        if ($live) {
            $paper->forceFill(['blogger_id' => (string) $post['id'], 'blogger_url' => $patched->json('url') ?? ($post['url'] ?? null), 'publish_at' => null])->save();
        } elseif ($existing) {
            $paper->save(); // copied too early: hidden (inactive) or waiting for its schedule, like the Blogger post
        }
        Log::info('Blogger: emailed e-paper "' . $paper->title . '" is post ' . $post['id'] . ($live ? ', shown on the website' : ''));
    }

    /** Given up: shown in admin for 3 days; its pictures are deleted now */
    private function fail(string $code, string $message): void
    {
        File::deleteDirectory(self::dir($code));
        self::update($code, ['state' => 'failed', 'error' => Str::limit($message, 400), 'failed_at' => now()->toIso8601String()]);
        Log::warning('Blogger: e-paper upload ' . $code . ' failed: ' . $message);
    }

    /** Done: out of the outbox, pictures deleted */
    private static function forget(string $code): void
    {
        File::deleteDirectory(self::dir($code));
        self::change(fn (array $all) => array_diff_key($all, [$code => true]));
    }

    private static function update(string $code, array $changes): void
    {
        self::change(fn (array $all) => isset($all[$code]) ? array_replace($all, [$code => $changes + $all[$code]]) : $all);
    }

    private static function dir(string $code = ''): string
    {
        return rtrim(config('blogger.outbox') ?: storage_path('app/epaper-outbox'), '/\\') . ($code !== '' ? '/' . $code : '');
    }

    private static function read(): array
    {
        $file = self::dir() . '/queue.json';

        return is_file($file) ? (json_decode((string) file_get_contents($file), true) ?: []) : [];
    }

    /** Read-change-write of queue.json, one at a time (file lock); failed entries go after 3 days */
    private static function change(callable $change): void
    {
        File::ensureDirectoryExists(self::dir());
        $handle = fopen(self::dir() . '/queue.json', 'c+');
        try {
            flock($handle, LOCK_EX);
            $all = json_decode((string) stream_get_contents($handle), true) ?: [];
            $all = array_filter($change($all), fn ($e) => $e['state'] !== 'failed' || Carbon::parse($e['failed_at'] ?? 'now')->gt(now()->subDays(3)));
            ftruncate($handle, 0);
            rewind($handle);
            fwrite($handle, json_encode($all, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            fflush($handle);
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }
}
