<?php

namespace App\Models;

use App\Support\Html;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/** One "Editor's Desk" article: written by Nikhil Bhatt in Admin -> Editor's Desk. */
class EditorArticle extends Model
{
    protected $fillable = ['title', 'summary', 'content', 'image', 'published_on', 'status'];

    protected $casts = [
        'published_on' => 'date',
    ];

    protected static function booted()
    {
        static::deleting(function (EditorArticle $article) {
            if ($article->image && !Str::startsWith($article->image, ['http://', 'https://'])) {
                Storage::disk('public')->delete($article->image);
            }
        });
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /** Newest first (same day: the one added last first). */
    public function scopeLatestFirst($query)
    {
        return $query->orderByDesc('published_on')->orderByDesc('id');
    }

    public function getUrlAttribute(): string
    {
        return route('editors-desk.show', array_filter(['article' => $this->id, 'slug' => Str::slug(Str::limit($this->title, 80, '')) ?: null]));
    }

    /** The picture, or null when none was added (pages then show the site logo). */
    public function getImageUrlAttribute(): ?string
    {
        if (!$this->image) {
            return null;
        }

        return Str::startsWith($this->image, ['http://', 'https://']) ? $this->image : asset('storage/' . $this->image);
    }

    /** List / share text: the summary, or the start of the article. */
    public function excerpt(int $limit = 160): string
    {
        return Html::plain((string) ($this->summary ?: $this->content), $limit);
    }
}
