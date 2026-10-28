<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

/** A Market Trend / Technical Analysis article sent to a news channel or a customer (see ArticleDistributor). */
class ArticleMail extends Mailable
{
    use Queueable, SerializesModels;

    /** @param array<int, array{path: string, name: string, mime: ?string}> $files attached to the email (any type) */
    public function __construct(public array $article, public bool $forChannel = false, public array $files = [])
    {
    }

    public function envelope(): Envelope
    {
        $contact = config('subscription.plan.contact_email');

        return new Envelope(
            subject: $this->article['title'],
            replyTo: $contact ? [new Address($contact, 'Investment Point')] : [],
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.article', with: ['imagePath' => $this->localImagePath()]);
    }

    /**
     * The article picture as a file on this server, when it is one of our uploads (/storage/...), so it
     * can be put inside the email. Outside pictures (other websites) stay as links.
     */
    private function localImagePath(): ?string
    {
        $path = (string) parse_url((string) ($this->article['image_url'] ?? ''), PHP_URL_PATH);
        if (!str_starts_with($path, '/storage/') || str_contains($path, '..')) {
            return null;
        }

        $file = Storage::disk('public')->path(rawurldecode(substr($path, strlen('/storage/'))));

        return is_file($file) && filesize($file) <= 5 * 1024 * 1024 ? $file : null;
    }

    /** Files picked under "Attach files" in the "Send this article" box. */
    public function attachments(): array
    {
        return collect($this->files)
            ->filter(fn ($f) => is_file($f['path']))
            ->map(function ($f) {
                $attachment = Attachment::fromPath($f['path'])->as($f['name']);

                return $f['mime'] ? $attachment->withMime($f['mime']) : $attachment;
            })
            ->values()->all();
    }
}
