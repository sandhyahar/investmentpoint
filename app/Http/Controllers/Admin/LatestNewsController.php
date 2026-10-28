<?php

namespace App\Http\Controllers\Admin;

use App\Models\LatestNews;

/** Admin -> Latest News: the homepage "Latest News" box and the "View All" news page. */
class LatestNewsController extends HeadlineNewsController
{
    protected function model(): string
    {
        return LatestNews::class;
    }

    protected function routePrefix(): string
    {
        return 'admin.latest-news';
    }

    protected function labels(): array
    {
        return [
            'title' => 'Latest News',
            'singular' => 'Latest news',            'ranked' => false,
        ];
    }
}
