<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EditorArticle;
use App\Models\PageView;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/** Admin -> Editor's Desk: Nikhil Bhatt's daily articles (site menu: Editor's Desk, after Blog). */
class EditorArticleController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->query('q', ''));
        $articles = EditorArticle::query()
            ->when($search !== '', fn ($q) => $q->where('title', 'like', '%' . addcslashes($search, '%_\\') . '%'))
            ->latestFirst()
            ->paginate(20)->withQueryString();
        $views = PageView::viewsPerArticle('editor', $articles->pluck('id'));

        return view('admin.editor-articles.index', compact('articles', 'search', 'views'));
    }

    public function create()
    {
        return view('admin.editor-articles.form', ['article' => new EditorArticle(['status' => 'active', 'published_on' => now('Asia/Kolkata')->toDateString()])]);
    }

    public function store(Request $request)
    {
        EditorArticle::create($this->validated($request));

        return redirect()->route('admin.editor-articles.index')->with('success', 'Article published successfully.');
    }

    public function edit(EditorArticle $editorArticle)
    {
        return view('admin.editor-articles.form', ['article' => $editorArticle]);
    }

    public function update(Request $request, EditorArticle $editorArticle)
    {
        $editorArticle->update($this->validated($request, $editorArticle));

        return redirect()->route('admin.editor-articles.index')->with('success', 'Article updated successfully.');
    }

    public function destroy(EditorArticle $editorArticle)
    {
        $editorArticle->delete();

        return redirect()->route('admin.editor-articles.index')->with('success', 'Article deleted successfully.');
    }

    public function bulkDestroy(Request $request)
    {
        $validated = $request->validate(['ids' => 'required|array', 'ids.*' => 'integer']);
        $items = EditorArticle::whereIn('id', $validated['ids'])->get();
        $items->each->delete(); // one by one so pictures are removed too

        return redirect()->route('admin.editor-articles.index')->with('success', $items->count() . ' article(s) deleted successfully.');
    }

    private function validated(Request $request, ?EditorArticle $existing = null): array
    {
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'summary' => 'nullable|string|max:1000',
            'content' => 'required|string',
            'image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:4096',
            'remove_image' => 'nullable|boolean',
            'published_on' => 'required|date',
            'status' => 'required|in:active,inactive',
        ], [
            'content.required' => 'Please write the article.',
        ]);

        $image = $existing?->image;
        if (($request->hasFile('image') || $request->boolean('remove_image')) && $image) {
            Storage::disk('public')->delete($image);
            $image = null;
        }
        if ($request->hasFile('image')) {
            $image = \App\Support\ImageOptimizer::storeFilled($request->file('image'), 'editor-articles');
        }

        return [
            'title' => trim($data['title']),
            'summary' => trim((string) ($data['summary'] ?? '')) ?: null,
            'content' => $data['content'],
            'image' => $image,
            'published_on' => $data['published_on'],
            'status' => $data['status'],
        ];
    }
}
