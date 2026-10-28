<?php

namespace App\Support;

use HTMLPurifier;
use HTMLPurifier_Config;
use Illuminate\Support\Str;

/**
 * Helpers for text written in the admin rich text editor (TinyMCE).
 */
class Html
{
    private static ?HTMLPurifier $purifier = null;

    /** Does this text contain editor HTML (as opposed to older plain text)? */
    public static function isHtml(?string $text): bool
    {
        return (bool) preg_match('/<\s*(p|br|div|h[1-6]|ul|ol|li|strong|b|em|i|u|a|img|table|span|blockquote)\b/i', (string) $text);
    }

    /**
     * Safe HTML for display: keeps formatting, links, images, tables, colours;
     * removes scripts, event handlers, iframes and anything else unsafe.
     */
    public static function clean(?string $html): string
    {
        if (!self::$purifier) {
            $config = HTMLPurifier_Config::createDefault();
            $config->set('Cache.SerializerPath', storage_path('framework/cache'));
            $config->set('HTML.Allowed', implode(',', [
                'p[style]', 'br', 'hr', 'div[style]', 'span[style]', 'h1[style]', 'h2[style]', 'h3[style]', 'h4[style]', 'h5[style]', 'h6[style]',
                'strong', 'b', 'em', 'i', 'u', 's', 'sub', 'sup', 'blockquote', 'pre', 'code',
                'ul[style]', 'ol[style]', 'li[style]',
                'a[href|title|target]', 'img[src|alt|title|width|height|style]',
                'table[style|border|cellpadding|cellspacing]', 'thead', 'tbody', 'tfoot', 'tr[style]', 'th[style|colspan|rowspan]', 'td[style|colspan|rowspan]', 'caption',
            ]));
            $config->set('CSS.AllowedProperties', ['color', 'background-color', 'text-align', 'text-decoration', 'font-weight', 'font-style', 'padding-left', 'margin-left', 'width', 'height', 'border', 'border-collapse', 'list-style-type', 'float', 'vertical-align']);
            $config->set('Attr.AllowedFrameTargets', ['_blank']);
            $config->set('HTML.TargetNoopener', true);
            $config->set('URI.AllowedSchemes', ['http' => true, 'https' => true, 'mailto' => true, 'tel' => true]);
            self::$purifier = new HTMLPurifier($config);
        }

        return self::tidySpacing(self::$purifier->purify((string) $html));
    }

    /**
     * Removes the blank space editors and Blogger imports leave behind: empty
     * paragraphs (only spaces, &nbsp; or <br>), long runs of <br>, and <br> at the
     * start/end of a paragraph.
     */
    private static function tidySpacing(string $html): string
    {
        $blank = '(?:\s|&nbsp;|&#160;|\x{00A0}|<br\s*\/?>)*';

        // Repeat so nested empties (<div><span><b>&nbsp;</b></span></div>) all go
        for ($i = 0; $i < 5; $i++) {
            $before = $html;
            $html = preg_replace('/<(p|div|span|b|strong|em|i|u|h[1-6])\b[^>]*>' . $blank . '<\/\1>/iu', '', $html);
            if ($html === $before || $html === null) {
                break;
            }
        }

        $html = preg_replace('/(?:<br\s*\/?>\s*){3,}/iu', '<br /><br />', (string) $html);
        $html = preg_replace('/(<(?:p|div|li|h[1-6])\b[^>]*>)(?:\s|<br\s*\/?>)+/iu', '$1', $html);
        $html = preg_replace('/(?:<br\s*\/?>|\s)+(<\/(?:p|div|li|h[1-6])>)/iu', '$1', $html);

        return trim((string) $html);
    }

    private static ?HTMLPurifier $legalPurifier = null;

    /**
     * Like clean(), but also keeps the class names the legal pages use for their styled
     * boxes (tc-officer, tc-grid, dc-tags...), so editing a page doesn't lose its design.
     */
    public static function cleanLegal(?string $html): string
    {
        if (!self::$legalPurifier) {
            $config = HTMLPurifier_Config::createDefault();
            $config->set('Cache.SerializerPath', storage_path('framework/cache'));
            $config->set('HTML.Allowed', implode(',', [
                'p[class|style]', 'br', 'hr', 'div[class|style]', 'span[class|style]', 'h2[class|style]', 'h3[class|style]', 'h4[class|style]', 'h5[style]', 'h6[style]',
                'strong', 'b', 'em[class]', 'i', 'u', 's', 'sub', 'sup', 'blockquote',
                'ul[class|style]', 'ol[class|style]', 'li[style]',
                'a[href|title|target|class]', 'img[src|alt|title|width|height|style]',
                'table[style|border|cellpadding|cellspacing]', 'thead', 'tbody', 'tr[style]', 'th[style|colspan|rowspan]', 'td[style|colspan|rowspan]',
            ]));
            $config->set('CSS.AllowedProperties', ['color', 'background-color', 'text-align', 'text-decoration', 'font-weight', 'font-style', 'padding-left', 'margin-left', 'width', 'height', 'border', 'border-collapse', 'list-style-type', 'vertical-align']);
            $config->set('Attr.AllowedFrameTargets', ['_blank']);
            $config->set('HTML.TargetNoopener', true);
            $config->set('URI.AllowedSchemes', ['http' => true, 'https' => true, 'mailto' => true, 'tel' => true]);
            self::$legalPurifier = new HTMLPurifier($config);
        }

        return self::$legalPurifier->purify((string) $html);
    }

    /** Plain one-line text for previews/excerpts (tags removed, &nbsp; etc. decoded). */
    public static function plain(?string $html, ?int $limit = null): string
    {
        $text = preg_replace('/<(br|\/p|\/div|\/li|\/h[1-6])[^>]*>/i', ' ', (string) $html);
        $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = trim(preg_replace('/\s+/u', ' ', str_replace("\u{00A0}", ' ', $text)));

        return $limit ? Str::limit($text, $limit) : $text;
    }

    /**
     * The text Google shows under a link, and WhatsApp / Facebook under a shared one.
     * A news summary alone is often only 40-60 characters, while Google shows about 120-160: a short
     * summary is continued with the start of the article (cut at a word, never in the middle of one).
     */
    public static function metaDescription(?string $summary, ?string $body, int $limit = 160): string
    {
        $summary = static::plain($summary);
        $body = static::plain($body);

        if ($body === '' || mb_strlen($summary) >= 110) {
            $text = $summary !== '' ? $summary : $body;
        } elseif ($summary === '' || str_starts_with(mb_strtolower($body), mb_strtolower(rtrim($summary, ' .')))) {
            $text = $body; // nothing to add, or the article already opens with the summary
        } else {
            $text = rtrim($summary, ' .') . '. ' . $body;
        }

        return mb_strlen($text) <= $limit ? $text : Str::limit($text, $limit - 1, '…', true);
    }

    /**
     * The "Short Summary" of an admin form, which may be left empty: then the start of the full
     * article is used (plain text), so lists, cards and Google's description still have something.
     * Never null: the summary columns don't allow it.
     */
    public static function summary(?string $summary, ?string $content, int $limit = 220): string
    {
        return static::plain($summary) !== '' ? (string) $summary : static::plain($content, $limit);
    }
}
