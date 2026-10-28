<?php

namespace App\Support;

/**
 * Turns the plain text a writer types in the public blog form into a formatted article (HTML, cleaned
 * by Html::clean when shown), so they don't need to know any formatting:
 *   blank line                        new paragraph (a single line break stays a line break)
 *   short line without end punctuation, followed by more text      heading
 *   lines starting with "-", "*" or "•"                            bullet list
 *   lines starting with "1." / "1)"                                numbered list
 *   **words**                         bold
 *   https://... / www....             clickable link
 *   [Image 2] on its own line         that picture (added with the form's "Add image" button)
 */
class BlogFormatter
{
    /** @param array<int, string> $images picture number => its URL */
    public static function toHtml(string $text, array $images = []): string
    {
        $html = [];
        foreach (preg_split('/\R{2,}/u', trim(str_replace("\u{00A0}", ' ', $text))) as $block) {
            $lines = array_values(array_filter(array_map('trim', preg_split('/\R/u', $block)), 'strlen'));
            $paragraph = [];
            $list = null; // ['ul'|'ol', [items]]

            $flushParagraph = function () use (&$paragraph, &$html) {
                if ($paragraph) {
                    $html[] = '<p>' . implode('<br>', array_map([self::class, 'inline'], $paragraph)) . '</p>';
                    $paragraph = [];
                }
            };
            $flushList = function () use (&$list, &$html) {
                if ($list) {
                    $html[] = "<{$list[0]}>" . implode('', array_map(fn ($item) => '<li>' . self::inline($item) . '</li>', $list[1])) . "</{$list[0]}>";
                    $list = null;
                }
            };

            foreach ($lines as $i => $line) {
                if (preg_match('/^\[\s*image\s*(\d+)\s*\]$/iu', $line, $m)) {
                    $flushParagraph();
                    $flushList();
                    if (isset($images[(int) $m[1]])) {
                        $html[] = '<p><img src="' . e($images[(int) $m[1]]) . '" alt="Blog picture ' . (int) $m[1] . '"></p>';
                    }
                } elseif (preg_match('/^(?:[-*•▪●]\s+)(.+)$/u', $line, $m) || preg_match('/^\d{1,2}[.)]\s+(.+)$/u', $line, $n)) {
                    $type = isset($n[1]) ? 'ol' : 'ul';
                    $flushParagraph();
                    if ($list && $list[0] !== $type) {
                        $flushList();
                    }
                    $list ??= [$type, []];
                    $list[1][] = $n[1] ?? $m[1];
                    unset($m, $n);
                } elseif (isset($lines[$i + 1]) && mb_strlen($line) <= 70 && !preg_match('/[.,:;!?।…)\]"\']$/u', $line)) {
                    $flushParagraph();
                    $flushList();
                    $html[] = '<h3>' . self::inline($line) . '</h3>';
                } else {
                    $flushList();
                    $paragraph[] = $line;
                }
            }
            $flushParagraph();
            $flushList();
        }

        return implode("\n", $html);
    }

    /** Picture numbers the text uses ([Image 1], [Image 3] ...) */
    public static function imageNumbers(string $text): array
    {
        preg_match_all('/^\s*\[\s*image\s*(\d+)\s*\]\s*$/imu', $text, $m);

        return array_map('intval', array_unique($m[1]));
    }

    /** One line: escaped, with **bold** and links */
    private static function inline(string $text): string
    {
        $text = e($text);
        $text = preg_replace('/\*\*(.+?)\*\*/u', '<strong>$1</strong>', $text);

        return preg_replace_callback('~\b(https?://|www\.)[^\s<]+[^\s<.,;:!?)\]"\']~iu', function ($m) {
            $url = html_entity_decode($m[0], ENT_QUOTES);
            $href = str_starts_with(strtolower($url), 'www.') ? 'https://' . $url : $url;

            return '<a href="' . e($href) . '" target="_blank">' . $m[0] . '</a>';
        }, $text);
    }
}
