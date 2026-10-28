<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ExpertPost;
use App\Support\Html;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ExpertPostController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->query('q', ''));
        $posts = ExpertPost::query()
            ->when($search !== '', fn ($q) => $q->where(fn ($q) => $q
                ->where('title', 'like', '%' . addcslashes($search, '%_\\') . '%')
                ->orWhere('author', 'like', '%' . addcslashes($search, '%_\\') . '%')))
            ->orderByDesc('published_on')->orderByDesc('id')
            ->paginate(20)->withQueryString();

        $views = \App\Models\PageView::viewsPerArticle('expert', $posts->pluck('id'));

        return view('admin.expert-posts.index', compact('posts', 'search', 'views'));
    }

    public function create()
    {
        return view('admin.expert-posts.create');
    }

    public function store(Request $request)
    {
        ExpertPost::create($this->validated($request));

        return redirect()->route('admin.expert-posts.index')->with('success', 'Article posted successfully.');
    }

    public function edit(ExpertPost $expertPost)
    {
        return view('admin.expert-posts.edit', ['post' => $expertPost]);
    }

    public function update(Request $request, ExpertPost $expertPost)
    {
        $expertPost->update($this->validated($request, $expertPost));

        return redirect()->route('admin.expert-posts.index')->with('success', 'Article updated successfully.');
    }

    public function destroy(ExpertPost $expertPost)
    {
        $expertPost->delete();

        return redirect()->route('admin.expert-posts.index')->with('success', 'Article deleted successfully.');
    }

    public function bulkDestroy(Request $request)
    {
        $validated = $request->validate(['ids' => 'required|array', 'ids.*' => 'integer']);
        $posts = ExpertPost::whereIn('id', $validated['ids'])->get();
        $posts->each->delete(); // one by one so uploaded covers are removed too

        return redirect()->route('admin.expert-posts.index')->with('success', $posts->count() . ' article(s) deleted successfully.');
    }

    private function validated(Request $request, ?ExpertPost $existing = null): array
    {
        $data = $request->validate([
            'title' => 'required|string|max:500',
            'author' => 'nullable|string|max:150',
            'content' => 'required|string',
            'image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:4096',
            'remove_image' => 'nullable|boolean',
            'published_on' => 'required|date',
            'status' => 'required|in:active,inactive',
        ]);

        $data['content'] = Html::clean($data['content']);
        unset($data['remove_image']);

        if ($request->hasFile('image')) {
            $data['image'] = \App\Support\ImageOptimizer::store($request->file('image'), 'expert-posts');
        } elseif ($request->boolean('remove_image')) {
            $data['image'] = null;
        } else {
            unset($data['image']); // keep the current cover
        }
        // Replaced or removed an uploaded cover -> delete the old file
        if ($existing && array_key_exists('image', $data) && $existing->isUploadedImage()) {
            Storage::disk('public')->delete($existing->image);
        }

        return $data;
    }
}
