<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class TechnicalAnalysis extends Model
{
    use HasFactory;

    protected $table = 'technical_analyses';

    protected $fillable = [
        'title',
        'share_name',
        'cmp',
        'support',
        'resistance',
        'description',
        'content',
        'image',
        'status',
        'published_on',
    ];

    // The date the admin chose for the card and the article (Admin -> Technical Analysis -> Date); empty = no date shown
    protected $casts = [
        'published_on' => 'date',
    ];

    /** Newest first by the admin's date; analyses without a date go by the day they were added */
    public function scopeNewest($query)
    {
        return $query->orderByRaw('COALESCE(published_on, DATE(created_at)) DESC')->orderByDesc('id');
    }

    public function getUrlAttribute(): string
    {
        return route('analysis.show', array_filter(['analysis' => $this->id, 'slug' => Str::slug($this->title)]));
    }

    /** The link the Share buttons send: investmentpoint.in/hdfc-bank when this is the newest HDFC Bank analysis */
    public function getShareUrlAttribute(): string
    {
        return \App\Support\ShortLink::urlFor($this);
    }

    public function getImageUrlAttribute(): string
    {
        if (!$this->image) {
            return 'https://images.unsplash.com/photo-1642790106117-e829e14a795f?auto=format&fit=crop&w=1200&q=80';
        }

        if (Str::startsWith($this->image, ['http://', 'https://'])) {
            return $this->image;
        }
        // ?v=: browsers keep pictures 30 days; a file changed in place (images:unframe-analysis) gets a new address
        $file = storage_path('app/public/' . $this->image);

        return asset('storage/' . $this->image) . (is_file($file) ? '?v=' . filemtime($file) : '');
    }

    /**
     * Full analysis text; falls back to the short summary for older rows.
     */
    public function getBodyAttribute(): string
    {
        return $this->content ?: (string) $this->description;
    }

    /** Admin input may be a single price or a range: digits, spaces, "-", "/", "," and "." (e.g. "610 - 620"). */
    public const LEVEL_RULE = ['nullable', 'string', 'max:50', 'regex:/^[0-9.,\s\-\/₹]+$/u'];

    /**
     * Each price gets ₹ and Indian grouping, the rest is kept as typed:
     * "1390" -> ₹1,390, "616.5" -> ₹616.50, "610 - 620" -> ₹610 - ₹620, "618/620" -> ₹618/₹620.
     */
    public static function formatLevel($value): ?string
    {
        $value = trim(str_replace('₹', '', (string) $value));
        if ($value === '') {
            return null;
        }

        return preg_replace_callback('/\d[\d,]*(?:\.\d+)?/', fn ($m) => self::formatPrice((float) str_replace(',', '', $m[0])), $value);
    }

    private static function formatPrice(float $value): string
    {
        $decimals = floor($value) == $value ? 0 : 2;
        $formatted = number_format($value, $decimals, '.', '');
        [$int, $fraction] = array_pad(explode('.', $formatted), 2, null);

        // Indian grouping: last 3 digits, then groups of 2 (12,34,567)
        $last3 = substr($int, -3);
        $rest = substr($int, 0, -3);
        $grouped = $rest !== '' ? preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', $rest) . ',' . $last3 : $last3;

        return '₹' . $grouped . ($fraction !== null ? '.' . $fraction : '');
    }
}
