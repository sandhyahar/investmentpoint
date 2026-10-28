<?php

namespace App\Mail;

use App\Models\UserBlog;
use App\Support\Html;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Sent to the writer (email from the Create Blog form) when the admin approves their blog: the full blog + its link. */
class BlogApprovedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public UserBlog $blog)
    {
    }

    public function envelope(): Envelope
    {
        $contact = config('subscription.plan.contact_email');

        return new Envelope(
            subject: 'Your blog is live on Investment Point: ' . $this->blog->title,
            replyTo: $contact ? [new Address($contact, 'Investment Point')] : [],
        );
    }

    public function content(): Content
    {
        $text = (string) $this->blog->content;

        // The same body the website shows: cleaned HTML, or plain text split into paragraphs
        $html = Html::isHtml($text)
            ? Html::clean($text)
            : collect(preg_split('/\R{2,}/', trim($text)))->map(fn ($p) => '<p>' . nl2br(e(trim($p))) . '</p>')->implode("\n");

        return new Content(view: 'emails.blog-approved', with: [
            'contentHtml' => $html,
            'blogUrl' => route('blog.show', $this->blog->slug),
        ]);
    }
}
