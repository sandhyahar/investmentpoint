<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * "Write with AI": an original short English news report (about 200-250 words) from the facts
 * of one headline + its feed summary, for the admin to check and edit before publishing.
 * Google Gemini (paid, Admin -> Settings -> Gemini AI), else the free Groq, else Hugging Face: each one is
 * the backup of the one before (see ask()).
 */
class NewsWriter
{
    public const NOT_SET = 'AI writing is not set up yet. Paste a Gemini API key in Admin -> Site Settings -> Gemini AI (or a free Groq key).';

    /** The service that wrote the last reply (gemini, groq or huggingface) */
    private ?string $lastProvider = null;

    private const SYSTEM = <<<'TXT'
You are a news writer for Investment Point, an Indian business and stock market news website.
Write an ORIGINAL short news report in simple, clear English for Indian investors.
Rules:
- Use only the facts given. Never invent numbers, prices, percentages, names, quotes or dates.
- Do not copy the given sentences or phrases; write everything in your own words.
- The first paragraph states the news itself and must include EVERY company name, person, amount, percentage and date from the headline and summary.
- Copy every figure exactly as given (for example ₹722.05, 1.8%, Rs 10,000 crore). Never write vague words instead of a given figure, such as "a certain percentage", "a lower price" or "a significant amount".
- Do not guess reasons, motives or plans (for example "part of the government's disinvestment") unless they are in the input.
- No speculation or comparison that is not in the input: no "likely", "expected to be one of the largest", "record", "first", "biggest", "may be a cause for concern".
- The headline must say exactly what the input says: do not move a fact onto the wrong thing (a "Blackstone-backed company" is not a "Blackstone-backed dividend").
- You may add at most one short sentence of general background per paragraph explaining a term (what a block deal, repo rate or IPO is). Never explain what "the stock market" or "shares" are.
- Never state anything about the company itself (its business, size, growth, results, history, listing, management) unless it is in the input.
- Neutral tone. No investment advice.
- News about a company or its shares (SEBI guidelines): call a stop loss "support" and a target or target price "resistance level" (for example "a resistance level of ₹1,250", never "a target of ₹1,250").
- Never write a buy, sell, hold, accumulate, add, reduce, outperform, underperform, overweight or underweight rating or call, not even when reporting a brokerage's view: leave the rating out and keep only the facts. "Sell" or "buy" may only describe a real deal (a promoter selling a stake), never advice.
- Write it as Investment Point's own report: do not name or mention any newspaper, website or news channel.
- No markdown, no bullet points, no headings inside the article.
TXT;

    public function isConfigured(): bool
    {
        return $this->provider() !== null;
    }

    /**
     * @return array{title: string, description: string, content: string, provider: string}
     */
    public function draft(string $headline, ?string $summary, ?string $source): array
    {
        $provider = $this->provider()
            ?? throw new RuntimeException(self::NOT_SET);

        $prompt = "Headline: {$headline}\n"
            . 'Summary: ' . ($summary ?: '(none)') . "\n\n"
            . "Reply in exactly this format:\n"
            . "TITLE: <a new, clear, SEO-friendly headline of 8 to 14 words (at most 100 characters) that names the company or topic and says what happened>\n"
            . "SUMMARY: <one or two full sentences, 110 to 160 characters in total, saying what happened and why it matters>\n"
            . "KEYWORDS: <5 to 8 search keywords Indian investors would type, comma separated, e.g. company name share price, sector stocks, NSE, Sensex>\n"
            . "ARTICLE:\n<220 to 240 words in 4 short paragraphs (never under 200, never over 250). Do not repeat the title or summary here. The last paragraph starts with \"What it means for investors:\">";

        $messages = [['role' => 'system', 'content' => self::SYSTEM], ['role' => 'user', 'content' => $prompt]];

        try {
            $ask = fn (array $messages) => $this->ask($messages);
            $text = $ask($messages);

            // Keep it in the 200-250 word range: up to two rewrites, keeping the closest to ~230 words
            for ($try = 0, $words = $this->articleWords($text); $try < 2 && ($words < 200 || $words > 250); $try++) {
                $retry = $ask(array_merge($messages, [
                    ['role' => 'assistant', 'content' => $text],
                    ['role' => 'user', 'content' => "The ARTICLE has {$words} words. Rewrite the whole reply in the same format with an ARTICLE of 220 to 240 words in 4 paragraphs, following all the rules. " . ($words < 200 ? 'Add only general explanation of terms - no new facts.' : 'Make each paragraph shorter, keeping every fact and figure from the input.')],
                ]));
                $retryWords = $this->articleWords($retry);
                if (abs($retryWords - 230) < abs($words - 230)) {
                    [$text, $words] = [$retry, $retryWords];
                }
            }
        } catch (RuntimeException $e) {
            throw $e;
        } catch (Throwable $e) {
            Log::warning('AI news writer failed: ' . $e->getMessage());
            throw new RuntimeException('Could not reach the free AI service. Please try again in a moment.');
        }

        $draft = $this->parse($text);

        // Headlines of 2-5 words ("Metal Stocks Fall") are weak for readers and search: ask once for a fuller one
        if (str_word_count($draft['title']) < 7) {
            try {
                $better = trim($ask([
                    ['role' => 'system', 'content' => self::SYSTEM],
                    ['role' => 'user', 'content' => "Original headline: {$headline}\nSummary: " . ($summary ?: '(none)') . "\n\nWrite ONE new news headline of 10 to 14 words in your own words. Include the company or topic names and the key figure from the input. Reply with the headline only."],
                ]), " \"'\n");
                $better = preg_replace('/^(TITLE|Headline)\s*:\s*/i', '', strtok($better, "\n"));
                if (str_word_count($better) >= 7 && mb_strlen($better) <= 120) {
                    $draft['title'] = $better;
                }
            } catch (Throwable $e) {
                // keep the short headline
            }
        }

        // SEBI wording: stop loss -> support, target -> resistance level; a buy / sell / hold call is rewritten once
        $draft = self::sebiDraft($draft);
        if ($calls = self::recommendationWords(self::draftText($draft))) {
            try {
                $retry = self::sebiDraft($this->parse($ask(array_merge($messages, [
                    ['role' => 'assistant', 'content' => $text],
                    ['role' => 'user', 'content' => 'The reply gives a recommendation (' . implode(', ', $calls) . '), which SEBI guidelines do not allow. '
                        . 'Rewrite the whole reply in the same format with no buy, sell, hold, accumulate, add, reduce, outperform, underperform, overweight or underweight rating or call: leave the rating out, '
                        . 'write "support" for a stop loss and "resistance level" for a target, and keep every other fact and figure.'],
                ]))));
                if (count(self::recommendationWords(self::draftText($retry))) < count($calls)) {
                    $draft = ['title' => $retry['title'] ?: $draft['title']] + $retry;
                }
            } catch (Throwable $e) {
                // keep the first draft: auto-publish holds it, the admin edits it
            }
        }

        return $draft + ['provider' => $this->lastProvider ?? $provider];
    }

    /** Title, summary, keywords and article of a draft as one text */
    public static function draftText(array $draft): string
    {
        return implode(' ', [$draft['title'] ?? '', $draft['description'] ?? '', $draft['keywords'] ?? '', $draft['content'] ?? '']);
    }

    /** sebiWording() on every text part of a draft (or of a saved article's columns) */
    public static function sebiDraft(array $draft): array
    {
        foreach (['title', 'description', 'keywords', 'content'] as $part) {
            if (isset($draft[$part]) && is_string($draft[$part])) {
                $draft[$part] = self::sebiWording($draft[$part]);
            }
        }

        return $draft;
    }

    /**
     * SEBI wording for stock news: "stop loss" becomes "support" and a price target becomes "resistance
     * level" ("target price of ₹400" -> "resistance level of ₹400"). A business target such as
     * "revenue target of ₹10,000 crore" is left alone. The capital letters of the original are kept.
     */
    public static function sebiWording(string $text): string
    {
        $word = function (string $found, string $new) {
            if (preg_match('/^\p{Lu}/u', $found)) {
                // "Target Price" (title case) -> "Resistance Level", "Target price" -> "Resistance level"
                return preg_match('/\s\p{Lu}/u', $found) ? ucwords($new) : ucfirst($new);
            }

            return $new;
        };

        $text = preg_replace_callback('/\bstop[\s-]?loss(es)?\b/iu',
            fn ($m) => $word($m[0], 'support' . (empty($m[1]) ? '' : 's')), $text);
        $text = preg_replace_callback('/\b(?:price[\s-]targets?|target[\s-]prices?)\b/iu',
            fn ($m) => $word($m[0], 'resistance level' . (preg_match('/s$/i', $m[0]) ? 's' : '')), $text);

        // "target of ₹400" / "targets at Rs 1,250": a share price, not an amount in crore / lakh / billion
        return preg_replace_callback('/\b(?:upside\s+)?target(s)?\b(?=\s+(?:of|at|near|around|is|was|to)?\s*(?:₹|rs\.?|inr)\s?\d[\d,]*(?:\.\d+)?(?![\d,.]|\s*(?:crore|cr\b|lakh|billion|bn\b|million|mn\b|trillion|k\b)))/iu',
            fn ($m) => $word($m[0], 'resistance level' . (empty($m[1]) ? '' : 's')), $text);
    }

    /**
     * Buy / sell / hold calls left in a news text ("maintains Buy", "a sell rating", "investors should
     * accumulate"), plus the promotional phrases of promotionalWords(). A real deal ("promoter sells a
     * 2% stake", "RBI keeps rates on hold") is not a call. [] = nothing to fix.
     */
    public static function recommendationWords(string $text): array
    {
        $text = html_entity_decode(strip_tags($text), ENT_QUOTES);
        $rating = '(?:strong[\s-])?(?:buy|sell|hold|accumulate|add|reduce|neutral|outperform|underperform|overweight|underweight|equal[\s-]weight|market[\s-]perform)';
        $patterns = [
            // "buy rating", "'Sell' call", "hold recommendation"
            '/\b' . $rating . '[\'’"”]?\s+(?:rating|call|recommendation|tag)s?\b/iu',
            // "maintains Buy", "upgraded the stock to accumulate", "initiated coverage with a 'buy'"
            '/\b(?:maintain|retain|reiterat|recommend|upgrad|downgrad|initiat|suggest|advis)\w*\s+(?:(?:the\s+)?(?:stock|shares?|scrip|counter|it|coverage)\s+)?(?:(?:to|with|at|as)\s+)?(?:an?\s+|its\s+|their\s+)?[\'‘"“]?'
                . $rating . '\b(?!(?<=hold)\s+(?:on|over|of|in)\b)(?!\s+(?:stance|policy|outlook|position|view)\b)/iu',
            '/\brated\s+(?:the\s+)?(?:(?:stock|shares?|scrip|counter|it)\s+)?(?:as\s+)?[\'‘"“]?' . $rating . '\b/iu',
            // "investors should buy", "traders may consider selling"
            '/\b(?:investors?|traders?|one)\s+(?:can|could|may|might|should|must)\s+(?:consider\s+)?(?:buy|buying|sell|selling|hold|holding|accumulate|accumulating|exit|avoid|add|adding)\b/iu',
            // "buy on dips", "sell on rise"
            '/\b(?:buy|sell)\s+(?:(?:the|this)\s+(?:stock|shares?)\s+)?on\s+(?:dips?|rises?|declines?)\b/iu',
        ];

        $found = self::promotionalWords($text);
        foreach ($patterns as $pattern) {
            preg_match_all($pattern, $text, $m);
            array_push($found, ...array_map('mb_strtolower', $m[0]));
        }

        return array_values(array_unique($found));
    }

    /**
     * A published AI news article that still gives a buy / sell / hold call, rewritten without it.
     * Null when the AI keeps the call or adds a figure (the article is then hidden by the caller).
     *
     * @throws RuntimeException when the AI can't be reached (try again later)
     */
    public function withoutRecommendations(array $article): ?array
    {
        $calls = self::recommendationWords(self::draftText($article));
        $plain = html_entity_decode(strip_tags(str_replace('</p>', "</p>\n", (string) $article['content'])), ENT_QUOTES);
        $reply = $this->ask([
            ['role' => 'system', 'content' => self::SYSTEM],
            ['role' => 'user', 'content' => 'This published news article gives a recommendation (' . implode(', ', $calls) . '), which SEBI guidelines do not allow. '
                . "Rewrite it with no buy, sell, hold, accumulate, add, reduce, outperform, underperform, overweight or underweight rating or call: leave the rating out, "
                . "write \"support\" for a stop loss and \"resistance level\" for a target. Change only those sentences; keep every other fact, figure and paragraph.\n\n"
                . "TITLE: {$article['title']}\nSUMMARY: {$article['description']}\nKEYWORDS: " . ($article['keywords'] ?? '') . "\nARTICLE:\n{$plain}\n\n"
                . 'Reply in exactly the same TITLE / SUMMARY / KEYWORDS / ARTICLE format.'],
        ]);

        try {
            $draft = self::sebiDraft($this->parse($reply));
        } catch (RuntimeException $e) {
            return null; // reply too short to be the article
        }
        $draft['title'] = $draft['title'] ?: self::sebiWording((string) $article['title']);
        $draft['description'] = $draft['description'] ?: self::sebiWording((string) $article['description']);
        $draft['keywords'] = $draft['keywords'] ?: self::sebiWording((string) ($article['keywords'] ?? ''));

        return self::recommendationWords(self::draftText($draft)) || $this->newNumbers($draft, $article['title'], $article['description'] . ' ' . $plain)
            ? null : $draft;
    }

    /** Gujarati or Hindi (Devanagari) letters */
    private const INDIC = '/[\x{0900}-\x{097F}\x{0A80}-\x{0AFF}]/u';

    /** Source text sent to the AI in one request (Gujarati / Hindi letters cost more of the AI's size limit than English) */
    private const TRANSLATE_CHUNK = 1200;

    public static function hasIndic(?string $text): bool
    {
        return (bool) preg_match(self::INDIC, (string) $text);
    }

    /**
     * Admin "auto-translate to English": Gujarati or Hindi text pasted into an article form, translated by
     * the free AI. HTML from the editor keeps its tags: it is translated a few paragraphs at a time, so a
     * long article fits the AI's reply size. Parts that are already English are not sent at all.
     *
     * @throws RuntimeException with a message for the admin
     */
    public function translate(string $text, bool $html = false): string
    {
        if (!self::hasIndic($text)) {
            return $text;
        }
        $provider = $this->provider()
            ?? throw new RuntimeException(self::NOT_SET);

        $system = "You are a professional translator for Investment Point, an Indian business newspaper and stock market website. "
            . "Translate Gujarati or Hindi into clear, natural English as an Indian business journalist would write it.\n"
            . "Rules:\n"
            . "- Translate faithfully and completely: do not add, remove, shorten, summarise or explain anything.\n"
            . "- Keep every number, percentage, date, price, ₹ amount, company name, person name, index name and stock symbol exactly.\n"
            . "- Use the usual English market terms (Sensex, Nifty, bullish, bearish, support, resistance, FII, DII, IPO).\n"
            . "- Words that are already English stay as they are.\n"
            . ($html ? "- The text is HTML. Keep every HTML tag and attribute exactly as it is, in the same order, and translate only the text between the tags.\n" : '')
            . "- Reply with the translation only: no notes, no quotation marks around it, no \"Translation:\" label.";

        $out = '';
        foreach (self::translationChunks($text, $html) as $chunk) {
            if (!self::hasIndic($chunk)) {
                $out .= $chunk;
                continue;
            }
            $messages = [['role' => 'system', 'content' => $system], ['role' => 'user', 'content' => $chunk]];
            try {
                $reply = $this->ask($messages, 1800, 0.2);
            } catch (RuntimeException $e) {
                throw $e;
            } catch (Throwable $e) {
                Log::warning('AI translation failed: ' . $e->getMessage());
                throw new RuntimeException('Could not reach the free AI service. Please try again in a moment.');
            }

            // Small models sometimes wrap the answer: ```html ... ```, "Translation: ..."
            $reply = trim(preg_replace('/^```[a-z]*\s*|\s*```$/i', '', trim($reply)));
            $reply = trim(preg_replace('/^(here is the translation|translation|english translation)\s*:\s*/i', '', $reply));
            if (!$html) {
                $reply = trim($reply, " \t\n\r\"'“”‘’");
            }
            if ($reply === '') {
                throw new RuntimeException('The AI returned an empty translation. Please try again.');
            }
            // Keep the space / line break that separated this part from the next
            $out .= $reply . (preg_match('/\s+$/u', $chunk, $tail) ? $tail[0] : '');
        }

        return $out;
    }

    /**
     * The text in parts of about TRANSLATE_CHUNK characters, cut only between paragraphs / list items /
     * table rows (HTML) or lines and sentences (plain text). Parts with no Gujarati or Hindi in them
     * come out on their own, so they can be skipped.
     *
     * @return string[]
     */
    private static function translationChunks(string $text, bool $html): array
    {
        $pieces = $html
            ? preg_split('/(?<=<\/p>|<\/li>|<\/tr>|<\/ul>|<\/ol>|<\/h1>|<\/h2>|<\/h3>|<\/h4>|<\/h5>|<\/h6>|<\/div>|<\/table>|<\/blockquote>)/i', $text)
            : preg_split('/(?<=\n)/u', $text);

        // A single very long paragraph: cut it at sentence ends ("." "!" "?" or the Hindi full stop)
        $pieces = array_merge(...array_map(fn ($piece) => mb_strlen($piece) > self::TRANSLATE_CHUNK
            ? preg_split('/(?<=[.!?।])(?=\s)/u', $piece) : [$piece], array_values(array_filter($pieces, fn ($piece) => $piece !== ''))));

        $chunks = [];
        $buffer = '';
        foreach ($pieces as $piece) {
            $indic = self::hasIndic($piece);
            // English-only pieces, and anything that would make the request too big, start a new part
            if ($buffer !== '' && (!$indic || !self::hasIndic($buffer) || mb_strlen($buffer) + mb_strlen($piece) > self::TRANSLATE_CHUNK)) {
                $chunks[] = $buffer;
                $buffer = '';
            }
            $buffer .= $piece;
        }
        if ($buffer !== '') {
            $chunks[] = $buffer;
        }

        return $chunks;
    }

    /** What the admin's title is for: wording of the request to the AI (admin "Suggest SEO titles") */
    public const TITLE_TYPES = [
        'analysis' => 'a technical analysis article about a stock or index (its share price, support and resistance levels, chart trend)',
        'news' => 'a stock market / business news article',
        'article' => 'an educational article on investing, the stock market or the economy',
    ];

    /**
     * Admin "Suggest SEO titles": five search-friendly titles from what the admin typed in the Title box
     * (a stock name, a few words or a full title). Free AI first; for Technical Analysis, ready-made
     * SEO patterns are returned when the AI can't be reached (e.g. the daily allowance is used up).
     *
     * @return array{titles: string[], source: string, note: ?string}
     */
    public function titles(string $topic, string $type = 'news', ?string $support = null, ?string $resistance = null): array
    {
        $topic = trim(preg_replace('/\s+/u', ' ', $topic));
        $about = self::TITLE_TYPES[$type] ?? self::TITLE_TYPES['news'];

        try {
            $provider = $this->provider()
                ?? throw new RuntimeException(self::NOT_SET);

            $system = "You write headlines for Investment Point, an Indian business newspaper and stock market website. "
                . "Headlines must follow SEBI guidelines: factual and neutral, with no buy, sell, hold, accumulate or exit calls, no price targets or stop losses, "
                . "no promises of returns and no words like guaranteed, multibagger, jackpot, must buy or best stock.";
            $prompt = "The editor typed this in the title box: \"{$topic}\"\n"
                . ($type === 'analysis' && ($support || $resistance) ? 'Support: ' . ($support ?: '-') . ', Resistance: ' . ($resistance ?: '-') . " (you may mention these levels, no other figures)\n" : '')
                . "It is for {$about}.\n\n"
                . "Write 5 different SEO-friendly titles for it. Rules:\n"
                . "- 50 to 70 characters each, in clear English, Title Case.\n"
                . "- Start with or include the company, index or topic name exactly as people search for it.\n"
                . "- Use natural search words (" . ($type === 'analysis' ? 'share price, technical analysis, support, resistance, key levels' : 'the main keyword of the topic, share price, stock market, India') . ") only where they fit.\n"
                . "- Do not invent any fact, number, date or event that is not in the text above.\n"
                . "- No clickbait, no question marks, no quotation marks, no emojis, no numbering.\n"
                . "Reply with exactly 5 lines, one title per line, nothing else.";

            $messages = [['role' => 'system', 'content' => $system], ['role' => 'user', 'content' => $prompt]];
            $titles = self::titleLines($this->ask($messages));
            if (count($titles) >= 2) {
                return ['titles' => array_slice($titles, 0, 5), 'source' => $this->lastProvider ?? $provider, 'note' => null];
            }

            throw new RuntimeException('The AI did not return usable titles. Please try again.');
        } catch (RuntimeException $e) {
            if ($type !== 'analysis') {
                throw $e;
            }

            return ['titles' => self::analysisTitlePatterns($topic, $support, $resistance), 'source' => 'patterns',
                'note' => $e->getMessage() . ' These are ready-made SEO title patterns instead.'];
        } catch (Throwable $e) {
            Log::warning('AI title suggestions failed: ' . $e->getMessage());
            if ($type !== 'analysis') {
                throw new RuntimeException('Could not reach the free AI service. Please try again in a moment.');
            }

            return ['titles' => self::analysisTitlePatterns($topic, $support, $resistance), 'source' => 'patterns',
                'note' => 'Could not reach the free AI service. These are ready-made SEO title patterns instead.'];
        }
    }

    /** One title per line out of the AI's reply: numbering, bullets and quotes removed; unusable or advice-like lines dropped. */
    private static function titleLines(string $text): array
    {
        $titles = [];
        foreach (preg_split('/\R+/', $text) as $line) {
            $line = trim(preg_replace('/^\s*(?:\d+[\.\)\:]|[-*•])\s*/u', '', trim($line)), " \t\"'“”‘’*");
            $length = mb_strlen($line);
            if ($length < 20 || $length > 110 || str_ends_with($line, ':')) {
                continue;
            }
            if (preg_match('/\b(buy|sell|hold|accumulate|target|stop[ -]?loss|multibagger|guaranteed|jackpot|must[ -]buy|best stock)\b/i', $line)) {
                continue; // not allowed under SEBI guidelines
            }
            $titles[mb_strtolower($line)] = $line;
        }

        return array_values($titles);
    }

    /** Ready-made SEO titles for a Technical Analysis, built around the stock / index name the admin typed. */
    private static function analysisTitlePatterns(string $topic, ?string $support, ?string $resistance): array
    {
        // "GODREJ PROPERTY: support & resistance" -> "Godrej Property"
        $name = trim(preg_replace('/\s*[:\-–|].*$/u', '', $topic));
        $name = trim(preg_replace('/\b(technical analysis|technical view|share price|stock|shares?|support|resistance|key levels?|analysis|outlook)\b/iu', '', $name)) ?: $topic;
        $name = trim(preg_replace('/\s+/u', ' ', $name));
        if ($name === mb_strtoupper($name) && mb_strlen($name) > 5) {
            $name = mb_convert_case(mb_strtolower($name), MB_CASE_TITLE); // ALL CAPS -> Title Case (short tickers like TCS stay)
        }

        $titles = [
            "{$name} Share Price: Support, Resistance & Key Levels",
            "{$name} Technical Analysis: Trend, Support & Resistance",
            "{$name} Share Price Outlook: Key Chart Levels to Watch",
            "{$name} Technical View: Trend, Momentum & Price Levels",
        ];
        if ($support && $resistance) {
            // Prices as on the site: ₹1,533 / ₹1,574 - ₹1,580
            array_splice($titles, 1, 0, ["{$name} Share Price: Support at " . \App\Models\TechnicalAnalysis::formatLevel($support) . ", Resistance at " . \App\Models\TechnicalAnalysis::formatLevel($resistance)]);
        } else {
            $titles[] = "{$name} Chart Analysis: Support and Resistance Explained";
        }

        return array_values(array_filter($titles, fn ($t) => mb_strlen($t) <= 110));
    }

    /** Fixed wording of every Technical Analysis report (written by the site, never by the AI) */
    public const REPORT_RECOMMENDATION = 'Neutral Editorial Assessment – No Buy / Sell Recommendation';
    public const REPORT_ASSESSMENT = 'Overall Assessment: Neutral and Observation-Based. No target price or Buy, Sell or Hold recommendation is assigned.';
    public const REPORT_NOTE = 'This report is intended exclusively for educational and informational purposes. It does not constitute personalised investment advice or a securities recommendation. Technical levels are subject to change. Investments in securities markets are subject to market risks.';

    /**
     * Technical Analysis "Write with AI": a public appearance report of the company in the title, in the
     * owner's fixed format (about 250 words). The AI writes only the company details, overview, market
     * trend and risk factor; the report date, recommendation line, red Overall Assessment and closing note
     * are added by the site word for word. Amounts never contain commas (1526, not 1,526).
     *
     * @return array{description: string, content: string, words: int, warnings: string[], provider: string}
     */
    public function analysis(string $title, ?string $support, ?string $resistance, ?string $cmp = null, ?string $date = null): array
    {
        $provider = $this->provider()
            ?? throw new RuntimeException(self::NOT_SET);

        $system = <<<'TXT'
You write public appearance reports on Indian listed companies for Investment Point, an Indian business newspaper and stock market website.
Every report follows the SEBI (Research Analysts) Regulations and SEBI (Investment Advisers) Regulations guidelines for public appearances: fair, balanced, factual, educational and free of any recommendation.
Write in simple, clear English for Indian retail investors.
Rules:
- Write every sentence in your own original words. Never copy text from other websites, news articles, reports or books.
- The ONLY share price figures you may use are the CMP, support and resistance levels given. Never invent prices, targets, stop losses, percentages, results, revenue, profit, market cap, dates or indicator values.
- Never write a number with a comma: write 1526, never 1,526.
- No buy, sell, hold, accumulate, add, exit, book profit or avoid calls; no price targets, entry or exit points or stop losses.
- Never use promotional or misleading words: guaranteed, assured, sure shot, risk-free, safe bet, multibagger, jackpot, huge returns, must buy, best stock, do not miss, hurry, double your money.
- Use careful words such as "may", "generally", "could". Never promise returns.
- Do not add any disclaimer, disclosure or "educational purposes" line: the website adds its own.
- No markdown, no bullet points, no headings.
TXT;

        $facts = "Title: {$title}\n"
            . 'CMP: ' . ($cmp ? "₹{$cmp}" : '(not given)') . "\n"
            . 'Support: ' . ($support ? "₹{$support}" : '(not given)') . "\n"
            . 'Resistance: ' . ($resistance ? "₹{$resistance}" : '(not given)');
        $prompt = "Write a public appearance report on the company named in this title.\n{$facts}\n\n"
            . "Reply in exactly this format, each label on its own line:\n"
            . "COMPANY: <the company's full name>\n"
            . "NSE: <its NSE symbol>\n"
            . "BSE: <its BSE scrip code>\n"
            . "SECTOR: <its sector>\n"
            . "SUMMARY: <one sentence of 15 to 25 words about this report on the company>\n"
            . "OVERVIEW: <about 50 words: what the company does, its main businesses and where it operates. No heading.>\n"
            . "TREND: <80 to 90 words in exactly 2 paragraphs separated by a blank line: the market trend for this company and its sector in general terms, and how the CMP, support and resistance given (if any) may be watched. Balanced, no prediction.>\n"
            . "RISK: <about 40 words: the main risk factors for this company and its sector>";

        $messages = [['role' => 'system', 'content' => $system], ['role' => 'user', 'content' => $prompt]];
        $ask = fn (array $messages) => $this->ask($messages);
        $complete = fn (array $p) => $p['overview'] !== '' && $p['trend'] !== '' && $p['risk'] !== '';

        try {
            $parts = self::reportParts($ask($messages));
            // Missing or far too short parts: one rewrite
            if (!$complete($parts) || str_word_count($parts['trend']) < 60) {
                $retry = self::reportParts($ask(array_merge($messages, [['role' => 'user', 'content' => 'Reply again in exactly the format asked, with every label filled: OVERVIEW about 50 words, TREND 80 to 90 words in 2 paragraphs, RISK about 40 words.']])));
                if ($complete($retry)) {
                    $parts = $retry;
                }
            }
        } catch (RuntimeException $e) {
            throw $e;
        } catch (Throwable $e) {
            Log::warning('AI analysis writer failed: ' . $e->getMessage());
            throw new RuntimeException('Could not reach the free AI service. Please try again in a moment.');
        }
        if (!$complete($parts)) {
            throw new RuntimeException('The AI reply was incomplete. Please click "Write Full Analysis with AI" again.');
        }

        $name = $parts['company'] ?: self::stockName($title);
        $when = rescue(fn () => \Illuminate\Support\Carbon::parse($date ?: 'now', 'Asia/Kolkata'), fn () => now('Asia/Kolkata'), false);
        $line = fn (string $label, string $text) => '<p><strong>' . e($label) . ' :</strong> ' . e($text) . '</p>';
        $trend = array_values(array_filter(array_map('trim', preg_split('/\n\s*\n|\n/', $parts['trend']))));

        $html = implode("\n", array_filter([
            $line('Report Date', $when->format('j F Y')),
            '<p><strong>NSE Symbol :</strong> ' . e($parts['nse'] ?: '-') . ' | <strong>BSE Code :</strong> ' . e($parts['bse'] ?: '-') . '</p>',
            $line('Sector', $parts['sector'] ?: '-'),
            '<p>Recommendation : ' . e(self::REPORT_RECOMMENDATION) . '</p>',
            $line('Company Overview', $parts['overview']),
            $line('Market Trend', $trend[0] ?? ''),
            isset($trend[1]) ? '<p>' . e(implode(' ', array_slice($trend, 1))) . '</p>' : null,
            $line('Risk Factor', $parts['risk']),
            '<p style="color: #dc2626;"><strong>' . e(self::REPORT_ASSESSMENT) . '</strong></p>',
            '<p>' . e(self::REPORT_NOTE) . '</p>',
        ]));

        // Checks only on what the AI wrote (the fixed lines mention "target price" on purpose)
        $written = $parts['summary'] . ' ' . $parts['overview'] . ' ' . $parts['trend'] . ' ' . $parts['risk'];
        $invented = $this->newNumbers(['title' => '', 'description' => '', 'content' => $written],
            $title, trim(($cmp ?? '') . ' ' . ($support ?? '') . ' ' . ($resistance ?? '')));
        $promotional = self::promotionalWords($written);
        $description = str_word_count($parts['summary']) >= 10 ? $parts['summary']
            : "{$name} public appearance report: company overview, market trend and risk factors. Neutral, observation-based and for education only.";

        return [
            'description' => mb_substr($description, 0, 500),
            'content' => self::justified($html),
            'words' => str_word_count(strip_tags(str_replace('</p>', ' </p>', $html))),
            'warnings' => array_values(array_filter([
                'Check the NSE symbol, BSE code and sector the AI filled in: ' . ($parts['nse'] ?: '-') . ' / ' . ($parts['bse'] ?: '-') . ' / ' . ($parts['sector'] ?: '-'),
                $invented ? 'Check these figures - they are not in your title, CMP or levels: ' . implode(', ', $invented) : null,
                $promotional ? 'Not allowed under SEBI guidelines - please reword: ' . implode(', ', $promotional) : null,
            ])),
            'provider' => $this->lastProvider ?? $provider,
        ];
    }

    /** Fixed wording of the Nifty Derivative Market Trend Analysis (written by the site, never by the AI) */
    public const NIFTY_TITLE = 'Nifty Derivative Market Trend Analysis';
    public const NIFTY_SUPPORT = 'The immediate downside support zone may be assessed around [S Level 1] with further support near [S Level 2]. These levels should be verified against the previous trading session\'s Nifty closing price and put open interest concentration. A sustained move below the identified support zone may indicate weakening momentum. However open interest alone cannot confirm a breakdown.';
    public const NIFTY_RESISTANCE = 'The immediate upside resistance zone may be observed near [R Level 1] with the next hurdle around [R Level 2]. These levels should be confirmed using recent price highs and call open interest data. A sustained move above resistance with supportive volume may indicate improving momentum.';
    public const NIFTY_RECOMMENDATION = 'Neutral Editorial Assessment – No Buy / Sell Fully Recommendation.';

    /** A Market Trend title about the Nifty derivative market: written in the owner's fixed Nifty format */
    public static function isNiftyTrend(string $title): bool
    {
        return (bool) preg_match('/\bnifty\b/i', $title) && (bool) preg_match('/derivative|f\s*&\s*o|market trend|trend analysis/i', $title);
    }

    /**
     * Market Trend "Write Full Article with AI" for a Nifty Derivative Market Trend Analysis title, in the
     * owner's fixed format. The AI writes only the intro, market behaviour, two sectors and the outlook;
     * the headings, both Technical Analysis paragraphs, the report date and the recommendation line are
     * added by the site word for word. Without $facts the AI has no live market data, so every level or amount
     * is a [ ] placeholder for the admin to fill in, never a made-up figure. With $facts (App\Services\NiftyDayData,
     * the automatic 5 PM update) it may use only those real figures, the support / resistance levels are filled
     * in, and a reply with any other figure or a [ ] blank is refused (RuntimeException), so nothing invented is published.
     *
     * @return array{title: string, content: string, words: int, warnings: string[], provider: string}
     */
    public function niftyTrend(string $title, ?string $date = null, ?array $facts = null): array
    {
        $provider = $this->provider()
            ?? throw new RuntimeException(self::NOT_SET);

        $system = <<<'TXT'
You write the daily Nifty Derivative Market Trend Analysis for Investment Point, an Indian business newspaper and stock market website.
Every report follows the SEBI (Research Analysts) Regulations and SEBI (Investment Advisers) Regulations guidelines for public appearances: fair, balanced, factual, educational and free of any recommendation.
Write in simple, clear English for Indian retail investors.
Rules:
- Write every sentence in your own original words. Never copy text from other websites, news articles, reports or books.
__DATA_RULE__
- Never use quotation marks or inverted commas. Never write a number with a comma.
- No buy, sell, hold, accumulate, add, exit, book profit or avoid calls; no price targets, entry or exit points or stop losses.
- Never use promotional or misleading words: guaranteed, assured, sure shot, risk-free, safe bet, multibagger, jackpot, huge returns, must buy, best stock, do not miss, hurry, double your money.
- Use careful words such as may, generally, could. Never promise returns or predict the market with certainty.
- Do not add any disclaimer, disclosure or educational purposes line: the website adds its own.
- No markdown, no bullet points, no headings.
TXT;
        $system = str_replace('__DATA_RULE__', $facts
            ? '- Use ONLY the figures given in FACTS, written exactly as given there. Never write any other number, level, amount, percentage or date, and never use square brackets. If FACTS has no figure for something, describe it in words without a number.'
            : '- You have no live market data. Never invent a Nifty level, price, points, percentage, FII or DII amount, open interest figure or date. Wherever a figure belongs, write a placeholder in square brackets, for example [Nifty close], [points], [%], [₹ crore], [Level].', $system);

        // The automatic 5 PM update ($facts) follows the owner's own Nifty prompt (22 Oct 2026): an engaging, SEO-friendly
        // title after "Nifty Derivative Market Trend Analysis :", Intro + Market Overview about 50 words, Global / FIIs /
        // DIIs about 40 words each, any 2 sectors about 40 words each, Next Session Outlook about 40 words, about 250 words
        // written in all; the site adds the headings, both Technical Analysis texts word for word, the Report Date
        // and the Recommendation line. The "Write with AI" button (no $facts) keeps its own instructions.
        $prompt = $facts
            ? "FACTS (real figures of the trading day; the only figures you may use):\n" . self::niftyFacts($facts) . "\n\n"
                . "Write today's Nifty Derivative Market Trend Analysis as per SEBI guidelines, about 250 words in all. Reply in exactly this format, each label on its own line:\n"
                . "HEADLINE: <something engaging and SEO friendly to follow \"Nifty Derivative Market Trend Analysis :\", 4 to 9 words, Title Case, no figures>\n"
                . (self::isIntraday($facts)
                    ? "INTRO: <Intro + Market Overview, about 50 words: how the Nifty opened today and where it trades now, from FACTS>\n"
                    : "INTRO: <Intro + Market Overview, about 50 words: the day for the Nifty with its close and change from FACTS>\n")
                . "GLOBAL: <Global Market Influence, about 40 words>\n"
                . "FII: <FIIs Activity, about 40 words>\n"
                . "DII: <DIIs Activity, about 40 words>\n"
                . "SECTOR1: <one sector from FACTS (for example the best performing), about 40 words, starting with the sector name and a colon, for example IT : ...>\n"
                . "SECTOR2: <a second sector from FACTS (for example the weakest), about 40 words, starting with the sector name and a colon>\n"
                . (self::isIntraday($facts)
                    ? "OUTLOOK: <Session Outlook for the rest of today, about 40 words, using today's support and resistance levels from FACTS>"
                    : "OUTLOOK: <Next Session Outlook, about 40 words, using the support and resistance levels from FACTS>")
            : "Title typed by the editor: {$title}\n\n"
                . "Write today's Nifty Derivative Market Trend Analysis. Reply in exactly this format, each label on its own line:\n"
                . "HEADLINE: <an engaging, neutral 4 to 8 word phrase to follow \"Nifty Derivative Market Trend Analysis :\", Title Case, no figures>\n"
                . "INTRO: <45 to 55 words in 3 sentences: a short introduction and overview of the Nifty derivative market>\n"
                . "GLOBAL: <35 to 45 words in 2 or 3 sentences: how global markets may influence the Nifty>\n"
                . "FII: <35 to 45 words in 2 or 3 sentences: Foreign Institutional Investors activity in cash and derivatives>\n"
                . "DII: <35 to 45 words in 2 or 3 sentences: Domestic Institutional Investors activity>\n"
                . "SECTOR1: <about 40 words on one sector, starting with the sector name and a colon, for example Banking : ...>\n"
                . "SECTOR2: <about 40 words on a different sector, starting with the sector name and a colon>\n"
                . "OUTLOOK: <35 to 45 words in 2 or 3 sentences: a balanced outlook for the next session; every Nifty level in square brackets>";

        $labels = ['headline' => 'HEADLINE', 'intro' => 'INTRO', 'global' => 'GLOBAL', 'fii' => 'FIIS?', 'dii' => 'DIIS?',
            'sector1' => 'SECTOR ?1', 'sector2' => 'SECTOR ?2', 'outlook' => 'OUTLOOK'];
        $messages = [['role' => 'system', 'content' => $system], ['role' => 'user', 'content' => $prompt]];
        $ask = fn (array $messages) => self::labelledParts($this->ask($messages), $labels);
        $missing = fn (array $p) => array_keys(array_filter(array_diff_key($p, ['headline' => 1]), fn ($v) => $v === ''));

        try {
            $parts = $ask($messages);
            if ($missing($parts)) {
                $retry = $ask(array_merge($messages, [['role' => 'user', 'content' => 'Reply again in exactly the format asked, with every label filled: HEADLINE, INTRO, GLOBAL, FII, DII, SECTOR1, SECTOR2, OUTLOOK.']]));
                if (count($missing($retry)) < count($missing($parts))) {
                    $parts = $retry;
                }
            }
        } catch (RuntimeException $e) {
            throw $e;
        } catch (Throwable $e) {
            Log::warning('AI Nifty trend writer failed: ' . $e->getMessage());
            throw new RuntimeException('Could not reach the free AI service. Please try again in a moment.');
        }
        if ($missing($parts)) {
            throw new RuntimeException('The AI reply was incomplete. Please click "Write Full Article with AI" again.');
        }
        // Real-data mode: a figure that is not in the market data (or a [ ] blank) is never published
        if ($facts && ($wrong = self::unsupportedFigures($parts, $facts))) {
            try {
                $retry = $ask(array_merge($messages, [['role' => 'user', 'content' => 'These figures are not in FACTS or are blanks: ' . implode(', ', $wrong) . '. Reply again in exactly the same format, using only figures written exactly as in FACTS and no square brackets.']]));
            } catch (Throwable) {
                $retry = [];
            }
            $wrongAgain = $retry && !$missing($retry) ? self::unsupportedFigures($retry, $facts) : $wrong;
            if ($wrongAgain) {
                Log::warning('AI Nifty trend refused: figures not in the market data', ['figures' => $wrongAgain]);
                throw new RuntimeException('The AI used figures that are not in the market data (' . implode(', ', $wrongAgain) . '), so nothing was published.');
            }
            $parts = $retry;
        }

        $when = rescue(fn () => \Illuminate\Support\Carbon::parse($date ?: 'now', 'Asia/Kolkata'), fn () => now('Asia/Kolkata'), false);
        $headline = trim(preg_replace('/^.*?market trend analysis\s*:\s*/i', '', $parts['headline']), ' .:');
        $line = fn (string $label, string $text) => '<p><strong>' . e($label) . ' :</strong> ' . e($text) . '</p>';
        // "Banking : text" -> bold sector name
        $sector = fn (string $text) => preg_match('/^([^:.]{2,40}?)\s*:\s*(.+)$/s', $text, $m) ? $line(trim($m[1]), ucfirst(rtrim(trim($m[2]), '.')) . '.') : '<p>' . e($text) . '</p>';

        $html = implode("\n", [
            '<p>Dear Investors,</p>',
            '<p>' . e($parts['intro']) . '</p>',
            '<p><strong>Market Behavior &amp; Activity :</strong></p>',
            $line('A. Global Market Influence', $parts['global']),
            $line('B. FIIs Activity', $parts['fii']),
            $line('C. DIIs Activity', $parts['dii']),
            '<p><strong>Sector Wise Performance :</strong></p>',
            $sector($parts['sector1']),
            $sector($parts['sector2']),
            '<p><strong>Technical Analysis :</strong></p>',
            $line('A. Downside support', $facts ? str_replace(['[S Level 1]', '[S Level 2]'], [$facts['levels']['s1'], $facts['levels']['s2']], self::NIFTY_SUPPORT) : self::NIFTY_SUPPORT),
            $line('B. Upside Resistance', $facts ? str_replace(['[R Level 1]', '[R Level 2]'], [$facts['levels']['r1'], $facts['levels']['r2']], self::NIFTY_RESISTANCE) : self::NIFTY_RESISTANCE),
            // While the market trades the outlook is for the rest of today; after the close, for the next session
            $line(self::isIntraday($facts ?? []) ? 'Session Outlook' : 'Next Session Outlook', $parts['outlook']),
            '<p><strong>Report Date :</strong> ' . e($when->format('j F Y')) . ' | ' . e($when->format('l')) . '</p>',
            '<p><strong>Recommendation :</strong> ' . e(self::NIFTY_RECOMMENDATION) . '</p>',
        ]);

        $written = implode(' ', array_diff_key($parts, ['headline' => 1]));
        // Figures outside [ ] placeholders: the AI has no live data, so these may be made up
        $invented = $this->newNumbers(['title' => '', 'description' => '', 'content' => preg_replace('/\[[^\]]*\]/', '', $written)], $title, null);
        $promotional = self::promotionalWords($written);

        return [
            'title' => self::NIFTY_TITLE . ' : ' . ($headline !== '' && str_word_count($headline) >= 3 ? $headline : 'Understanding Market Momentum and Key Levels'),
            'content' => self::justified($html),
            'words' => str_word_count(strip_tags(str_replace('</p>', ' </p>', $html))),
            'warnings' => array_values(array_filter([
                $facts ? null : 'Fill in every [ ] placeholder (support, resistance and other levels) with today\'s figures before saving.',
                $invented && !$facts ? 'Check these figures - the AI has no live market data: ' . implode(', ', $invented) : null,
                $promotional ? 'Not allowed under SEBI guidelines - please reword: ' . implode(', ', $promotional) : null,
            ])),
            'provider' => $this->lastProvider ?? $provider,
        ];
    }

    /**
     * The 11:11 AM (open) and 2:02 PM (midday) updates: the market is still trading, so the figures are "so far today",
     * the levels are today's (from the previous session) and the outlook is for the rest of the session.
     */
    public static function isIntraday(array $facts): bool
    {
        return in_array($facts['session'] ?? '', ['open', 'midday'], true);
    }

    /** The real figures of the day as FACTS lines for the AI (numbers without commas, as the article writes them) */
    public static function niftyFacts(array $f): string
    {
        $pct = fn (float $v) => ($v > 0 ? '+' : '') . number_format($v, 2, '.', '') . '%';
        $num = fn (float $v) => rtrim(rtrim(number_format($v, 2, '.', ''), '0'), '.');
        $n = $f['nifty'];
        $afternoon = ($f['session'] ?? '') === 'midday';
        $lines = self::isIntraday($f)
            // Morning / afternoon update: the market is trading; today's levels come from the previous session.
            // No dates or clock times in the FACTS: the article mentions none (owner, 23 Oct 2026)
            ? [
                'Session: ' . ($afternoon ? 'the afternoon session (the market is still trading)' : 'the morning session (the market is trading)'),
                'Nifty 50 in ' . ($afternoon ? 'afternoon' : 'morning') . " trade: {$num($n['close'])}, change so far: {$num($n['change'])} points ({$pct($n['change_pct'])}) from the previous close {$num($n['prev_close'])}; opened at {$num($n['open'])} (" . ($n['open'] >= $n['prev_close'] ? 'a gap up' : 'a gap down') . " of {$num(abs(round($n['open'] - $n['prev_close'], 2)))} points); high so far {$num($n['high'])}, low so far {$num($n['low'])}",
                "Support levels for today's session (pivot points of the previous session): {$f['levels']['s1']} and {$f['levels']['s2']}; resistance levels: {$f['levels']['r1']} and {$f['levels']['r2']}",
            ]
            : [
                'Session: the market close (the day\'s final figures)',
                "Nifty 50 close: {$num($n['close'])}, change: {$num($n['change'])} points ({$pct($n['change_pct'])}); previous close {$num($n['prev_close'])}; day open {$num($n['open'])}, high {$num($n['high'])}, low {$num($n['low'])}",
                "Support levels for the next session (pivot points): {$f['levels']['s1']} and {$f['levels']['s2']}; resistance levels: {$f['levels']['r1']} and {$f['levels']['r2']}",
            ];
        if ($f['vix']) {
            $lines[] = "India VIX: {$num($f['vix']['close'])} ({$pct($f['vix']['change_pct'])})";
        }
        if ($f['sectors']) {
            $lines[] = 'NSE sector indices today, best to weakest: ' . implode('; ', array_map(fn ($s) => "{$s['name']} {$pct($s['change_pct'])}", $f['sectors']));
        }
        if ($f['global']) {
            $lines[] = 'World markets (latest session): ' . implode('; ', array_map(fn ($g) => "{$g['name']} {$pct($g['change_pct'])}", $f['global']));
        }
        $lines[] = $f['fii_dii']
            ? 'FII / DII cash market (NSE provisional, latest session): FIIs net ' . ($f['fii_dii']['fii_net'] >= 0 ? 'bought' : 'sold') . ' ₹' . abs($f['fii_dii']['fii_net']) . ' crore; DIIs net ' . ($f['fii_dii']['dii_net'] >= 0 ? 'bought' : 'sold') . ' ₹' . abs($f['fii_dii']['dii_net']) . ' crore'
            : 'FII / DII figures: not available (describe their role in words only)';
        if (!empty($f['sensex'])) {
            $lines[] = "Sensex: {$num($f['sensex']['close'])}, change: {$num($f['sensex']['change'])} points ({$pct($f['sensex']['change_pct'])})";
        }
        $bank = collect($f['sectors'] ?? [])->firstWhere('name', 'Banking');
        if ($bank) {
            $lines[] = "Bank Nifty: {$num($bank['close'])} ({$pct($bank['change_pct'])})";
        }
        if (!empty($f['macro'])) {
            // Rupees per dollar going UP means the rupee WEAKENED: said in words so it is never reported the wrong way round
            $lines[] = 'Energy and currency (latest): ' . implode('; ', array_map(fn ($m) => "{$m['name']} {$num($m['close'])} ({$pct($m['change_pct'])}"
                . (str_starts_with($m['name'], 'Rupee') && $m['change_pct'] != 0 ? ($m['change_pct'] > 0 ? ', the rupee weakened against the US dollar' : ', the rupee strengthened against the US dollar') : '') . ')', $f['macro']));
        }
        if (!empty($f['moving_averages'])) {
            // Above the Nifty = overhead resistance, below = support: said in words so they land in the right list
            $lines[] = 'Nifty 50 moving averages of daily closes: ' . implode(', ', array_map(fn ($d, $v) => "{$d}-day {$num($v)} (" . ($v > $n['close'] ? 'above the Nifty, so overhead resistance' : 'below the Nifty, so support') . ')', array_keys($f['moving_averages']), $f['moving_averages']));
        }
        if (!empty($f['breadth'])) {
            $b = $f['breadth'];
            $lines[] = "Nifty 50 market breadth (NSE): {$b['advances']} advances, {$b['declines']} declines, {$b['unchanged']} unchanged";
        }
        if (!empty($f['headlines'])) {
            $lines[] = "Business headlines of the last 24 hours (real news; the ONLY news, events, results or company names you may mention):\n- " . implode("\n- ", $f['headlines']);
        }

        return implode("\n", $lines);
    }

    /**
     * The automatic Market Trend update (9:30 AM, 3:30 PM, 5 PM; App\Services\NiftyDailyUpdater), in the owner's
     * full market-trend structure (22 Oct 2026): headline + introduction; Market Behaviour & Activity (global, FIIs,
     * DIIs); Macroeconomic, Energy & Currency Drivers; Sector-Wise Performance (gainers / losers with catalysts);
     * Market Sentiment & Technical Analysis (breadth & volatility, downside / upside levels); Outlook & Key Triggers.
     * Written ONLY from the real FACTS (App\Services\NiftyDayData + News Inbox headlines): any figure that is not in
     * them, or a [ ] blank, means nothing is published. SEBI public-appearance rules: no buy / sell calls, no targets.
     *
     * @return array{title: string, content: string, words: int, warnings: string[], provider: string}
     */
    public function marketTrendAuto(array $facts): array
    {
        $provider = $this->provider() ?? throw new RuntimeException(self::NOT_SET);
        // The 11:11 AM and 2:02 PM updates: the market is still trading
        $open = self::isIntraday($facts);
        $session = ['open' => 'morning session', 'midday' => 'afternoon session'][$facts['session'] ?? ''] ?? 'market close';

        $system = <<<'TXT'
You are an expert financial journalist and stock market analyst writing the daily Nifty market trend article for Investment Point, an Indian business newspaper and stock market website, for retail and institutional investors.
The tone is professional, objective, crisp, financial and analytical. Every report follows the SEBI (Research Analysts) Regulations and SEBI (Investment Advisers) Regulations guidelines for public appearances: fair, balanced, factual and free of any recommendation.
Rules:
- Use ONLY the figures given in FACTS, written exactly as given there. Never write any other number, level, amount, percentage or date, and never use square brackets.
- Mention news, events, corporate results, companies, RBI, US Fed or inflation only when they are in the FACTS headlines; otherwise describe them in general words (for example: investors may watch global cues and institutional flows). Never mention Gift Nifty.
- Write every sentence in your own original words. Never copy text from the headlines or any other source.
- No buy, sell, hold, accumulate, add, exit, book profit or avoid calls; no price targets, target projections, entry or exit points or stop losses; nothing actionable for a reader to trade on.
- Never use promotional or misleading words: guaranteed, assured, sure shot, risk-free, safe bet, multibagger, jackpot, huge returns, must buy, best stock, do not miss, hurry, double your money.
- Use careful words such as may, could, generally. Never predict the market with certainty.
- Never use quotation marks or inverted commas. Never write a number with a comma.
- Never mention a date, a day of the week or a clock time (no "23 October", no "Friday", no "9:30 AM"): write "today", "at the opening", "at the close" or "the next session" instead.
- Do not add a disclaimer: the website adds its own line.
- No markdown and no headings in your reply: only the labelled parts asked for.
TXT;

        $prompt = "FACTS (real figures; the only figures you may use):\n" . self::niftyFacts($facts) . "\n\n"
            . "Write the {$session} Nifty market trend article for this trading day. Reply in exactly this format, each label on its own line:\n"
            . "HEADLINE: <a catchy, SEO friendly headline of 6 to 11 words summarising the market's overall direction, Title Case, no figures>\n"
            . ($open
                ? "INTRO: <70 to 90 words: the key market sentiment so far today and the moves of the Nifty 50, Sensex and Bank Nifty so far, from FACTS>\n"
                : "INTRO: <70 to 90 words: the key market sentiment of the session and the moves of the Nifty 50, Sensex and Bank Nifty, from FACTS>\n")
            . "GLOBAL: <50 to 65 words: cues from Wall Street, European markets and Asian peers, from FACTS>\n"
            . "FII: <40 to 50 words: FII buy or sell figures from FACTS and what they may mean for market liquidity>\n"
            . "DII: <40 to 50 words: DII deployment from FACTS and their cushioning effect>\n"
            . "MACRO: <60 to 80 words: Brent crude, the US Dollar Index and the Rupee from FACTS, and what they may mean for Indian markets; RBI, Fed or inflation only in general words unless in the headlines>\n"
            . "GAINERS: <the 2 or 3 best sectors from FACTS, each as Sector : 20 to 30 words with its move and a catalyst only if one is in the headlines; separate the sectors with ' | '>\n"
            . "LOSERS: <the 2 or 3 weakest sectors from FACTS, in the same way, separated with ' | '>\n"
            . "BREADTH: <35 to 50 words: the advance decline ratio and the India VIX move from FACTS and what they say about sentiment>\n"
            . "DOWNSIDE: <2 or 3 points separated with ' | ': the immediate support levels, ONLY the moving averages marked below the Nifty (if none, say all key averages are overhead), and the crucial breakdown zone, all from FACTS>\n"
            . "UPSIDE: <2 or 3 points separated with ' | ': the immediate resistance levels, ONLY the moving averages marked above the Nifty, and the breakout zone, all from FACTS; no targets>\n"
            . "TRIGGERS: <2 or 3 points separated with ' | ': data triggers, corporate results or global events to watch " . ($open ? 'for the rest of the session' : 'in the next session') . ", only from the FACTS headlines and data>\n"
            . "OUTLOOK: <40 to 60 words: a balanced, non-advisory outlook " . ($open ? 'for the rest of today' : 'for the next session') . '>';

        $labels = ['headline' => 'HEADLINE', 'intro' => 'INTRO', 'global' => 'GLOBAL', 'fii' => 'FIIS?', 'dii' => 'DIIS?', 'macro' => 'MACRO',
            'gainers' => 'GAINERS', 'losers' => 'LOSERS', 'breadth' => 'BREADTH', 'downside' => 'DOWNSIDE', 'upside' => 'UPSIDE',
            'triggers' => 'TRIGGERS', 'outlook' => 'OUTLOOK'];
        $messages = [['role' => 'system', 'content' => $system], ['role' => 'user', 'content' => $prompt]];
        // Any date or clock time the AI still writes is taken out (before the figure check, which would refuse it)
        $ask = fn (array $messages) => array_map([self::class, 'withoutDateTime'], self::labelledParts($this->ask($messages, 2200, 0.5), $labels));
        $missing = fn (array $p) => array_keys(array_filter(array_diff_key($p, ['headline' => 1]), fn ($v) => $v === ''));

        try {
            $parts = $ask($messages);
            if ($missing($parts)) {
                $retry = $ask(array_merge($messages, [['role' => 'user', 'content' => 'Reply again in exactly the format asked, with every label filled: ' . implode(', ', $labels) . '.']]));
                if (count($missing($retry)) < count($missing($parts))) {
                    $parts = $retry;
                }
            }
        } catch (RuntimeException $e) {
            throw $e;
        } catch (Throwable $e) {
            Log::warning('AI market trend writer failed: ' . $e->getMessage());
            throw new RuntimeException('Could not reach the AI service. Please try again in a moment.');
        }
        if ($missing($parts)) {
            throw new RuntimeException('The AI reply was incomplete (' . implode(', ', $missing($parts)) . ').');
        }
        // A figure that is not in the market data (or a [ ] blank) is never published
        if ($wrong = self::unsupportedFigures($parts, $facts)) {
            try {
                $retry = $ask(array_merge($messages, [['role' => 'user', 'content' => 'These figures are not in FACTS or are blanks: ' . implode(', ', $wrong) . '. Reply again in exactly the same format, using only figures written exactly as in FACTS and no square brackets.']]));
            } catch (Throwable) {
                $retry = [];
            }
            $wrongAgain = $retry && !$missing($retry) ? self::unsupportedFigures($retry, $facts) : $wrong;
            if ($wrongAgain) {
                Log::warning('AI market trend refused: figures not in the market data', ['figures' => $wrongAgain]);
                throw new RuntimeException('The AI used figures that are not in the market data (' . implode(', ', $wrongAgain) . '), so nothing was published.');
            }
            $parts = $retry;
        }
        if ($promotional = self::promotionalWords(implode(' ', $parts))) {
            throw new RuntimeException('The AI used words not allowed under SEBI guidelines (' . implode(', ', $promotional) . '), so nothing was published.');
        }

        $heading = fn (string $text) => '<p><strong>' . e($text) . ' :</strong></p>';
        $line = fn (string $label, string $text) => '<p><strong>' . e($label) . ' :</strong> ' . e($text) . '</p>';
        // "IT : text | Metal : text" -> a bullet list with bold names
        $bullets = fn (string $text) => '<ul>' . implode('', array_map(function ($item) {
            $item = trim($item, " -•\t");

            return '<li>' . (preg_match('/^([^:.]{2,40}?)\s*:\s*(.+)$/s', $item, $m) ? '<strong>' . e(trim($m[1])) . ' :</strong> ' . e(trim($m[2])) : e($item)) . '</li>';
        }, array_filter(array_map('trim', preg_split('/\s*\|\s*|\s+[-•]\s+(?=[A-Z])/u', $text))))) . '</ul>';

        // No Report Date / Updated time line: the article mentions no date or time (owner, 23 Oct 2026)
        $html = implode("\n", [
            '<p>' . e($parts['intro']) . '</p>',
            $heading('Market Behaviour & Activity'),
            $line('A. Global Market Influence', $parts['global']),
            $line('B. FIIs Activity', $parts['fii']),
            $line('C. DIIs Activity', $parts['dii']),
            $line('Macroeconomic, Energy & Currency Drivers', $parts['macro']),
            $heading('Sector Wise Performance'),
            '<p><strong>Top Gainers</strong></p>', $bullets($parts['gainers']),
            '<p><strong>Top Losers</strong></p>', $bullets($parts['losers']),
            $heading('Market Sentiment & Technical Analysis'),
            $line('Market Breadth & Volatility', $parts['breadth']),
            '<p><strong>A. Downside Levels :</strong></p>', $bullets($parts['downside']),
            '<p><strong>B. Upside Levels :</strong></p>', $bullets($parts['upside']),
            $heading(($open ? 'Session' : 'Next Session') . ' Outlook & Key Triggers'),
            $bullets($parts['triggers']),
            '<p>' . e($parts['outlook']) . '</p>',
            '<p><strong>Recommendation :</strong> ' . e(self::NIFTY_RECOMMENDATION) . '</p>',
        ]);

        $headline = trim(preg_replace('/^.*?market trend analysis\s*:\s*/i', '', $parts['headline']), ' .:');

        return [
            'title' => self::NIFTY_TITLE . ' : ' . ($headline !== '' && str_word_count($headline) >= 3 ? $headline : 'Market Direction, Key Levels and Triggers'),
            'content' => self::justified($html),
            'words' => str_word_count(strip_tags(str_replace(['</p>', '</li>'], [' </p>', ' </li>'], $html))),
            'warnings' => [],
            'provider' => $this->lastProvider ?? $provider,
        ];
    }

    /**
     * Figures in the AI's parts that are not in the FACTS (also accepted rounded to 0 or 1 decimal), and any
     * [ ] blank. Small counts (1-4) and years are fine.
     */
    /**
     * A Market Trend text without dates, days of the week or clock times: "a cautious start on 23 October 2026
     * as the Nifty" -> "a cautious start as the Nifty", "By 9:30 AM, the index traded" -> "The index traded".
     */
    public static function withoutDateTime(string $text): string
    {
        $months = 'January|February|March|April|May|June|July|August|September|October|November|December|Jan|Feb|Mar|Apr|Jun|Jul|Aug|Sept?|Oct|Nov|Dec';
        $days = 'Monday|Tuesday|Wednesday|Thursday|Friday|Saturday|Sunday';
        $time = '(?<![\d.,])(?:\d{1,2}:\d{2}(?:\s*[ap]\.?m\.?)?|\d{1,2}(?:\.\d{2})?\s*[ap]\.?m\.?(?![a-z]))(?:\s*IST)?';
        $date = "(?:(?:{$days}),?\s+)?(?:(?<![\d.,])\d{1,2}(?:st|nd|rd|th)?\s+(?:{$months})\b\.?|(?:{$months})\s+\d{1,2}(?:st|nd|rd|th)?\b)(?:,?\s+\d{4})?|\b(?:{$days})\b";

        $clean = preg_replace([
            // "in Friday's session" -> "in the session"
            "/\b(?:{$days})(?:'|’)s\b/u",
            "/(?:\b(?:by|at|as of|as at|around|till|until|after|before|since|on|of)\s+)?(?:{$time})/iu",
            "/(?:\b(?:on|for|of|dated)\s+)?(?:{$date})/u",
        ], ['the', '', ''], $text);
        if ($clean === $text) {
            return $text;
        }

        $clean = preg_replace(['/\s{2,}/u', '/(^|[.!?])\s*[,;:]\s*/u', '/\s+([,.;:!?])/u', '/,\s*,/u', '/\(\s*\)/u'], [' ', '$1 ', '$1', ',', ''], trim($clean));

        // A sentence that began with the date / time now starts in lower case
        return preg_replace_callback('/(^|[.!?]\s+)(\p{Ll})/u', fn ($m) => $m[1] . mb_strtoupper($m[2]), trim($clean));
    }

    public static function unsupportedFigures(array $parts, array $facts): array
    {
        $key = fn (float $v) => rtrim(rtrim(number_format($v, 2, '.', ''), '0'), '.');
        preg_match_all('/\d[\d,]*(?:\.\d+)?/', self::niftyFacts($facts), $m);
        $allowed = [];
        foreach ($m[0] as $n) {
            $v = (float) str_replace(',', '', $n);
            foreach ([$v, round($v), round($v, 1)] as $x) {
                $allowed[$key($x)] = true;
            }
        }
        $text = html_entity_decode(strip_tags(implode(' ', array_diff_key($parts, ['headline' => 1]))), ENT_QUOTES);
        preg_match_all('/\d[\d,]*(?:\.\d+)?/', $text, $found);
        $wrong = [];
        foreach (array_unique($found[0]) as $n) {
            $k = $key((float) str_replace(',', '', $n));
            if (!isset($allowed[$k]) && !preg_match('/^(19|20)\d\d$/', $k) && !in_array($k, ['1', '2', '3', '4'], true)) {
                $wrong[] = $n;
            }
        }
        if (preg_match_all('/\[[^\]]*\]/', $text, $blanks)) {
            $wrong = array_merge($wrong, $blanks[0]);
        }

        return array_values(array_unique($wrong));
    }

    /**
     * "LABEL: text" parts of an AI reply, cleaned: no markdown, no quotation marks, no commas inside
     * amounts ("1,526" -> "1526") and each part on one line.
     *
     * @param  array<string, string>  $labels  key => label regex
     * @return array<string, string>
     */
    public static function labelledParts(string $text, array $labels): array
    {
        $text = str_replace(["\r\n", '**', '__', '#'], ["\n", '', '', ''], trim($text));
        $any = '(?:' . implode('|', $labels) . ')\s*:';
        $parts = [];
        foreach ($labels as $key => $label) {
            $value = preg_match('/^\s*(?:' . $label . ')\s*:\s*(.*?)(?=^\s*' . $any . '|\z)/ims', $text, $m) ? $m[1] : '';
            $value = str_replace(['"', '“', '”', '‘', '’', "'"], ['', '', '', '', "'", "'"], preg_replace('/(\d),(?=\d)/', '$1', $value));
            $parts[$key] = trim(preg_replace('/\s+/', ' ', $value), " \t\n'");
        }

        return $parts;
    }

    /**
     * The labelled parts of the AI's report reply, cleaned: no markdown, and no commas inside amounts
     * ("1,526" -> "1526", "1,52,600" -> "152600").
     *
     * @return array{company: string, nse: string, bse: string, sector: string, summary: string, overview: string, trend: string, risk: string}
     */
    public static function reportParts(string $text): array
    {
        $text = str_replace(["\r\n", '**', '__', '#'], ["\n", '', '', ''], trim($text));
        $labels = ['company' => 'COMPANY(?: NAME)?', 'nse' => 'NSE(?: SYMBOL)?', 'bse' => 'BSE(?: CODE| SCRIP CODE)?', 'sector' => 'SECTOR',
            'summary' => 'SUMMARY', 'overview' => '(?:COMPANY )?OVERVIEW', 'trend' => '(?:MARKET )?TREND', 'risk' => 'RISK(?: FACTORS?)?'];
        $any = '(?:' . implode('|', $labels) . ')\s*:';
        $parts = [];
        foreach ($labels as $key => $label) {
            $parts[$key] = preg_match('/^\s*(?:' . $label . ')\s*:\s*(.*?)(?=^\s*' . $any . '|\z)/ims', $text, $m)
                ? trim(preg_replace('/(\d),(?=\d)/', '$1', $m[1]), " \t\n\"")
                : '';
        }
        foreach (['company', 'nse', 'bse', 'sector'] as $short) {
            $parts[$short] = mb_substr(trim(strtok($parts[$short] . "\n", "\n")), 0, 120);
        }
        $parts['nse'] = strtoupper($parts['nse']);
        foreach (['summary', 'overview', 'risk'] as $one) {
            $parts[$one] = trim(preg_replace('/\s+/', ' ', $parts[$one]));
        }

        return $parts;
    }

    /**
     * Small models add a disclaimer paragraph even when told not to. Drop any paragraph that is one
     * ("What it means for investors: ... educational purposes only ... consult a SEBI registered adviser"),
     * since the analysis page already shows the standard disclaimer box.
     */
    public static function withoutDisclaimer(string $text): string
    {
        $paragraphs = preg_split('/(\n\s*\n|\n)/', str_replace("\r\n", "\n", $text), -1, PREG_SPLIT_DELIM_CAPTURE);
        $out = '';
        for ($i = 0; $i < count($paragraphs); $i += 2) {
            $p = $paragraphs[$i];
            $isDisclaimer = preg_match('/^\s*what (?:it|this) means for investors\s*:/i', $p)
                || preg_match_all('/educational purposes? only|not (?:a|any) (?:buy|recommendation)|subject to market risks?|do (?:their|your) own research|sebi[- ]registered (?:investment )?advis[eo]r|risk profile|consult (?:a|your) (?:financial|sebi)/i', $p) >= 2;
            if (!$isDisclaimer) {
                $out .= $p . ($paragraphs[$i + 1] ?? '');
            }
        }

        return rtrim($out);
    }

    /** "SBI Cards: Support, Resistance & Key Levels" -> "SBI Cards" (a hyphen inside a name like "Bajaj-Auto" is kept). */
    public static function stockName(string $title): string
    {
        return trim(preg_split('/\s*[:|]\s*|\s+[–—-]\s+/u', $title)[0]) ?: $title;
    }

    /** Recommendation / promotional words that SEBI guidelines do not allow in a public appearance. */
    public static function promotionalWords(string $text): array
    {
        // Phrases, not single words, so risk wording such as "past performance does not guarantee" is not flagged
        preg_match_all('/\b(guaranteed (?:returns?|profits?|gains?)|assured (?:returns?|profits?|gains?)|sure[\s-]?shot|safe bet|multi[\s-]?bagger|jackpot|huge (?:returns?|profits?)|must buy|best stock|do not miss|don\'t miss|hurry|double your money|strong buy|buy now|should (?:buy|sell)|good time to (?:buy|invest)|book profits?|target price|price target of|stop[\s-]?loss (?:at|of))\b/i',
            html_entity_decode(strip_tags($text), ENT_QUOTES), $m);

        return array_values(array_unique(array_map('mb_strtolower', $m[0])));
    }

    /**
     * A realistic corporate news photo for a headline, saved on the public disk.
     * Made by the free AI picture services (App\Services\AiPictures), never a picture with a logo mark.
     * Returns the stored path, or null when neither works.
     */
    public function image(string $headline): ?string
    {
        $prompt = self::imagePrompt($headline);
        try {
            [$data, $ext] = app(AiPictures::class)->make($prompt, 1024, 576, false);
        } catch (RuntimeException $e) {
            Log::warning('AI news image failed: ' . $e->getMessage());

            return null;
        }

        // "photo-": realistic style (older cartoon-style images are "ai-" and get replaced automatically)
        // Web-sized, filling a 16:9 box (the middle is kept, no blurred bands)
        return \App\Support\ImageOptimizer::putFilled($data, $ext, 'headline-news/photo-' . now()->format('Ymd-His') . '-' . \Illuminate\Support\Str::random(6));
    }

    /**
     * Photo prompt: realistic corporate / financial photography. Only a general topic, never names
     * (company, people or brand names make the AI draw real logos or faces), and no people at all
     * (AI pictures of real persons could be mistaken for real photos).
     */
    public static function imagePrompt(string $headline): string
    {
        return self::photoStyle(self::imageTopic($headline));
    }

    /**
     * One concrete scene written like a news photographer's shot description. Image models follow a
     * single clear scene far better than a list of ideas (which comes out as a cartoon-like collage).
     */
    public static function photoStyle(string $scene): string
    {
        // Described only in positive words: this image model tends to ADD what a prompt names,
        // so "no text / no letters" invites text. Plain, unbranded surfaces keep pictures clean.
        return 'Award-winning editorial news photograph: ' . rtrim($scene, '. ') . '. '
            . 'A real photograph, photorealistic, shot on a full-frame Canon EOS R5 with a 35mm lens at f/2.8, natural available light, '
            . 'shallow depth of field with soft bokeh, crisp focus, fine realistic textures, true-to-life muted colours, subtle film grain, '
            . 'premium financial newspaper look like a Reuters or Bloomberg photo. '
            . 'Clean, uncluttered composition with plain, unbranded surfaces and nobody in the frame.';
    }

    /**
     * Numbers in the draft that are not in the original headline / summary (years like 2026 allowed).
     * Anything returned means the AI may have invented a figure.
     */
    public function newNumbers(array $draft, string $headline, ?string $summary): array
    {
        $numbers = function (string $text) {
            preg_match_all('/\d[\d,]*(?:\.\d+)?/', html_entity_decode(strip_tags($text), ENT_QUOTES), $m);

            // "1,062.80" -> "1062.8"; trailing zeros only trimmed after a decimal point
            return array_unique(array_map(function ($n) {
                $n = str_replace(',', '', $n);

                return str_contains($n, '.') ? rtrim(rtrim($n, '0'), '.') : $n;
            }, $m[0]));
        };
        $source = $numbers($headline . ' ' . $summary);
        $draftNumbers = $numbers($draft['title'] . ' ' . $draft['description'] . ' ' . ($draft['keywords'] ?? '') . ' ' . $draft['content']);

        return array_values(array_filter(array_diff($draftNumbers, $source),
            fn ($n) => !preg_match('/^(19|20)\d\d$/', $n) && !in_array($n, ['1', '2', '3', '4'], true))); // years, "4 banks", etc.
    }

    /**
     * Second, independent check: another AI pass compares the article with the original facts and
     * lists any specific claim that is not supported. Returns the problems ([] = only true facts).
     * If the check itself can't run, the article is treated as NOT checked (returned as a problem).
     */
    public function factCheck(array $draft, string $headline, ?string $summary): array
    {
        $provider = $this->provider();
        $article = html_entity_decode(strip_tags(str_replace('</p>', "\n", $draft['content'])), ENT_QUOTES);
        $messages = [
            ['role' => 'system', 'content' => 'You are a careful fact-checker for a financial news website. You compare an ARTICLE with the FACTS it was written from and report only invented news facts.'],
            ['role' => 'user', 'content' => "FACTS (the only source):\n{$headline}\n" . ($summary ?: '') . "\n\n"
                . "ARTICLE:\nTitle: {$draft['title']}\nSummary: {$draft['description']}\n{$article}\n\n"
                . "List every SPECIFIC claim about THIS news that the FACTS do not support: a number, percentage, amount, date, "
                . "person, company, event, quote, a stated cause or result of this news, a comparison or superlative "
                . "(\"one of the largest\", \"record\", \"first\"), or a headline that attaches a fact to the wrong thing.\n"
                . "Do NOT list any of these (they are allowed):\n"
                . "- general explanations or definitions (what an IPO, block deal, listing, Sensex, RBI or a merger is);\n"
                . "- general statements about how markets, sectors or investors usually behave;\n"
                . "- neutral remarks such as investors watching the stock, or the \"What it means for investors\" paragraph when it adds no new fact;\n"
                . "- the same fact in other words, or a rounded figure;\n"
                . "- \"support\" written for a stop loss, or \"resistance level\" for a target or target price, with the same figure;\n"
                . "- things the article leaves out.\n"
                . "Reply with the single word OK if there is nothing to list. Otherwise reply UNSUPPORTED: followed by the claims, separated by semicolons."],
        ];

        try {
            // The large model: the small one kept flagging general explanations and true facts
            $reply = trim($this->ask($messages));
        } catch (Throwable $e) {
            return ['fact-check could not run: ' . $e->getMessage()];
        }

        if (preg_match('/^\W*OK\b/i', $reply) && !preg_match('/UNSUPPORTED/i', $reply)) {
            return [];
        }

        $problems = array_values(array_filter(array_map('trim', explode(';', preg_replace('/^.*?UNSUPPORTED:\s*/is', '', $reply)))));
        if (!$problems) {
            return preg_match('/UNSUPPORTED/i', $reply) ? [] : [$reply]; // "UNSUPPORTED:" with nothing after it
        }

        // The checker sometimes lists things that are not invented facts: what the article leaves out,
        // its own "this is general" notes, or text that is word-for-word in the source. Drop those.
        $source = mb_strtolower($headline . ' ' . $summary);
        $problems = array_values(array_filter($problems, function (string $p) use ($source) {
            $plain = mb_strtolower(trim($p, " .\"'"));

            return $plain !== ''
                && !preg_match('/\b(does not|doesn\'t|did not|do not) (mention|state|specify|say|explicitly)|\bnot (explicitly )?mentioned\b|\bis a general\b|\bgeneral (statement|explanation|background)\b|\ballowed\b/i', $p)
                && !str_contains($source, $plain);
        }));

        return $problems;
    }

    /** True when the draft names a news outlet (the article must read as Investment Point's own report). */
    public function mentionsPublisher(array $draft): bool
    {
        $text = $draft['title'] . ' ' . $draft['description'] . ' ' . $draft['content'];

        return (bool) preg_match('/\b(Economic Times|ET Markets|ETMarkets|LiveMint|Mint|Business Standard|BusinessLine|Business Line|Hindu|CNBC|Moneycontrol|Reuters|Bloomberg|PTI)\b/', $text);
    }

    /** The writing services that are set up, the main one first: Gemini, then Groq, then Hugging Face */
    private function providers(): array
    {
        return array_keys(array_filter([
            'gemini' => (bool) \App\Models\Setting::geminiKey(),
            'groq' => (bool) \App\Models\Setting::groqKey(),
            'huggingface' => (bool) config('services.huggingface_ai.token'),
        ]));
    }

    private function provider(): ?string
    {
        return $this->providers()[0] ?? null;
    }

    /**
     * One AI reply: the main service, and when it fails (limit, no balance, busy) the next one.
     * All fail: the main service's message is shown.
     *
     * @throws RuntimeException with a message for the admin
     */
    private function ask(array $messages, int $maxTokens = 900, float $temperature = 0.6): string
    {
        $error = null;
        foreach ($this->providers() as $provider) {
            try {
                $text = match ($provider) {
                    'gemini' => $this->gemini($messages, $maxTokens, $temperature),
                    'groq' => $this->groq($messages, $maxTokens, $temperature),
                    default => $this->huggingFace($messages, $maxTokens, $temperature),
                };
                $this->lastProvider = $provider;

                return $text;
            } catch (Throwable $e) {
                $error ??= $e instanceof RuntimeException ? $e : new RuntimeException('Could not reach the AI service. Please try again in a moment.');
            }
        }

        throw $error ?? new RuntimeException(self::NOT_SET);
    }

    private const GEMINI_API = 'https://generativelanguage.googleapis.com/v1beta/models/';

    /**
     * One request to the Google Gemini API (generateContent). OpenAI-style messages are turned into
     * Gemini's format: the system message becomes the system instruction, "assistant" becomes "model".
     *
     * @throws RuntimeException with a message for the admin ("MODEL: ..." when only the model is wrong)
     */
    public function gemini(array $messages, int $maxTokens = 900, float $temperature = 0.6, ?string $key = null, ?string $model = null): string
    {
        $system = implode("\n\n", array_column(array_filter($messages, fn ($m) => $m['role'] === 'system'), 'content'));
        $contents = array_values(array_map(fn ($m) => ['role' => $m['role'] === 'assistant' ? 'model' : 'user', 'parts' => [['text' => $m['content']]]],
            array_filter($messages, fn ($m) => $m['role'] !== 'system')));

        try {
            $response = Http::withHeaders(['x-goog-api-key' => $key ?: \App\Models\Setting::geminiKey()])->connectTimeout(8)->timeout(90)
                ->post(self::GEMINI_API . ($model ?: \App\Models\Setting::geminiModel()) . ':generateContent', array_filter([
                    'systemInstruction' => $system !== '' ? ['parts' => [['text' => $system]]] : null,
                    'contents' => $contents,
                    // Room for the model's own thinking on top of the reply (only the tokens used are paid)
                    'generationConfig' => ['maxOutputTokens' => max($maxTokens * 3, 4096), 'temperature' => $temperature],
                ]));
        } catch (Throwable $e) {
            throw new RuntimeException('Could not reach Google Gemini. Please try again in a moment.');
        }

        $parts = $response->json('candidates.0.content.parts') ?? [];
        $text = trim(implode('', array_map(fn ($p) => empty($p['thought']) ? ($p['text'] ?? '') : '', $parts)));
        if ($response->successful() && $text !== '') {
            return $text;
        }
        $error = $response->json('error.message') ?? ($response->successful()
            ? 'empty reply (' . ($response->json('candidates.0.finishReason') ?? $response->json('promptFeedback.blockReason') ?? 'no text') . ')'
            : 'HTTP ' . $response->status());
        Log::warning('Gemini AI writer failed: ' . $error);

        throw new RuntimeException(self::geminiError($response->status(), (string) $error));
    }

    /** A Gemini API error, in words for the admin */
    private static function geminiError(int $status, string $error): string
    {
        return match (true) {
            in_array($status, [401, 403], true) || preg_match('/api key|API_KEY/i', $error) => 'Gemini did not accept the API key. Copy it again from aistudio.google.com (it starts with AQ. or AIza), and check that billing is set up for paid use.',
            preg_match('/free[_ ]tier|limit: 0\b|billing/i', $error) && in_array($status, [400, 403, 429], true) => 'Gemini needs billing for this (the free tier does not include it): open aistudio.google.com -> API keys -> Set up billing.',
            $status === 429 => 'Gemini limit reached (requests per minute, the daily free limit or the billing account cap). Please try again shortly, or check billing in aistudio.google.com.',
            $status === 404 || ($status === 400 && preg_match('/model/i', $error)) => 'MODEL: ' . $error,
            str_contains(strtolower($error), 'billing') => 'Gemini needs billing for this: open aistudio.google.com -> API keys -> Set up billing.',
            default => 'Gemini could not do this (' . $error . ').',
        };
    }

    /**
     * One request to Groq's free API (the same request format as OpenAI's).
     *
     * @throws RuntimeException with a message for the admin
     */
    public function groq(array $messages, int $maxTokens = 900, float $temperature = 0.6, ?string $key = null, ?string $model = null): string
    {
        try {
            $response = Http::withToken($key ?: \App\Models\Setting::groqKey())->connectTimeout(8)->timeout(60)
                ->post('https://api.groq.com/openai/v1/chat/completions', [
                    'model' => $model ?: \App\Models\Setting::groqModel(),
                    'messages' => $messages,
                    'max_completion_tokens' => $maxTokens,
                    'temperature' => $temperature,
                ]);
        } catch (Throwable $e) {
            throw new RuntimeException('Could not reach Groq. Please try again in a moment.');
        }

        if ($response->successful() && ($text = $response->json('choices.0.message.content'))) {
            return $text;
        }
        $error = $response->json('error.message') ?? ('HTTP ' . $response->status());
        Log::warning('Groq AI writer failed: ' . (is_string($error) ? $error : json_encode($error)));

        throw new RuntimeException(match (true) {
            $response->status() === 401 => 'Groq did not accept the API key. Copy it again from console.groq.com (it starts with gsk_).',
            $response->status() === 429 => "Today's free Groq allowance is used up, or too many requests in one minute. Please try again shortly.",
            in_array($response->status(), [400, 404], true) && preg_match('/model/i', (string) $error) => 'MODEL: ' . $error,
            default => 'Groq could not write this (' . (is_string($error) ? $error : 'HTTP ' . $response->status()) . ').',
        });
    }

    private function huggingFace(array $messages, int $maxTokens = 900, float $temperature = 0.6): string
    {
        $response = Http::withToken(config('services.huggingface_ai.token'))->timeout(90)
            ->post('https://router.huggingface.co/v1/chat/completions', [
                'model' => config('news.writer.huggingface_model'),
                'messages' => $messages,
                'max_tokens' => $maxTokens,
                'temperature' => $temperature,
            ]);

        if ($response->successful() && ($text = $response->json('choices.0.message.content'))) {
            return $text;
        }
        $error = $response->json('error.message') ?? $response->json('error') ?? ('HTTP ' . $response->status());
        Log::warning('Hugging Face AI writer failed: ' . (is_string($error) ? $error : json_encode($error)));

        throw new RuntimeException(match (true) {
            $response->status() === 403 => 'Your HF_TOKEN cannot write text: create a free Hugging Face token with the "Make calls to Inference Providers" permission - or paste a Gemini key in Admin -> Site Settings.',
            in_array($response->status(), [402, 429], true) => "The free Hugging Face allowance is used up. Paste a Gemini key in Admin -> Site Settings, or try again later.",
            default => 'The AI could not write this article (' . (is_string($error) ? $error : 'HTTP ' . $response->status()) . ').',
        });
    }

    /**
     * A name-free photo scene from a headline: one concrete, real-looking place or object for the
     * sector, plus the market mood. E.g. "HDFC Bank shares decline" -> a bank lobby in cool, overcast light.
     */
    public static function imageTopic(string $headline): string
    {
        $h = mb_strtolower($headline);
        // First match wins: the more specific topics come first
        $scenes = [
            'ipo|listing|drhp|sebi' => 'a modern stock exchange trading hall with rows of large screens showing candlestick charts',
            'tax|gst|audit|itr|budget' => 'a calculator, reading glasses and a fountain pen on a tidy wooden office desk, window light',
            'software|systems|infotech|\bit\b|\bai\b|tech\b|technology' => 'a long data centre aisle with server racks and soft blue LED lights',
            'gold|silver|jewel' => 'a neat stack of smooth, polished, blank gold ingots with mirror-like surfaces on dark velvet under soft studio light',
            'rupee|dollar|forex|currency' => 'stacked Indian rupee coins on a wooden desk',
            'bank|lender|rbi|repo|loan|credit|nbfc|upi|mdr' => 'the marble and glass lobby of a modern bank headquarters',
            'crude|oil|petroleum|refin|gas|fuel' => 'an oil refinery with distillation towers and pipelines at dusk',
            'metal|steel|alumin|zinc|copper|mining|coal' => 'glowing molten steel being poured inside a steel plant, sparks in the air',
            'pharma|drug|health|hospital' => 'a clean pharmaceutical production line with glass vials and tablets',
            'auto|car|vehicle|ev\b|two-wheeler' => 'new cars on a modern factory assembly line with robotic arms',
            'realty|real estate|housing|property|cement' => 'a modern high-rise building under construction with tower cranes against the sky',
            'telecom|5g|tariff' => 'a tall telecom tower with antennas against the sky',
            'power|energy|solar|renewable' => 'rows of solar panels with wind turbines in the distance',
            'fund|sip|investor|block deal|stake' => 'a sleek laptop on a clean walnut executive desk beside a cup of coffee and a fountain pen, soft window light',
            'futures|wall street|global|iran|china|us ' => 'a dimly lit trading room with several monitors showing world market indices and a city skyline through the window',
        ];
        $scene = 'a trading desk with multiple monitors showing stock market candlestick charts in a modern office';
        foreach ($scenes as $pattern => $picture) {
            if (preg_match('/' . $pattern . '/u', $h)) {
                $scene = $picture;
                break;
            }
        }
        $falling = (bool) preg_match('/fall|decline|drop|slump|slip|plunge|crash|loss|lower|weak|sell-?off|down/u', $h);
        $rising = !$falling && preg_match('/rise|rally|gain|jump|surge|soar|high|climb|up\b|strong|record/u', $h);
        $mood = $falling ? 'cool overcast light, a serious mood' : ($rising ? 'warm golden-hour light, an optimistic mood' : 'calm, balanced daylight');

        // Charts only where the scene has screens, and blurred (sharp screens fill up with made-up numbers)
        if (preg_match('/screen|monitor/', $scene)) {
            $mood .= ', the screens out of focus with soft abstract chart lines' . ($falling ? ' trending down in red' : ($rising ? ' trending up in green' : ''));
        }

        return $scene . ', ' . $mood;
    }

    /** "a, b, c" keyword list: up to 8 short keywords (max 5 words / 40 characters each). */
    public static function cleanKeywords(?string $keywords): string
    {
        return collect(explode(',', (string) $keywords))
            ->map(fn ($k) => trim(preg_replace('/\s+/', ' ', $k), " .\"'\n"))
            ->filter(fn ($k) => $k !== '' && mb_strlen($k) <= 40 && str_word_count($k) <= 5
                && !preg_match('/^(ARTICLE|TITLE|SUMMARY)\b/i', $k)
                && !preg_match('/^(with|or|and|the|of|to|for|in|on|as|amid)\b/i', $k)) // sentence fragments, not keywords
            ->unique(fn ($k) => mb_strtolower($k))->take(8)->implode(', ');
    }

    /** Words in the ARTICLE part of a reply (label lines not counted). */
    private function articleWords(string $text): int
    {
        $article = preg_match('/ARTICLE:\s*(.+)$/is', $text, $m) ? $m[1] : $text;
        $lines = array_filter(preg_split('/\n/', $article), fn ($l) => !preg_match('/^\s*(TITLE|SUMMARY|KEYWORDS|ARTICLE)\s*:/i', $l));

        return str_word_count(implode(' ', $lines));
    }

    /** Split the "TITLE / SUMMARY / KEYWORDS / ARTICLE" reply; the article becomes <p> paragraphs. */
    public function parse(string $text): array
    {
        $text = str_replace(["\r\n", '**'], ["\n", ''], trim($text));
        $grab = fn ($label, $until) => preg_match('/' . $label . ':\s*(.+?)\s*(?=' . $until . '|$)/is', $text, $m) ? trim($m[1], " \"\n") : '';

        $title = $grab('TITLE', 'SUMMARY:|KEYWORDS:|ARTICLE:');
        $summary = $grab('SUMMARY', 'KEYWORDS:|ARTICLE:');
        // Keywords: that one line only; real keywords are short (sentences that slipped in are dropped)
        $keywords = self::cleanKeywords(preg_match('/KEYWORDS:\s*([^\n]+)/i', $text, $k) ? $k[1] : '');
        $article = preg_match('/ARTICLE:\s*(.+)$/is', $text, $m) ? trim($m[1])
            : trim(preg_replace('/^.*?KEYWORDS:[^\n]*\n?/is', '', $text)); // no ARTICLE label: everything after the keywords line

        // Small models sometimes repeat the TITLE / SUMMARY lines inside the article: drop them
        $paragraphs = array_filter(array_map('trim', preg_split('/\n\s*\n|\n/', $article)),
            fn ($p) => $p !== '' && !preg_match('/^(TITLE|SUMMARY|KEYWORDS|ARTICLE)\s*:/i', $p));
        $article = implode("\n", $paragraphs);
        if (!$paragraphs || mb_strlen(strip_tags($article)) < 200) {
            throw new RuntimeException('The AI reply was too short. Please click "Write with AI" again.');
        }

        return [
            'title' => mb_substr($title, 0, 255),
            'description' => mb_substr($summary, 0, 500),
            'keywords' => mb_substr($keywords, 0, 500),
            'content' => self::justified(implode("\n", array_map(fn ($p) => '<p>' . e($p) . '</p>', $paragraphs))),
        ];
    }

    /**
     * Every AI-written article's paragraphs justified, like the editor's "Justify" button writes them (owner, 27 Oct
     * 2026). Only plain <p> tags: a paragraph that already has a style keeps it.
     */
    public static function justified(string $html): string
    {
        return str_replace('<p>', '<p style="text-align: justify;">', $html);
    }
}
