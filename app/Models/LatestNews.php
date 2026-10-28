<?php

namespace App\Models;

use App\Models\Concerns\IsHeadlineNews;
use Illuminate\Database\Eloquent\Model;

/** Homepage "Latest News" box and the "View All" news page (Admin -> Latest News). */
class LatestNews extends Model
{
    use IsHeadlineNews;

    public const SHOW_ROUTE = 'latest-news.show';

    protected $table = 'latest_news';

    /** Newest first (by publish time, then by when it was added). */
    public function scopeOrdered($query)
    {
        return $query->orderByDesc('published_at')->orderByDesc('id');
    }
}
