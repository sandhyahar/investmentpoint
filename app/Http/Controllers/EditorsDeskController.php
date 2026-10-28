<?php

namespace App\Http\Controllers;

use App\Models\EditorArticle;
use App\Models\PageView;
use Illuminate\Http\Request;

/** "Editor's Desk" (menu after Blog): Nikhil Bhatt's daily articles from Admin -> Editor's Desk. */
class EditorsDeskController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->query('q', ''));
        $like = '%' . addcslashes($search, '%_\\') . '%';

        $articles = EditorArticle::active()
            ->when($search !== '', fn ($q) => $q->where(fn ($q) => $q->where('title', 'like', $like)->orWhere('summary', 'like', $like)))
            ->latestFirst()
            ->paginate(12)->withQueryString();

        return view('editors-desk.index', compact('articles', 'search'));
    }

    public function show(EditorArticle $article, ?string $slug = null)
    {
        abort_unless($article->status === 'active', 404);

        $same = fn () => EditorArticle::active();
        $newer = $same()->where(fn ($q) => $q->where('published_on', '>', $article->published_on)
            ->orWhere(fn ($q) => $q->whereDate('published_on', $article->published_on)->where('id', '>', $article->id)))
            ->orderBy('published_on')->orderBy('id')->first();
        $older = $same()->where(fn ($q) => $q->where('published_on', '<', $article->published_on)
            ->orWhere(fn ($q) => $q->whereDate('published_on', $article->published_on)->where('id', '<', $article->id)))
            ->latestFirst()->first();
        $more = $same()->whereKeyNot($article->id)->latestFirst()->take(5)->get();
        $views = PageView::viewsOf(PageView::ARTICLE_ROUTES['editor'], $article->id);

        return view('editors-desk.show', compact('article', 'newer', 'older', 'more', 'views'));
    }
}
