<?php

namespace App\Services;

use App\Mail\ArticleMail;
use App\Models\News;
use App\Models\PressContact;
use App\Models\PressDelivery;
use App\Models\TechnicalAnalysis;
use App\Support\Html;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Throwable;

/**
 * Sends a saved Market Trend / Technical Analysis article to the channels and emails typed in the
 * "Send this article" box on its form:
 *  - a news channel's link gets the article posted to it as JSON (only works when that link is a
 *    "submit news" / API address; an ordinary web page is reported as failed)
 *  - every email address (channel or customer) gets the full article by email, one email each,
 *    with the files picked under "Attach files" (if any)
 * Every send is logged in press_deliveries as sent / failed, so the admin can see what worked.
 */
class ArticleDistributor
{
    public const TYPES = ['news' => 'Market Trend', 'analysis' => 'Technical Analysis'];

    /**
     * @param iterable<PressContact> $contacts
     * @param array<int, array{path: string, name: string, mime: ?string}> $files attached to every email (not to channel links)
     */
    public function send(Model $article, string $type, iterable $contacts, array $files = []): void
    {
        $payload = $this->payload($article, $type);

        foreach ($contacts as $contact) {
            if ($contact->type === 'channel' && $contact->url) {
                $this->postToLink($article, $type, $contact, $payload);
            }
            if ($contact->email) {
                $this->email($article, $type, $contact, $payload, $files);
            }
        }
    }

    /** What is sent: the article as it appears on the website. */
    public function payload(Model $article, string $type): array
    {
        $html = Html::isHtml((string) ($article->content ?: $article->description))
            ? Html::clean((string) ($article->content ?: $article->description))
            : collect(preg_split('/\R{2,}/', trim((string) ($article->content ?: $article->description))))
                ->map(fn ($p) => '<p>' . nl2br(e(trim($p))) . '</p>')->implode("\n");

        return array_filter([
            'source' => 'Investment Point',
            'source_url' => $this->publicUrl(url('/')),
            'category' => self::TYPES[$type],
            'title' => $article->title,
            'summary' => Html::plain((string) $article->description),
            'content_html' => $this->publicUrl($html),
            'content_text' => Html::plain($html),
            'image_url' => $this->publicUrl($article->image_url),
            'article_url' => $this->publicUrl($article->url),
            'published_at' => optional($article->created_at)->toIso8601String(),
            'cmp' => $article instanceof TechnicalAnalysis ? $article->cmp : null,
            'support' => $article instanceof TechnicalAnalysis ? $article->support : null,
            'resistance' => $article instanceof TechnicalAnalysis ? $article->resistance : null,
        ], fn ($v) => $v !== null && $v !== '');
    }

    /**
     * Links to this site point at the live address (config app.public_url) instead of the address
     * the admin panel was opened on (e.g. 127.0.0.1 on a test computer, which nobody else can open).
     */
    private function publicUrl(?string $text): ?string
    {
        $public = config('app.public_url');
        if ($text === null || !$public) {
            return $text;
        }

        $roots = array_unique(array_filter([rtrim(url('/'), '/'), rtrim((string) config('app.url'), '/')]));

        return str_replace($roots, $public, $text);
    }

    private function postToLink(Model $article, string $type, PressContact $contact, array $payload): void
    {
        try {
            $response = Http::acceptJson()->connectTimeout(5)->timeout(15)
                ->withHeaders(['User-Agent' => 'InvestmentPoint-Publisher/1.0'])
                ->post($contact->url, $payload);

            if (!$response->successful()) {
                $this->log($article, $type, $contact, 'link', $contact->url, 'failed', 'The link answered HTTP ' . $response->status() . '. It may not accept automatic submissions.');
            } elseif (Str::contains(strtolower((string) $response->header('Content-Type')), 'text/html')) {
                // A normal web page (e.g. a form people fill in by hand) answers with HTML and ignores the data
                $this->log($article, $type, $contact, 'link', $contact->url, 'failed', 'The link is a normal web page, not a submission address. Send by email or submit it there by hand.');
            } else {
                $this->log($article, $type, $contact, 'link', $contact->url, 'sent');
            }
        } catch (Throwable $e) {
            $this->log($article, $type, $contact, 'link', $contact->url, 'failed', 'Could not reach the link: ' . Str::limit($e->getMessage(), 180));
        }
    }

    private function email(Model $article, string $type, PressContact $contact, array $payload, array $files = []): void
    {
        $send = fn () => Mail::to($contact->email, $contact->name)->send(new ArticleMail($payload, $contact->type === 'channel', $files));

        try {
            try {
                $send();
            } catch (TransportExceptionInterface $e) {
                // Gmail sometimes drops the connection mid-send: try once more on a fresh connection
                Mail::purge();
                sleep(3);
                $send();
            }
            $this->log($article, $type, $contact, 'email', $contact->email, 'sent');
        } catch (Throwable $e) {
            report($e);
            $hint = $files && str_contains($e->getMessage(), 'closed unexpectedly')
                ? ' Try again without the attached files (too big, or Gmail blocks it, e.g. .exe or a ZIP/RAR with one inside).' : '';
            $this->log($article, $type, $contact, 'email', $contact->email, 'failed', 'Email not sent: ' . Str::limit($e->getMessage(), 250 - 16 - strlen($hint)) . $hint);
        }
    }

    private function log(Model $article, string $type, PressContact $contact, string $via, string $target, string $status, ?string $message = null): void
    {
        PressDelivery::create([
            'article_type' => $type,
            'article_id' => $article->getKey(),
            'article_title' => Str::limit($article->title, 250),
            'press_contact_id' => $contact->id,
            'contact_name' => $contact->name,
            'via' => $via,
            'target' => $target,
            'status' => $status,
            'message' => $message,
        ]);
    }
}
