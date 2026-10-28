<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\News;
use App\Support\ShortLink;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class NewsController extends Controller
{
    use Concerns\SendsToPress;

    public function index()
    {
        $news = News::latest()->paginate(20);
        return view('admin.news.index', compact('news'));
    }

    public function create()
    {
        return view('admin.news.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'share_name' => ShortLink::rule(),
            'description' => 'nullable|string',
            'content' => 'nullable|string',
            'image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'status' => 'required|in:active,inactive',
            'is_featured' => 'nullable|boolean',
        ]);
        $validated['description'] = \App\Support\Html::summary($validated['description'] ?? null, $validated['content'] ?? null);

        $validated['is_featured'] = (bool) ($validated['is_featured'] ?? false);
        $validated['share_name'] = ShortLink::normalize($validated['share_name'] ?? null);
        $press = $this->validatePress($request);

        if ($request->hasFile('image')) {
            $validated['image'] = \App\Support\ImageOptimizer::storeFilled($request->file('image'), 'news');
        }

        if ($validated['is_featured']) {
            News::where('is_featured', true)->update(['is_featured' => false]);
        }

        $news = News::create($validated);

        return redirect()->route('admin.news.index')->with('success', 'News created successfully.' . $this->sendToPress($news, 'news', $press));
    }

    public function edit(News $news)
    {
        return view('admin.news.edit', compact('news'));
    }

    public function update(Request $request, News $news)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'share_name' => ShortLink::rule(),
            'description' => 'nullable|string',
            'content' => 'nullable|string',
            'image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'status' => 'required|in:active,inactive',
            'is_featured' => 'nullable|boolean',
        ]);
        // No summary box on the form: taken from the start of the article; with no article, the one already saved stays
        $validated['description'] = \App\Support\Html::summary($validated['description'] ?? null, $validated['content'] ?? null) ?: (string) $news->description;

        $validated['is_featured'] = (bool) ($validated['is_featured'] ?? false);
        $validated['share_name'] = ShortLink::normalize($validated['share_name'] ?? null);
        $press = $this->validatePress($request);

        if ($request->hasFile('image')) {
            // Delete old image
            if ($news->image) {
                Storage::disk('public')->delete($news->image);
            }
            $validated['image'] = \App\Support\ImageOptimizer::storeFilled($request->file('image'), 'news');
        }

        if ($validated['is_featured']) {
            News::where('is_featured', true)->whereKeyNot($news->getKey())->update(['is_featured' => false]);
        }

        $news->update($validated);

        return redirect()->route('admin.news.index')->with('success', 'News updated successfully.' . $this->sendToPress($news, 'news', $press));
    }

    public function destroy(News $news)
    {
        if ($news->image) {
            Storage::disk('public')->delete($news->image);
        }

        $news->delete();

        return redirect()->route('admin.news.index')->with('success', 'News deleted successfully.');
    }

    public function bulkDestroy(Request $request)
    {
        $validated = $request->validate(['ids' => 'required|array', 'ids.*' => 'integer']);
        $items = News::whereIn('id', $validated['ids'])->get();
        foreach ($items as $news) {
            if ($news->image) {
                Storage::disk('public')->delete($news->image);
            }
            $news->delete();
        }
        return redirect()->route('admin.news.index')->with('success', $items->count() . ' news item(s) deleted successfully.');
    }
}
