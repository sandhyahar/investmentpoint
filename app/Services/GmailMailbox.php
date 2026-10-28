<?php

namespace App\Services;

use App\Models\Setting;
use RuntimeException;

/**
 * The website's Gmail account over IMAP (imap.gmail.com, TLS; same account and App Password as the website email),
 * for the daily E-Paper emails to Blogger (EpaperByEmail):
 *  - bounced(): which of them Blogger's mail server refused (Gmail's "Message blocked" reply names the email it
 *    answers in In-Reply-To), so they can be sent again;
 *  - trashBloggerEmails(): once Blogger has made the posts, the sent emails go to the Trash (Gmail empties it after
 *    30 days). Only emails to …@blogger.com that carry an upload code are touched.
 */
class GmailMailbox
{
    /** @var resource|null */
    protected $in;
    /** @var resource|null */
    protected $out;
    private int $tag = 0;

    /** Gmail search for the website's Blogger upload emails */
    public const SEARCH = 'to:blogger.com IPUPLOAD';

    /** Is this the Gmail account (and is its App Password known)? */
    public static function available(): bool
    {
        return str_ends_with(strtolower((string) config('mail.mailers.smtp.username')), '@gmail.com') && self::password() !== '';
    }

    /**
     * The Message-IDs (without < >) that Gmail reported as not delivered.
     *
     * @throws RuntimeException
     */
    public function bounced(array $messageIds): array
    {
        if (!$messageIds || !self::available()) {
            return [];
        }

        return $this->session(function (array $folders) use ($messageIds) {
            $this->command('EXAMINE ' . self::quote($folders['all']));
            $bounced = [];
            foreach ($messageIds as $id) {
                if ($this->search('HEADER In-Reply-To ' . self::quote($id))) {
                    $bounced[] = $id;
                }
            }

            return $bounced;
        });
    }

    /**
     * Move the website's Blogger upload emails from Sent to the Trash. Returns how many were moved.
     *
     * @throws RuntimeException
     */
    public function trashBloggerEmails(): int
    {
        if (!self::available()) {
            return 0;
        }

        return $this->session(function (array $folders) {
            $this->command('SELECT ' . self::quote($folders['sent']));
            $uids = $this->search('X-GM-RAW ' . self::quote(self::SEARCH));
            if ($uids) {
                $this->command('UID MOVE ' . implode(',', $uids) . ' ' . self::quote($folders['trash']));
            }

            return count($uids);
        });
    }

    /** Sign in, find the folders (by their special-use flags: the names depend on Gmail's language), run $work, sign out */
    private function session(callable $work): mixed
    {
        $this->connect();
        try {
            $this->line(); // greeting
            $this->command('LOGIN ' . self::quote((string) config('mail.mailers.smtp.username')) . ' ' . self::quote(self::password()));

            $folders = [];
            foreach ($this->command('LIST "" "*"') as $line) {
                if (preg_match('/^\* LIST \(([^)]*)\) "[^"]*" (.+)$/', $line, $m)) {
                    foreach (['all' => '\All', 'sent' => '\Sent', 'trash' => '\Trash'] as $key => $flag) {
                        if (str_contains($m[1], $flag)) {
                            $folders[$key] = trim($m[2], '"');
                        }
                    }
                }
            }
            if (count($folders) < 3) {
                throw new RuntimeException('Gmail did not show its All Mail, Sent and Trash folders.');
            }

            $result = $work($folders);
            $this->command('LOGOUT');

            return $result;
        } finally {
            $this->close();
        }
    }

    private static function password(): string
    {
        return str_replace(' ', '', (string) (Setting::mailPassword() ?: config('mail.mailers.smtp.password')));
    }

    protected function connect(): void
    {
        $socket = @stream_socket_client('ssl://imap.gmail.com:993', $errno, $error, 20);
        if (!$socket) {
            throw new RuntimeException('Could not reach Gmail (' . $error . ').');
        }
        stream_set_timeout($socket, 30);
        $this->in = $this->out = $socket;
    }

    protected function close(): void
    {
        if (is_resource($this->in)) {
            fclose($this->in);
        }
        $this->in = $this->out = null;
    }

    /** UID SEARCH; returns the UIDs found */
    private function search(string $criteria): array
    {
        $uids = [];
        foreach ($this->command('UID SEARCH ' . $criteria) as $line) {
            if (str_starts_with($line, '* SEARCH')) {
                $uids = array_merge($uids, array_values(array_filter(explode(' ', trim(substr($line, 8))), 'ctype_digit')));
            }
        }

        return $uids;
    }

    /** Send one command; returns the lines before its "OK". @throws RuntimeException when Gmail says NO / BAD */
    private function command(string $command): array
    {
        $tag = 'A' . ++$this->tag;
        fwrite($this->out, $tag . ' ' . $command . "\r\n");

        $lines = [];
        while (($line = $this->line()) !== null) {
            if (str_starts_with($line, $tag . ' ')) {
                if (!str_starts_with($line, $tag . ' OK')) {
                    $said = str_starts_with($command, 'LOGIN') ? 'the App Password was not accepted' : substr($line, strlen($tag) + 1);
                    throw new RuntimeException('Gmail: ' . $said . '.');
                }

                return $lines;
            }
            $lines[] = $line;
        }
        throw new RuntimeException('Gmail stopped answering.');
    }

    private function line(): ?string
    {
        $line = fgets($this->in);

        return $line === false ? null : rtrim($line, "\r\n");
    }

    private static function quote(string $value): string
    {
        return '"' . addcslashes($value, '"\\') . '"';
    }
}
