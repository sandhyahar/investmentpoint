<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class DivyaBhaskarArticle extends Model
{
    protected $fillable = ['blogger_id', 'headline', 'link', 'published_on', 'status'];

    protected $casts = [
        'published_on' => 'date',
    ];

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Headlines start with the column's series name ("ઈન્વેસ્ટમેન્ટ પોઈન્ટ - …", "માર્કેટ વ્યૂ : …").
     * Returns [series or null, rest of the headline] for a label + title layout.
     */
    public function headlineParts(): array
    {
        if (preg_match('/^(ઈન્વેસ્ટમેન્ટ પોઈન્ટ|ઇન્વેસ્ટમેન્ટ પોઈન્ટ|માર્કેટ વ્યૂ|Investment Point|Market View)\s*[-–:]\s*(.+)$/isu', $this->headline, $m)) {
            return [trim($m[1]), trim($m[2])];
        }

        return [null, $this->headline];
    }

    /**
     * Read one blogspot post: headline, Divya Bhaskar link and column date.
     * Newer posts: title "ઈન્વેસ્ટમેન્ટ પોઈન્ટ - <headline>". Older posts: title "DIVYA BHASKAR - 25.04.2022"
     * with the headline in the body after "Here is the link :". Returns null when there is no link.
     *
     * @return array{headline: string, link: string, published_on: ?string}|null
     */
    public static function parsePost(string $title, string $html, ?string $postedAt): ?array
    {
        preg_match_all('/href="(https?:\/\/[^"]+)"/i', $html, $hrefs);
        $link = collect($hrefs[1])->first(fn ($u) => preg_match('#^https?://([a-z0-9-]+\.)*(bhaskar\.com|divya-b\.in|dbpost\.in|bhaskarassets\.com)/#i', $u))
            ?? collect($hrefs[1])->first(fn ($u) => !preg_match('#nikhilbhatt|wa\.me|whatsapp|blogger|blogspot#i', $u));
        if (!$link) {
            return null;
        }

        $text = html_entity_decode(strip_tags(preg_replace('/<(br|\/p|\/div)[^>]*>/i', "\n", $html)), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/[ \t\x{00A0}]+/u', ' ', $text);

        // Column date: first dd.mm.yyyy in the body, then in the title, then the blog post date
        $date = null;
        foreach ([$text, $title] as $source) {
            if (preg_match('/(\d{1,2})[.\-\/](\d{1,2})[.\-\/](\d{4})/', $source, $m) && checkdate((int) $m[2], (int) $m[1], (int) $m[3])) {
                $date = Carbon::createFromDate((int) $m[3], (int) $m[2], (int) $m[1])->toDateString();
                break;
            }
        }
        $date ??= $postedAt ? Carbon::parse($postedAt)->toDateString() : null;

        // Headline: from the title unless it's just "DIVYA BHASKAR - date"; otherwise the text after "Here is the link :"
        $clean = fn ($s) => trim(preg_replace('/\s+/u', ' ', $s), " :-–\u{00A0}");
        $headline = $clean($title);
        if ($headline === '' || preg_match('/^divya\s*bhaskar\b/i', $headline)) {
            $body = preg_match('/Here\s+is\s+the\s+link\s*:(.*?)(https?:\/\/|$)/isu', $text, $m) ? $clean($m[1]) : '';
            $headline = $body !== '' ? $body : ($headline ?: 'Divya Bhaskar column');
        }

        return ['headline' => mb_substr($headline, 0, 500), 'link' => mb_substr($link, 0, 500), 'published_on' => $date];
    }

    /** Some columns were posted on the blogspot more than once: keep one per link, and one per headline + date. */
    public static function removeDuplicates(): int
    {
        $removed = 0;
        foreach ([['link'], ['headline', 'published_on']] as $columns) {
            $groups = static::query()->select($columns)->selectRaw('MIN(id) keep_id, COUNT(*) c')
                ->groupBy($columns)->having('c', '>', 1)->get();
            foreach ($groups as $g) {
                $match = array_intersect_key($g->getAttributes(), array_flip($columns));
                $removed += static::query()->where($match)->where('id', '!=', $g->keep_id)->delete();
            }
        }

        return $removed;
    }
}
