<?php

namespace App\Models;

use App\Models\Concerns\IsHeadlineNews;
use Illuminate\Database\Eloquent\Model;

/** "Most Popular" list in the news sidebar (Admin -> Most Popular News). */
class PopularNews extends Model
{
    use IsHeadlineNews;

    public const SHOW_ROUTE = 'popular-news.show';

    protected $table = 'popular_news';

    /** Pinned stories first (Position 1, 2, ...), then the newest (auto-published ones have Position 0). */
    public function scopeOrdered($query)
    {
        return $query->orderByRaw('CASE WHEN sort_order > 0 THEN 0 ELSE 1 END')
            ->orderBy('sort_order')->orderByDesc('published_at')->orderByDesc('id');
    }
}
