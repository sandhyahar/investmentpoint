<?php

namespace App\Services;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Admin "auto-translate to English": Gujarati or Hindi pasted into an article form is translated with
 * Google Translate (its free web service: no key, no daily AI allowance). If Google does not answer,
 * the free AI (NewsWriter::translate) is tried instead.
 *
 * This is Google's unofficial free service: it can be slowed down or blocked by Google at any time.
 * The official one is the Google Cloud Translation API (needs a Google Cloud account with billing).
 */
class Translator
{
    private const ENDPOINT = 'https://clients5.google.com/translate_a/t?client=dict-chrome-ex&sl=auto&tl=en';

    /** Source characters sent in one request (tested with 1,800 Gujarati characters) */
    private const BATCH_CHARS = 1800;

    /** Elements whose text is translated as one piece, so sentences keep their meaning */
    private const BLOCKS = ['p', 'li', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'td', 'th', 'blockquote', 'figcaption', 'caption', 'dt', 'dd', 'div'];

    public function __construct(private NewsWriter $ai)
    {
    }

    /**
     * @param  bool  $html  true for the rich-text editor's HTML: paragraphs, headings, lists and tables stay;
     *                      bold / links in the middle of a translated paragraph are not kept
     *
     * @throws RuntimeException with a message for the admin
     */
    public function toEnglish(string $text, bool $html = false): string
    {
        if (!NewsWriter::hasIndic($text)) {
            return $text;
        }

        try {
            return $html ? $this->html($text) : $this->pieces([self::withoutMarkers($text)])[0];
        } catch (Throwable $e) {
            Log::warning('Google translation failed: ' . $e->getMessage());
        }

        try {
            return $this->ai->translate($text, $html);
        } catch (RuntimeException $e) {
            throw new RuntimeException('Google Translate did not answer and the free AI could not help either (' . rtrim($e->getMessage(), '.') . '). Please try again in a minute.');
        }
    }

    /**
     * Translate several pieces of plain text, a few per request. Pieces with no Gujarati or Hindi in
     * them come back as they are. Line breaks inside a piece are kept.
     *
     * @param  string[]  $texts
     * @return string[] in the same order
     */
    private function pieces(array $texts): array
    {
        $out = $texts;
        $batch = [];
        $size = 0;
        $send = function () use (&$batch, &$size, &$out) {
            if (!$batch) {
                return;
            }
            foreach ($this->request(array_values($batch)) as $i => $english) {
                $out[array_keys($batch)[$i]] = $english;
            }
            $batch = [];
            $size = 0;
        };

        foreach ($texts as $key => $text) {
            if (!NewsWriter::hasIndic($text)) {
                continue;
            }
            // One very long paragraph: sentence by sentence, joined again afterwards
            if (mb_strlen($text) > self::BATCH_CHARS) {
                $send();
                $out[$key] = implode(' ', array_map('trim', $this->pieces(preg_split('/(?<=[.!?।])\s+/u', $text))));
                continue;
            }
            if ($size + mb_strlen($text) > self::BATCH_CHARS) {
                $send();
            }
            $batch[$key] = $text;
            $size += mb_strlen($text);
        }
        $send();

        return $out;
    }

    /**
     * One request: every piece as its own "q", answered in the same order.
     *
     * @param  string[]  $texts
     * @return string[]
     */
    private function request(array $texts): array
    {
        $response = Http::withBody(implode('&', array_map(fn ($text) => 'q=' . rawurlencode($text), $texts)), 'application/x-www-form-urlencoded')
            ->connectTimeout(8)->timeout(25)->post(self::ENDPOINT);

        $rows = $response->successful() ? $response->json() : null;
        if (!is_array($rows) || count($rows) !== count($texts)) {
            throw new RuntimeException('HTTP ' . $response->status() . ', ' . (is_array($rows) ? count($rows) . ' answers for ' . count($texts) . ' pieces' : 'not the expected answer'));
        }

        return array_map(function ($row) {
            $english = is_array($row) ? ($row[0] ?? null) : $row; // ["text", "gu"], or just "text"
            if (!is_string($english) || trim($english) === '') {
                throw new RuntimeException('an empty translation came back');
            }

            return $english;
        }, $rows);
    }

    /** The editor's HTML: each paragraph / heading / list item / table cell is translated as one piece. */
    private function html(string $html): string
    {
        $doc = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="UTF-8"><div id="ip-translate-root">' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $xpath = new DOMXPath($doc);
        $root = $xpath->query('//div[@id="ip-translate-root"]')->item(0);
        if (!$root) {
            throw new RuntimeException('the article could not be read');
        }

        // Text copied from a web page (e.g. Google search results) brings invisible parts with it: hidden
        // tracking spans such as "TgQPHd|||[]", scripts, comments. A browser never shows them, but they would
        // turn into visible text once a paragraph is rewritten, so they are removed first.
        $hidden = './/script | .//style | .//template | .//comment() | .//*[@hidden] | .//*[@aria-hidden="true"]'
            . ' | .//*[contains(translate(@style, " ", ""), "display:none")] | .//*[contains(translate(@style, " ", ""), "visibility:hidden")]';
        foreach (iterator_to_array($xpath->query($hidden, $root)) as $node) {
            $node->parentNode?->removeChild($node);
        }

        // What gets translated: [the node to fill, its text]
        $units = [];
        $done = [];
        $blockTest = implode(' or ', array_map(fn ($tag) => "self::{$tag}", [...self::BLOCKS, 'ul', 'ol', 'table', 'tr', 'tbody', 'thead']));
        foreach ($xpath->query('.//*[' . implode(' or ', array_map(fn ($tag) => "self::{$tag}", self::BLOCKS)) . "][not(.//*[{$blockTest}])]", $root) as $block) {
            if (!NewsWriter::hasIndic($block->textContent)) {
                continue;
            }
            // <p><strong>…</strong></p>: keep the wrapper, translate what is inside it
            $target = $block;
            while ($target->childNodes->length === 1 && $target->firstChild instanceof DOMElement && $target->firstChild->nodeName !== 'br') {
                $target = $target->firstChild;
            }
            $units[] = [$target, $this->plainText($target)];
            $done[spl_object_id($block)] = $block; // the object is kept, so its id stays this element's
        }
        // Text that is not inside one of those (straight in the editor, or next to a nested list)
        foreach ($xpath->query('.//text()', $root) as $node) {
            if (!NewsWriter::hasIndic($node->nodeValue)) {
                continue;
            }
            for ($parent = $node->parentNode; $parent && $parent !== $root; $parent = $parent->parentNode) {
                if (isset($done[spl_object_id($parent)])) {
                    continue 2;
                }
            }
            $units[] = [$node, $node->nodeValue];
        }

        $english = $this->pieces(array_map(fn ($unit) => trim(self::withoutMarkers($unit[1])), $units));

        foreach ($units as $i => [$node, $source]) {
            // Keep the spaces that were around the text
            $text = (preg_match('/^\s+/u', $source, $m) ? $m[0] : '') . trim($english[$i]) . (preg_match('/\s+$/u', $source, $m) ? $m[0] : '');
            if ($node instanceof DOMElement) {
                while ($node->firstChild) {
                    $node->removeChild($node->firstChild);
                }
                foreach (explode("\n", $text) as $n => $line) {
                    if ($n > 0) {
                        $node->appendChild($doc->createElement('br'));
                    }
                    $node->appendChild($doc->createTextNode($line));
                }
            } else {
                $node->nodeValue = $text;
            }
        }

        $out = '';
        foreach ($root->childNodes as $child) {
            $out .= $doc->saveHTML($child);
        }

        return $out;
    }

    /** Google's search pages put markers like "TgQPHd|||[]" in the copied text: not part of the article */
    private static function withoutMarkers(string $text): string
    {
        return preg_replace('/[ \t]*\b[A-Za-z0-9_-]{4,12}\|\|\|\[[^\]\n]{0,40}\][ \t]*/u', ' ', $text);
    }

    /** The text of an element, with <br> as a line break */
    private function plainText(DOMNode $node): string
    {
        $text = '';
        foreach ($node->childNodes as $child) {
            $text .= $child->nodeName === 'br' ? "\n" : ($child->hasChildNodes() ? $this->plainText($child) : $child->textContent);
        }

        return $text;
    }
}
