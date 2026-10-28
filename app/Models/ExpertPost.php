<?php

namespace App\Models;

use App\Support\Html;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ExpertPost extends Model
{
    protected $fillable = ['blogger_id', 'title', 'author', 'content', 'image', 'published_on', 'status'];

    protected $casts = [
        'published_on' => 'date',
    ];

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function getUrlAttribute(): string
    {
        return route('experts-view.show', ['expertPost' => $this->id, 'slug' => Str::slug(Str::limit($this->title, 80, '')) ?: null]);
    }

    public function getAuthorNameAttribute(): string
    {
        return $this->author ?: 'Investment Point';
    }

    public function isUploadedImage(): bool
    {
        return $this->image && !Str::startsWith($this->image, ['http://', 'https://']);
    }

    public function imageUrl(): ?string
    {
        if (!$this->image) {
            return null;
        }

        return $this->isUploadedImage() ? asset('storage/' . $this->image) : $this->image;
    }

    /** Writer shown on cards and the article: the name from the post's intro, else the author field. */
    public function writerName(): string
    {
        return $this->authorIntro()['name'] ?? $this->author_name;
    }

    public function writerRole(): string
    {
        return $this->authorIntro()['roles'][0]['text'] ?? ($this->author ? 'Guest Expert' : 'Investment Point research desk');
    }

    private ?array $intro = null;

    /**
     * Imported Blogger posts open with the writer's photo and centred details (name, firm, phone,
     * email / website), which left a big centred block with white space around it. This splits
     * that block off so the article page can show it as a profile card (photo left, details right)
     * and the article starts right after. Null when the post doesn't open that way.
     *
     * @return array{photo: string, label: ?string, name: string, roles: array, contacts: array, body: string}|null
     */
    public function authorIntro(): ?array
    {
        if ($this->intro !== null) {
            return $this->intro ?: null;
        }
        $this->intro = [];

        $html = (string) $this->content;
        if (!Html::isHtml($html) || stripos($html, '<img') === false) {
            return null;
        }

        $dom = new \DOMDocument();
        $errors = libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8"?><div id="ip-root">' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($errors);
        $root = (new \DOMXPath($dom))->query('//div[@id="ip-root"]')->item(0);
        if (!$root) {
            return null;
        }

        // The intro = the centred blocks at the very top (blank lines in between are dropped too)
        $introNodes = [];
        $photo = null;
        $loose = false;
        foreach (iterator_to_array($root->childNodes) as $node) {
            $text = trim(preg_replace('/[\s\x{00A0}]+/u', ' ', $node->textContent));
            $imgs = $node instanceof \DOMElement
                ? ($node->tagName === 'img' ? [$node] : iterator_to_array($node->getElementsByTagName('img')))
                : [];
            if ($text === '' && !$imgs) {
                $introNodes[] = $node;
                continue;
            }
            if (!$node instanceof \DOMElement || !preg_match('/text-align:\s*center/i', $node->getAttribute('style'))) {
                // Some posts put the name (or all details) just outside the centred block, under the photo
                $short = !$imgs && mb_strlen($text) <= 150 && ($node instanceof \DOMText || in_array($node->tagName, ['div', 'span', 'b', 'strong', 'font'], true));
                if (!$photo || !$short) {
                    break;
                }
                $loose = true;
                $introNodes[] = $node;
                continue;
            }
            if ($imgs) {
                if ($photo || count($imgs) > 1) {
                    return null;
                }
                $photo = $imgs[0];
            }
            $introNodes[] = $node;
        }

        // A portrait-ish photo, not a wide banner or chart
        $width = (int) $photo?->getAttribute('width');
        $height = (int) $photo?->getAttribute('height');
        if (!$photo || !str_starts_with($photo->getAttribute('src'), 'https://') || ($width && $height && $width / $height > 1.4)) {
            return null;
        }

        // Its text, one entry per line (<br>, <div>, <p>), source line wraps ignored
        $introHtml = implode('', array_map(fn ($n) => $dom->saveHTML($n), $introNodes));
        $text = strip_tags(preg_replace('/<br\b[^>]*>|<\/?(?:div|p)\b[^>]*>/i', "\x01", $introHtml));
        $lines = array_values(array_filter(array_map(
            fn ($l) => trim(preg_replace('/[\s\x{00A0}]+/u', ' ', $l)),
            explode("\x01", html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8'))
        ), 'strlen'));
        if (!$lines || count($lines) > 6 || max(array_map('mb_strlen', $lines)) > 100) {
            return null;
        }

        $label = $name = null;
        $roles = $contacts = [];
        foreach ($lines as $line) {
            if (preg_match('/[\w.+-]+@[\w-]+(?:\.[\w-]+)+/u', $line, $m)) {
                $contacts[] = ['type' => 'email', 'text' => $m[0], 'href' => 'mailto:' . $m[0]];
            } elseif (preg_match('/(?:https?:\/\/|www\.)\S+/i', $line, $m) && filter_var($url = preg_match('/^https?:/i', $m[0]) ? $m[0] : 'https://' . $m[0], FILTER_VALIDATE_URL)) {
                $contacts[] = ['type' => 'web', 'text' => preg_replace('#^https?://#i', '', rtrim($m[0], '/')), 'href' => $url];
            } elseif (!preg_match('/\bIN[AH]\d/i', $line) && preg_match('/\+?\d[\d\s().-]{8,}\d/', $line, $m) && strlen(preg_replace('/\D/', '', $m[0])) >= 10) {
                $contacts[] = ['type' => 'phone', 'text' => trim($m[0]), 'href' => 'tel:' . preg_replace('/[^\d+]/', '', $m[0])];
                // "નવીન દેસાઈ - ન્યુ જર્સી +1(201)699-8042": the rest of the line is the name / place
                $rest = preg_replace('/^[\s\-–|,:]+|[\s\-–|,:]+$/u', '', str_replace($m[0], '', $line));
                if ($rest !== '' && $name === null) {
                    [$name, $place] = array_pad(array_map('trim', explode(' - ', $rest, 2)), 2, null);
                    if ($place) {
                        $roles[] = ['text' => $place, 'sebi' => false];
                    }
                }
            } elseif (mb_strlen($line) <= 40 && preg_match('/(?:\d{4}|[૦-૯]{4})$/u', $line)) {
                continue; // "શુક્રવાર, સપ્ટેમ્બર ૫, ૨૦૨૫": the date, already shown under the title
            } elseif (preg_match('/sebi|\bIN[AH]\d/i', $line)) {
                $roles[] = ['text' => $line, 'sebi' => true];
            } elseif ($name === null) {
                // "સંકલન :- આશિષ શાહ" (compiled by: ...) -> small label + name
                if (preg_match('/^(.{2,30}?)\s*:-?\s*(.+)$/u', $line, $m)) {
                    [$label, $name] = [trim($m[1]), trim($m[2])];
                } else {
                    $name = $line;
                }
            } else {
                $roles[] = ['text' => $line, 'sebi' => false];
            }
        }
        // Lines from outside the centred block count only when they clearly are a profile
        // (a phone, email, website or SEBI number), not the article's first words ("Dear Trader...")
        if (!$name || ($loose && !$contacts && !array_filter(array_column($roles, 'sebi')))) {
            return null;
        }

        foreach ($introNodes as $node) {
            $root->removeChild($node);
        }
        $body = implode('', array_map(fn ($n) => $dom->saveHTML($n), iterator_to_array($root->childNodes)));

        return $this->intro = [
            'photo' => $photo->getAttribute('src'),
            'label' => $label,
            'name' => $name,
            'roles' => $roles,
            'contacts' => $contacts,
            'body' => trim($body),
        ];
    }

    /**
     * The article text to show (without the intro when there is one). Imported Gujarati / Hindi
     * posts lost the spaces between words that sat in separate <span>s ("હેડજિંગ</span><span>ખર્ચ"),
     * so a space is put back where one script word ends and the next word starts.
     */
    public function articleHtml(): string
    {
        $html = $this->authorIntro()['body'] ?? (string) $this->content;
        $indic = '\x{0900}-\x{097F}\x{0A80}-\x{0AFF}';
        $letter = '\x{0905}-\x{0939}\x{0A85}-\x{0AB9}'; // a new letter, not a vowel sign / virama of the same word
        $tags = '((?:<\/(?:span|b|strong)>)+(?:<(?:span|b|strong)\b[^>]*>)+)';

        $html = preg_replace('/(?<=[' . $indic . '])' . $tags . '(?=[' . $letter . 'A-Za-z0-9₹(])/u', ' $1', $html) ?? $html;

        return preg_replace('/(?<=[A-Za-z0-9%)])' . $tags . '(?=[' . $letter . '])/u', ' $1', $html) ?? $html;
    }

    public function excerpt(int $limit = 160): string
    {
        $text = Html::plain($this->authorIntro()['body'] ?? $this->content);
        // Posts titled from their opening sentence: don't repeat it in the preview
        $title = rtrim($this->title, '.…');
        if ($title !== '' && Str::startsWith($text, $title)) {
            $text = ltrim(Str::after($text, $title), " .…!?");
        }

        return Str::limit($text, $limit);
    }

    /** Minutes to read at ~200 words a minute. */
    public function readingMinutes(): int
    {
        return max(1, (int) round(str_word_count(Html::plain($this->content)) / 200));
    }

    protected static function booted()
    {
        static::deleting(function (ExpertPost $post) {
            if ($post->isUploadedImage()) {
                Storage::disk('public')->delete($post->image);
            }
        });
    }

    /**
     * Turn a blogspot post into title / author / cleaned content / cover image.
     * "Export view - By Srishti Mendiratta" posts carry their real headline as the first line
     * of the body; that line becomes the title and is removed from the content.
     *
     * @return array{title: string, author: ?string, content: string, image: ?string, published_on: string}
     */
    public static function fromBlogPost(string $title, string $html, string $postedAt): array
    {
        $title = trim(preg_replace('/\s+/u', ' ', $title));
        $author = null;
        $generic = false;

        if (preg_match('/^(?:ex[pt]ert|export)\s*view\s*(?:[-–:]\s*(?:by\s+)?(.*))?$/iu', $title, $m)) {
            $generic = true;
            // "By Srishti Mendiratta - 16.12.2025" -> author; a bare date ("04.07.2026") is not an author
            $author = trim(preg_replace('/(?:^|\s*[-–]\s*)\d{1,2}[.\-\/]\d{1,2}[.\-\/]\d{4}\s*$/', '', $m[1] ?? '')) ?: null;
        }

        $content = self::tidy(Html::clean($html));

        if ($generic) {
            // The headline is the short bold / centred line(s) at the top, sometimes split over 2-3 lines
            $parts = [];
            while (count($parts) < 3 && preg_match('/^\s*(<(p|h[1-6]|div)\b[^>]*>(.*?)<\/\2>)/isu', $content, $first)) {
                $text = Html::plain($first[3]);
                $looksLikeHeading = preg_match('/<(b|strong)\b|text-align:\s*center/i', $first[1]) || preg_match('/^<h[1-6]/i', $first[1]);
                if ($text === '' || mb_strlen($text) > 160 || !$looksLikeHeading) {
                    break;
                }
                $parts[] = $text;
                $content = self::tidy(substr($content, strlen($first[0])));
            }
            // No headline in the post: use the opening sentence
            $title = $parts
                ? implode(' ', $parts)
                : Str::limit(preg_split('/(?<=[.!?])\s/u', Html::plain($content), 2)[0] ?? $title, 110);
        }

        preg_match('/<img[^>]+src="(https:\/\/[^"]+)"/i', $content, $img);

        return [
            'title' => mb_substr($title, 0, 500),
            'author' => $author ? mb_substr($author, 0, 150) : null,
            'content' => $content,
            'image' => $img[1] ?? null,
            'published_on' => Carbon::parse($postedAt)->timezone('Asia/Kolkata')->toDateString(),
        ];
    }

    /** Drop the empty paragraphs / line breaks Blogger's editor leaves behind. */
    private static function tidy(string $html): string
    {
        $empty = '(?:\s|&nbsp;|\x{00A0}|<br\s*\/?>|<span[^>]*>\s*<\/span>)*';
        do {
            $before = $html;
            $html = preg_replace('/<(p|div|span|b|strong|i|em)\b[^>]*>' . $empty . '<\/\1>/iu', '', $html);
        } while ($html !== $before);

        return trim(preg_replace('/^(?:\s|<br\s*\/?>)+|(?:\s|<br\s*\/?>)+$/i', '', $html));
    }
}
