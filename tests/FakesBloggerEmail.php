<?php

namespace Tests;

use App\Models\Setting;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;

/**
 * Blogger connected, with each daily blog's "Post using email" address saved. Blogger answers like the real one:
 * the posts it made from the emails (setBloggerPosts) are listed with their pictures; patch / publish / revert succeed.
 */
trait FakesBloggerEmail
{
    /** What Blogger lists for "posts of the blog": [blogId => [post, ...]] */
    protected array $bloggerPosts = [];

    protected function bloggerConnected(): void
    {
        Setting::set('blogger_client_id', '1234567890-abcdef123.apps.googleusercontent.com');
        Setting::set('blogger_client_secret', Crypt::encryptString('GOCSPX-test-secret-0123456789'));
        Setting::set('blogger_refresh_token', Crypt::encryptString('1//refresh-token'));
        Setting::set('blogger_connected_at', now()->subHour()->toIso8601String());
        foreach (['gujarati', 'hindi', 'english'] as $key) {
            Setting::set('blogger_email_' . $key, Crypt::encryptString("investmentpoint.{$key}secret@blogger.com"));
        }
        Setting::set('blogger_blogs', json_encode(array_map(fn ($key) => ['id' => 'blog-' . $key, 'name' => ucfirst($key), 'url' => "https://{$key}.blogspot.com/"],
            ['gujarati' => 'gujarati', 'hindi' => 'hindi', 'english' => 'english'])));
        Cache::put('blogger.access_token', 'ya29.token', now()->addDays(30)); // the tests move the clock forward
        // The outbox of this test only (never the real storage/app/epaper-outbox)
        $outbox = sys_get_temp_dir() . '/ip-epaper-outbox-' . getmypid() . '-' . uniqid();
        config(['blogger.outbox' => $outbox]);
        $this->beforeApplicationDestroyed(fn () => \Illuminate\Support\Facades\File::deleteDirectory($outbox));

        Http::swap(new Factory());
        Http::fake([
            'www.googleapis.com/blogger/v3/blogs/*/posts?*' => fn (Request $r) => Http::response(['items' => $this->bloggerPosts[explode('/', explode('/blogs/', $r->url())[1])[0]] ?? []]),
            'www.googleapis.com/blogger/v3/blogs/*' => fn (Request $r) => Http::response(['id' => basename(parse_url($r->url(), PHP_URL_PATH)), 'url' => 'https://hindi.blogspot.com/2026/10/post.html']),
        ]);
    }

    /** Gmail answering over IMAP with $replies (the lines after the greeting); gmailCommands() shows what it was sent */
    protected function fakeGmail(array $replies): \App\Services\GmailMailbox
    {
        config(['mail.mailers.smtp.username' => 'shreeinvestmentpoint@gmail.com', 'mail.mailers.smtp.password' => 'abcd efgh ijkl mnop']);
        $gmail = new class($replies) extends \App\Services\GmailMailbox {
            public $commands;

            public function __construct(private array $replies)
            {
            }

            protected function connect(): void
            {
                $this->in = fopen('php://memory', 'r+');
                fwrite($this->in, "* OK Gimap ready\r\n" . implode('', array_map(fn ($r) => $r . "\r\n", $this->replies)));
                rewind($this->in);
                $this->out = $this->commands = fopen('php://memory', 'r+');
            }

            protected function close(): void
            {
                // keep $commands to read in the test
            }
        };
        $this->app->instance(\App\Services\GmailMailbox::class, $gmail);

        return $gmail;
    }

    /** The commands the fake Gmail received */
    protected static function gmailCommands(\App\Services\GmailMailbox $gmail): string
    {
        rewind($gmail->commands);

        return stream_get_contents($gmail->commands);
    }

    /** The emails sent (array mailer): Symfony Email objects */
    protected function sentEmails(): \Illuminate\Support\Collection
    {
        return app('mail.manager')->mailer()->getSymfonyTransport()->messages()->map(fn ($m) => $m->getOriginalMessage())->values();
    }

    /** The code in an email to Blogger (finds the post again) */
    protected static function codeOf(\Symfony\Component\Mime\Email $email): string
    {
        return strtok($email->getTextBody(), "\n");
    }

    /** Blogger made a post from an email: its pictures are the attachments, the code is in the text */
    protected function bloggerMadePost(string $blogId, string $postId, string $code, int $pages, string $status = 'LIVE'): void
    {
        $pictures = '';
        for ($n = 1; $n <= $pages; $n++) {
            $file = sprintf('page-%02d.jpg', $n);
            $pictures .= "<a href=\"https://blogger.googleusercontent.com/img/b/R29vZ2xl/AVvX{$postId}{$n}/s1600/{$file}\"><img src=\"https://blogger.googleusercontent.com/img/b/R29vZ2xl/AVvX{$postId}{$n}/s320/{$file}\"></a>";
        }
        $this->bloggerPosts[$blogId][] = ['id' => $postId, 'status' => $status, 'url' => "https://x.blogspot.com/{$postId}.html", 'content' => "<div>{$code}</div>{$pictures}"];
    }
}
