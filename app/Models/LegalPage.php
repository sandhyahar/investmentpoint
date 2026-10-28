<?php

namespace App\Models;

use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * The legal pages (Disclaimer, Privacy Policy, Terms, Refund Policy, Cookie Policy). Admin can edit the title,
 * subtitle, the chips under the title and the whole text; until then the built-in default is shown.
 */
class LegalPage extends Model
{
    protected $fillable = ['slug', 'title', 'subtitle', 'meta', 'content'];

    /** slug => defaults. 'route' is the public page, 'description' its search-engine summary. */
    public const PAGES = [
        'disclaimer' => [
            'menu' => 'Disclaimer',
            'route' => 'disclaimer',
            'title' => 'Disclaimer & Disclosure Statement',
            'subtitle' => 'Investment Point: Legal Policies, Disclosures, Disclaimer & Terms of Use',
            'meta' => "📰 RNI Registered Weekly Newspaper\n⚖️ Jurisdiction: Jamnagar, Gujarat",
            'description' => 'Investment Point disclaimer and disclosure statement: SEBI research analyst details, risks of investing in securities and how to read our research.',
        ],
        'privacy' => [
            'menu' => 'Privacy Policy',
            'route' => 'privacy',
            'title' => 'Privacy Policy',
            'subtitle' => 'Privacy Policy & Statutory Regulatory Disclosure',
            'meta' => "🏢 Entity: Investment Point\n📍 Registered Office: Jamnagar, Gujarat, India",
            'description' => 'Investment Point privacy policy: what information we collect, how we use and protect it, cookies, and your choices.',
        ],
        'terms' => [
            'menu' => 'Terms & Conditions',
            'route' => 'terms',
            'title' => 'Terms & Conditions',
            'subtitle' => 'Comprehensive Terms & Conditions of Use and Affiliate Program Agreement',
            'meta' => "📅 Effective Date: May 19, 2016\n🏢 Entity: Investment Point",
            'description' => 'Terms and conditions for using the Investment Point website, newsletters and subscriptions, including content use and liability.',
        ],
        'refund' => [
            'menu' => 'Terms of Refund & Cancellation Policy',
            'route' => 'refund',
            'title' => 'Terms of Refund & Cancellation Policy',
            'subtitle' => 'How payments, cancellations and refund claims are handled for all Investment Point services.',
            'meta' => "📅 Effective Date: May 19, 2025\n🏢 Entity: Investment Point",
            'description' => 'Investment Point refund and cancellation policy for newsletter and newspaper subscriptions: eligibility, timelines and how to request a refund.',
        ],
        'cookies' => [
            'menu' => 'Cookie Policy',
            'route' => 'cookies',
            'title' => 'Cookie Policy',
            'subtitle' => 'How Investment Point uses cookies and similar technologies, and how you can manage them.',
            'meta' => "📅 Effective Date: May 19, 2025\n🏢 Entity: Investment Point",
            'description' => 'Investment Point cookie policy: what cookies are, the types we use, third-party cookies and how to manage or disable them in your browser.',
        ],
    ];

    /** The saved page, or an unsaved one filled with the defaults. */
    public static function for(string $slug): self
    {
        abort_unless(isset(self::PAGES[$slug]), 404);
        $defaults = self::PAGES[$slug];

        return static::firstOrNew(['slug' => $slug], [
            'title' => $defaults['title'],
            'subtitle' => $defaults['subtitle'],
            'meta' => $defaults['meta'],
        ]);
    }

    public function info(): array
    {
        return self::PAGES[$this->slug];
    }

    public function contentHtml(): string
    {
        return $this->content ?: view('legal.defaults.' . $this->slug)->render();
    }

    /** Chips under the title, one per line. "Label: value" shows the value in bold. */
    public function metaItems(): array
    {
        // /u: without it \R matches the 0x85 byte inside emoji like 📅 and splits them
        return collect(preg_split('/\R/u', (string) $this->meta))
            ->map(fn ($line) => trim($line))->filter()
            ->map(fn ($line) => Str::contains($line, ': ') ? explode(': ', $line, 2) : [null, $line])
            ->values()->all();
    }

    /**
     * Gives every section heading (h3) an id and builds the "Contents" list from the headings,
     * so the sidebar stays right whatever the admin changes. Part headings (h2) become group labels.
     *
     * @return array{0: string, 1: array<int, array{type: string, text: string, id?: string, num?: string}>}
     */
    public static function withContents(string $html): array
    {
        $doc = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="UTF-8"><div id="legal-root">' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = $doc->getElementById('legal-root');
        if (!$root) {
            return [$html, []];
        }

        $toc = [];
        $n = 0;
        foreach ((new DOMXPath($doc))->query('.//h2|.//h3', $root) as $heading) {
            /** @var DOMElement $heading */
            if ($heading->tagName === 'h2') {
                // <h2><span>Part I</span> Website Terms</h2> -> "Part I: Website Terms"
                $span = $heading->getElementsByTagName('span')->item(0);
                $label = $span ? self::text($span) : '';
                $text = self::text($heading);
                if ($label !== '' && Str::startsWith($text, $label)) {
                    $text = $label . ': ' . trim(Str::after($text, $label));
                }
                if ($text !== '') {
                    $toc[] = ['type' => 'group', 'text' => $text];
                }
                continue;
            }

            $em = $heading->getElementsByTagName('em')->item(0);
            $num = $em ? self::text($em) : '';
            $text = trim(Str::after(self::text($heading), $num));
            if ($text === '') {
                continue;
            }
            $id = $heading->getAttribute('id') ?: 'sec-' . (++$n);
            $heading->setAttribute('id', $id);
            $toc[] = ['type' => 'link', 'id' => $id, 'num' => $num, 'text' => $text];
        }

        $out = '';
        foreach ($root->childNodes as $child) {
            $out .= $doc->saveHTML($child);
        }

        return [$out, $toc];
    }

    private static function text(\DOMNode $node): string
    {
        return trim(preg_replace('/\s+/u', ' ', $node->textContent));
    }
}
