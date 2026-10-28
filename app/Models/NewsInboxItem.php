<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A headline collected from a publisher's RSS feed, waiting in Admin -> News Inbox. */
class NewsInboxItem extends Model
{
    protected $fillable = ['source', 'url', 'url_hash', 'title', 'summary', 'published_at', 'score', 'coverage', 'status'];

    protected $casts = [
        'published_at' => 'datetime',
        'score' => 'integer',
        'coverage' => 'integer',
    ];

    public function scopeOpen($query)
    {
        return $query->where('status', 'new');
    }
}
