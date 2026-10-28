<?php

namespace App\Models\Concerns;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Shared by LatestNews and PopularNews: same columns, only the list order and public URL differ.
 * The using model defines SHOW_ROUTE (public page route name).
 */
trait IsHeadlineNews
{
    public function initializeIsHeadlineNews(): void
    {
        $this->mergeFillable(['title', 'description', 'keywords', 'content', 'image', 'image_credit', 'image_credit_url', 'source', 'source_url', 'fact_checked_at', 'published_at', 'sort_order', 'status']);
        $this->mergeCasts(['published_at' => 'datetime', 'fact_checked_at' => 'datetime', 'sort_order' => 'integer', 'views' => 'integer']);
    }

    protected static function bootIsHeadlineNews(): void
    {
        static::deleting(function ($item) {
            if ($item->isUploadedImage()) {
                Storage::disk('public')->delete($item->image);
            }
        });
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function getUrlAttribute(): string
    {
        // Gujarati/Hindi titles slug to an empty string, so the id is the real key
        return route(static::SHOW_ROUTE, array_filter(['id' => $this->id, 'slug' => Str::slug($this->title)]));
    }

    /** Uploaded images live on the public disk; seeded/pasted ones are full URLs. */
    public function isUploadedImage(): bool
    {
        return $this->image && !Str::startsWith($this->image, ['http://', 'https://']);
    }

    public function getImageUrlAttribute(): string
    {
        if (!$this->image) {
            return 'https://images.unsplash.com/photo-1611974789855-9c2a0a7236a3?auto=format&fit=crop&w=1200&q=80';
        }

        if (!$this->isUploadedImage()) {
            return $this->image;
        }
        // ?v=: browsers keep pictures 30 days; a file changed in place (images:fill-business-news) gets a new address
        $file = storage_path('app/public/' . $this->image);

        return asset('storage/' . $this->image) . (is_file($file) ? '?v=' . filemtime($file) : '');
    }

    /** Full article body; falls back to the short summary. */
    public function getBodyAttribute(): string
    {
        return $this->content ?: (string) $this->description;
    }

    /** Date shown to readers: the chosen publish time, else when it was added. */
    public function getDisplayDateAttribute()
    {
        return $this->published_at ?? $this->created_at;
    }
}
