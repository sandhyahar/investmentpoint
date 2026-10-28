<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class News extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'share_name',
        'description',
        'content',
        'image',
        'status',
        'is_featured',
    ];

    protected $casts = [
        'is_featured' => 'boolean',
        'views' => 'integer',
    ];

    public function getUrlAttribute(): string
    {
        // Gujarati/Hindi titles slug to an empty string, so the id is the real key
        return route('news.show', array_filter(['news' => $this->id, 'slug' => Str::slug($this->title)]));
    }

    /** The link the Share buttons send: investmentpoint.in/nifty when this is the newest Nifty article */
    public function getShareUrlAttribute(): string
    {
        return \App\Support\ShortLink::urlFor($this);
    }

    public function getImageUrlAttribute(): string
    {
        if (!$this->image) {
            return 'https://images.unsplash.com/photo-1611974789855-9c2a0a7236a3?auto=format&fit=crop&w=1200&q=80';
        }

        return Str::startsWith($this->image, ['http://', 'https://']) ? $this->image : asset('storage/' . $this->image);
    }

    /**
     * Full article body; falls back to the short description for older rows.
     */
    public function getBodyAttribute(): string
    {
        return $this->content ?: (string) $this->description;
    }
}
