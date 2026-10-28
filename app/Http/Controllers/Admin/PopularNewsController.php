<?php

namespace App\Http\Controllers\Admin;

use App\Models\PopularNews;

/** Admin -> Most Popular News: the ranked "Most Popular" list in the news sidebar. */
class PopularNewsController extends HeadlineNewsController
{
    protected function model(): string
    {
        return PopularNews::class;
    }

    protected function routePrefix(): string
    {
        return 'admin.popular-news';
    }

    protected function labels(): array
    {
        return [
            'title' => 'Most Popular News',
            'singular' => 'Popular news',            'ranked' => true,
        ];
    }
}
