<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MediaArticle extends Model
{
    protected $fillable = ['blogger_id', 'title', 'column_name', 'publication', 'published_on', 'image', 'pages', 'description', 'status', 'publish_at'];

    protected $casts = [
        'published_on' => 'date',
        // Uploaded as a PDF: one image path per page, in order (image = the first page, the cover)
        'pages' => 'array',
        // Scheduled by the admin: shown to readers (and posted to Blogger) only from this moment (stored in UTC)
        'publish_at' => 'datetime',
    ];

    /** Shown to readers: active, and its scheduled time (if any) has come */
    public function scopeActive($query)
    {
        return $query->where('status', 'active')
            ->where(fn ($q) => $q->whereNull('publish_at')->orWhere('publish_at', '<=', now()));
    }

    /** Active but waiting for its scheduled date and time */
    public function isScheduled(): bool
    {
        return $this->status === 'active' && $this->publish_at?->isFuture();
    }

    public function getUrlAttribute(): string
    {
        return route('media-articles.show', ['mediaArticle' => $this->id, 'slug' => Str::slug($this->title) ?: null]);
    }

    /** Uploaded images live on the public disk; imported ones are full Blogger URLs. */
    public function isUploadedImage(): bool
    {
        return $this->image && !Str::startsWith($this->image, ['http://', 'https://']);
    }

    public function imageUrl(string $size = 'w1200'): ?string
    {
        if (!$this->image) {
            return null;
        }
        if ($this->isUploadedImage()) {
            return asset('storage/' . $this->image);
        }

        // Blogger image URLs carry their size as a path segment (…/w452-h640/…, …/s1600/…); s0 = original
        return preg_replace('#/(?:[swh]\d+(?:-[swhc]\d*)*)(/[^/]+)$#', '/' . $size . '$1', $this->image, 1);
    }

    /**
     * The pages shown on the article page, in order: [view => w1200 picture, full => full size].
     * A PDF upload has all its pages; older articles have their one clipping picture.
     */
    public function pageImages(): array
    {
        if ($this->pages) {
            return array_map(fn ($p) => ['view' => asset('storage/' . $p), 'full' => asset('storage/' . $p)], $this->pages);
        }

        return $this->image ? [['view' => $this->imageUrl('w1200'), 'full' => $this->imageUrl('s0')]] : [];
    }

    /** Files of this article on the public disk (the cover and every page) */
    public function uploadedFiles(): array
    {
        return array_values(array_unique(array_filter(array_merge(
            $this->isUploadedImage() ? [$this->image] : [],
            array_filter($this->pages ?? [], fn ($p) => !Str::startsWith($p, ['http://', 'https://'])),
        ))));
    }

    protected static function booted()
    {
        static::deleting(function (MediaArticle $article) {
            Storage::disk('public')->delete($article->uploadedFiles());
        });
    }

    /**
     * The blogspot has some clippings posted 2-3 times. Treat rows with the same column, newspaper and
     * date (or, without a newspaper, the same title and date) as one clipping and keep a single copy:
     * preferably the one whose image file name carries that date (e.g. "PAGDANDI - 18.07.2025.jpg"),
     * otherwise the first imported. Returns how many rows were removed.
     */
    public static function removeDuplicates(): int
    {
        $removed = 0;
        $groups = static::query()
            ->selectRaw("COALESCE(column_name, '') col, COALESCE(publication, '') pub, published_on, CASE WHEN publication IS NULL THEN title ELSE '' END t, COUNT(*) c")
            ->groupBy('col', 'pub', 'published_on', 't')
            ->having('c', '>', 1)
            ->get();

        foreach ($groups as $g) {
            $rows = static::query()
                ->whereRaw("COALESCE(column_name, '') = ?", [$g->col])
                ->whereRaw("COALESCE(publication, '') = ?", [$g->pub])
                ->where('published_on', $g->published_on)
                ->when($g->pub === '', fn ($q) => $q->where('title', $g->t))
                ->orderBy('id')->get();

            $date = $rows->first()->published_on?->format('d.m.Y');
            $keep = $rows->first(fn ($r) => $date && str_contains(urldecode(str_replace('+', ' ', (string) $r->image)), $date)) ?? $rows->first();
            $rows->where('id', '!=', $keep->id)->each(function ($r) use (&$removed) {
                $r->delete();
                $removed++;
            });
        }

        return $removed;
    }

    /** Spelling variants of newspaper names seen in the imported posts => the right spelling */
    private const PUBLICATION_SPELLINGS = ['SAURASTRA' => 'SAURASHTRA', 'ALRAM' => 'ALARM'];

    /** Whole names that are another spelling of a paper => that paper */
    private const PUBLICATION_ALIASES = ['AAJ KAL' => 'AAJ KAAL'];

    /** Cache keys of the newspaper lists (cleared when an article is added, changed or deleted) */
    public const CACHE_KEYS = ['media-articles.publications', 'media-articles.trusted-by', 'media-articles.publication-names'];

    /** One name per newspaper: "PAGDANDI /", " pagdandi" and "PAGDANDI" are all "PAGDANDI" */
    public static function normalizePublication(?string $name): string
    {
        $name = mb_strtoupper(trim(preg_replace('/\s+/u', ' ', (string) $name), " /\t\"'“”"));

        $name = strtr($name, self::PUBLICATION_SPELLINGS);

        return self::PUBLICATION_ALIASES[$name] ?? $name;
    }

    public static function forgetPublicationCaches(): void
    {
        foreach (self::CACHE_KEYS as $key) {
            \Illuminate\Support\Facades\Cache::forget($key);
        }
    }

    /** Every newspaper name on the site, one spelling each, longest first (so "GUJARAT MAIL" wins over "GUJARAT") */
    public static function knownPublications(): array
    {
        $all = \Illuminate\Support\Facades\Cache::remember('media-articles.publication-names', now()->addHour(),
            fn () => static::whereNotNull('publication')->distinct()->pluck('publication')->all());
        $names = array_values(array_unique(array_filter(array_map([self::class, 'normalizePublication'], $all))));
        usort($names, fn ($a, $b) => mb_strlen($b) <=> mb_strlen($a) ?: strcmp($a, $b));

        return $names;
    }

    /**
     * The article already on the site for this clipping: the same date and the same newspaper (any spelling),
     * or, without a newspaper, the same date and title. Null when there is none.
     */
    public static function findDuplicate(string $title, ?string $publication, string $date): ?self
    {
        $date = \Illuminate\Support\Carbon::parse($date)->toDateString();
        $publication = self::normalizePublication($publication);
        if ($publication !== '') {
            return static::whereIn('publication', self::publicationVariants($publication))->whereDate('published_on', $date)->orderBy('id')->first();
        }

        return static::whereDate('published_on', $date)->whereRaw('LOWER(TRIM(title)) = ?', [mb_strtolower(trim($title))])->orderBy('id')->first();
    }

    /** Every stored spelling of a newspaper's name (for filtering by newspaper) */
    public static function publicationVariants(string $name): array
    {
        $all = \Illuminate\Support\Facades\Cache::remember('media-articles.publication-names', now()->addHour(),
            fn () => static::whereNotNull('publication')->distinct()->pluck('publication')->all());
        $wanted = self::normalizePublication($name);
        $variants = array_values(array_filter($all, fn ($p) => self::normalizePublication($p) === $wanted));

        return $variants ?: [$name];
    }

    /**
     * "Trusted by" on the home page: the newspapers that publish Investment Point columns (at least $minimum active
     * articles) plus any paper the admin added by hand, as set in Admin -> Media Articles -> Newspaper Logos
     * (logo, display name, position, hidden). Positions first, then most articles. A click opens all of that
     * paper's articles. Cached for an hour and cleared whenever an article or a newspaper setting is saved.
     *
     * @return array<int, array{name: string, label: string, total: int, logo: ?string, official: bool, masthead: ?string, url: string, hidden: bool, sort_order: ?int, manual: bool}>
     */
    public static function trustedBy(int $minimum = 10, int $limit = 40): array
    {
        // Only the home page list is cached
        if ($minimum !== 10 || $limit !== 40) {
            return self::buildTrustedBy($minimum, $limit);
        }

        return \Illuminate\Support\Facades\Cache::remember('media-articles.trusted-by', now()->addHour(), fn () => self::buildTrustedBy(10, 40));
    }

    /** Every newspaper for the admin Newspaper Logos page, hidden ones and small papers included */
    public static function allNewspapers(): array
    {
        return self::buildTrustedBy(1, PHP_INT_MAX, true);
    }

    private static function buildTrustedBy(int $minimum, int $limit, bool $withHidden = false): array
    {
        $settings = NewspaperLogo::all()->keyBy('name');
        $groups = [];
        $rows = static::active()->whereNotNull('publication')->whereNotNull('image')
            ->selectRaw('publication, COUNT(*) as total')->groupBy('publication')->get();
        foreach ($rows as $row) {
            $name = self::normalizePublication($row->publication);
            if ($name === '') {
                continue;
            }
            $groups[$name]['total'] = ($groups[$name]['total'] ?? 0) + (int) $row->total;
            $groups[$name]['names'][] = $row->publication;
        }
        // Papers added by hand (no articles of their own)
        foreach ($settings as $name => $setting) {
            $groups[$name] ??= ['total' => 0, 'names' => []];
        }
        // Enough articles, or set up by the admin; never a hidden one on the home page
        $groups = array_filter($groups, fn ($g, $name) => ($withHidden || !($settings->get($name)?->is_hidden))
            && ($g['total'] >= $minimum || isset($settings[$name])), ARRAY_FILTER_USE_BOTH);
        $position = fn ($name) => $settings->get($name)?->sort_order ?? PHP_INT_MAX;
        $totals = array_map(fn ($g) => $g['total'], $groups);
        uksort($groups, fn ($a, $b) => $position($a) <=> $position($b) ?: $totals[$b] <=> $totals[$a] ?: strcmp($a, $b));
        $groups = array_slice($groups, 0, $limit, true);

        $list = [];
        foreach ($groups as $name => $g) {
            $setting = $settings->get($name);
            $newest = null;
            if ($g['names']) {
                // Newest by publishing date (imported posts are not in date order by id). Some imported posts carry
                // another paper's page (e.g. "ABTAK DAILY" with "AZAD SANDESH - 12.12.2025.jpg"), so the masthead comes
                // from the newest clipping whose picture file name has this paper's name (or one uploaded here,
                // where the admin chose the paper); only when none does, simply the newest.
                $recent = static::active()->whereIn('publication', $g['names'])->whereNotNull('image')
                    ->orderByDesc('published_on')->orderByDesc('id')->take(40)->get(['id', 'title', 'image', 'published_on']);
                $squash = fn (string $s) => preg_replace('/[^A-Z0-9]+/', '', strtoupper($s));
                $newest = $recent->first(fn (self $a) => $a->isUploadedImage()
                    || str_contains($squash(rawurldecode(basename(parse_url($a->image, PHP_URL_PATH) ?: ''))), $squash($name))) ?? $recent->first();
            }
            $list[] = [
                'name' => $name,
                'label' => $setting?->display_name ?: $name,
                'total' => $g['total'],
                // The official logo (Admin -> Newspaper Logos), else the masthead of the newest clipping
                'logo' => $setting?->smallUrl() ?? $newest?->imageUrl('w400'),
                'official' => (bool) $setting?->logo,
                'masthead' => $newest?->imageUrl('w400'),
                // Clicking the logo opens its own link (Admin -> Newspaper Logos), else all of that newspaper's articles
                'url' => $setting?->link ?: ($g['total'] ? route('media-articles.index', ['publication' => $name]) : route('media-articles.index')),
                'link' => $setting?->link,
                'hidden' => (bool) $setting?->is_hidden,
                'sort_order' => $setting?->sort_order,
                'manual' => !$g['names'],
            ];
        }

        return $list;
    }

    /**
     * Split a title like "INVESTMENT POINT / PAGDANDI - 20.02.2026" into
     * column, publication and date. Missing parts come back as null.
     *
     * @return array{column_name: ?string, publication: ?string, published_on: ?string}
     */
    public static function parseTitle(string $title): array
    {
        $title = trim(preg_replace('/\s+/u', ' ', $title));
        $date = null;
        if (preg_match('/(\d{1,2})[.\-\/](\d{1,2})[.\-\/](\d{4})\s*$/', $title, $m) && checkdate((int) $m[2], (int) $m[1], (int) $m[3])) {
            $date = Carbon::createFromDate((int) $m[3], (int) $m[2], (int) $m[1])->toDateString();
            $title = trim(preg_replace('/\s*[-–]?\s*' . preg_quote($m[0], '/') . '$/u', '', $title));
        }

        // Older posts: "18.07.2013 GUJARAT SAMACHAR" (date first, then the newspaper)
        if (!$date && preg_match('/^(\d{1,2})[.\-\/](\d{1,2})[.\-\/](\d{4})\s+(.+)$/u', $title, $m) && checkdate((int) $m[2], (int) $m[1], (int) $m[3])) {
            return [
                'column_name' => null,
                'publication' => trim($m[4]),
                'published_on' => Carbon::createFromDate((int) $m[3], (int) $m[2], (int) $m[1])->toDateString(),
            ];
        }

        $column = $publication = null;
        if (str_contains($title, '/')) {
            [$column, $publication] = array_map(fn ($s) => trim($s, " \"'“”") ?: null, explode('/', $title, 2));
        }

        return ['column_name' => $column, 'publication' => $publication, 'published_on' => $date];
    }
}
