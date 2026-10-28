<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * One E-Paper edition: a title plus its page images. `edition` says which paper: the Weekly News Paper
 * or the daily Gujarati / Hindi / English e-paper (E-Paper menu on the site, Admin -> E-Paper).
 */
class DigitalNewspaper extends Model
{
    /** edition key => admin / menu label; the daily ones match config('epaper.editions') */
    public const EDITIONS = ['gujarati' => 'Gujarati', 'hindi' => 'Hindi', 'english' => 'English', 'weekly' => 'Weekly News Paper'];

    protected $fillable = ['edition', 'blogger_id', 'blogger_url', 'title', 'week_from', 'week_to', 'published_on', 'pages', 'status', 'publish_at'];

    protected $attributes = ['edition' => 'weekly'];

    protected $casts = [
        'week_from' => 'date',
        'week_to' => 'date',
        'published_on' => 'date',
        // Scheduled by the admin: shown to readers (and posted to Blogger) only from this moment (stored in UTC)
        'publish_at' => 'datetime',
        'pages' => 'array',
    ];

    protected static function booted()
    {
        static::deleting(function (DigitalNewspaper $paper) {
            // Posted to Blogger from here (blogger_url): the post shows these pictures from the website, keep them
            if (!$paper->blogger_url) {
                Storage::disk('public')->delete($paper->uploadedPages());
            }
        });
    }

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

    /** Newest edition first. */
    public function scopeLatestFirst($query)
    {
        return $query->orderByDesc('published_on')->orderByDesc('id');
    }

    /** Editions of one paper: weekly | gujarati | hindi | english */
    public function scopeEdition($query, string $edition)
    {
        return $query->where('edition', $edition);
    }

    /**
     * The same edition already on the site (bulk upload): the same paper with the same edition date (Weekly: week
     * start), or the same title. Null when there is none.
     */
    public static function findDuplicate(string $edition, string $title, ?string $date): ?self
    {
        $title = trim(preg_replace('/\s+/u', ' ', $title));
        if ($title === '' && !$date) {
            return null;
        }

        return static::edition($edition)
            ->where(function ($q) use ($edition, $title, $date) {
                if ($date) {
                    $q->whereDate($edition === 'weekly' ? 'week_from' : 'published_on', $date);
                }
                if ($title !== '') {
                    $q->orWhere('title', $title);
                }
            })
            ->orderByDesc('id')->first();
    }

    /**
     * The same edition, as far as Blogger imports go: the edition date plus the title without spaces, case and
     * punctuation ("INVESTMENT POINT  - 26.02.2026" = "Investment Point - 26.02.2026").
     */
    public static function sameEditionKey(string $title, string $date): string
    {
        return $date . '|' . mb_strtolower(preg_replace('/[^\p{L}\p{N}]+/u', '', $title));
    }

    public function isWeekly(): bool
    {
        return ($this->edition ?? 'weekly') === 'weekly';
    }

    public function getUrlAttribute(): string
    {
        return $this->isWeekly()
            ? route('digital-newspaper.show', array_filter(['id' => $this->id, 'slug' => Str::slug($this->title)]))
            : route('epaper.show', ['language' => $this->edition, 'id' => $this->id]);
    }

    /** "19 Sep 2026 – 25 Sep 2026", or the edition date for specials without a week. */
    public function getDateLabelAttribute(): string
    {
        if ($this->week_from && $this->week_to) {
            return $this->week_from->format('d M Y') . ' – ' . $this->week_to->format('d M Y');
        }

        return $this->published_on->format('d M Y');
    }

    /** Pages uploaded in admin (paths on the public disk), as opposed to Blogger image links. */
    public function uploadedPages(): array
    {
        return array_values(array_filter($this->pages ?? [], fn ($p) => !self::isLink($p)));
    }

    /** @return array<int, array{view: string, full: string, thumb: string}> */
    public function pageImages(): array
    {
        return array_map(fn ($p) => [
            'view' => self::imageUrl($p, 'w1200'),
            'full' => self::imageUrl($p, 's0'),
            'thumb' => self::imageUrl($p, 'w160'),
        ], $this->pages ?? []);
    }

    public function coverUrl(): ?string
    {
        $first = $this->pages[0] ?? null;

        return $first ? self::imageUrl($first, 'w400') : null;
    }

    public static function isLink(string $page): bool
    {
        return Str::startsWith($page, ['http://', 'https://']);
    }

    /** Uploaded file -> public URL; Blogger image -> the same image at the size asked for (s0 = original). */
    public static function imageUrl(string $page, string $size = 'w1200'): string
    {
        if (!self::isLink($page)) {
            return asset('storage/' . $page);
        }
        if (!Str::contains($page, ['blogger.googleusercontent.com', 'bp.blogspot.com'])) {
            return $page;
        }

        return preg_replace('#/(?:[swh]\d+(?:-[swhc]\d*)*)(/[^/]+)$#', '/' . $size . '$1', $page, 1);
    }

    /**
     * Read one blogspot post: title "INVESTMENT POINT WEEKLY - 19.09.2026 TO 25.09.2026" and one image per page.
     * Specials without a week in the title ("DEEPAWALI - 2023") are dated by the post date. Daily e-papers
     * ("GUJ - INVESTMENT POINT DAILY - 01.10.2026") have no week: dated by the date in the title.
     *
     * @return array{title: string, week_from: ?string, week_to: ?string, published_on: string, pages: array}|null
     */
    public static function parsePost(string $title, string $html, ?string $postedAt, bool $weekly = true): ?array
    {
        // Link to the full-size image when there is one (<a href="…/s1746/11.jpg"><img src="…/w474-h640/11.jpg">)
        preg_match_all('/<img[^>]+src="(https:\/\/[^"]+)"/i', $html, $m);
        $pages = array_values(array_unique(array_map(fn ($src) => self::imageUrl($src, 's0'), $m[1])));
        if (!$pages) {
            return null;
        }

        $title = trim(preg_replace('/\s+/u', ' ', $title)) ?: ($weekly ? 'Investment Point Weekly' : 'Investment Point');
        preg_match_all('/(\d{1,2})[.\-\/](\d{1,2})[.\-\/](\d{4})/', $title, $d, PREG_SET_ORDER);
        $dates = [];
        foreach ($d as $x) {
            if (checkdate((int) $x[2], (int) $x[1], (int) $x[3])) {
                $dates[] = Carbon::createFromDate((int) $x[3], (int) $x[2], (int) $x[1])->toDateString();
            }
        }
        $posted = $postedAt ? Carbon::parse($postedAt)->timezone('Asia/Kolkata')->toDateString() : now()->toDateString();

        return [
            'title' => mb_substr($title, 0, 255),
            'week_from' => $weekly ? ($dates[0] ?? null) : null,
            'week_to' => $weekly ? ($dates[1] ?? null) : null,
            'published_on' => $dates[0] ?? $posted,
            'pages' => $pages,
        ];
    }
}
