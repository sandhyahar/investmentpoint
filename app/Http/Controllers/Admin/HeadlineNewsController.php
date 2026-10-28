<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\NewsInboxItem;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * CRUD shared by Admin -> Latest News and Admin -> Most Popular News.
 * Each section is its own subclass with its own table, routes and page titles.
 */
abstract class HeadlineNewsController extends Controller
{
    /** @return class-string<Model> */
    abstract protected function model(): string;

    /** Route name prefix, e.g. "admin.latest-news" */
    abstract protected function routePrefix(): string;

    /** Page labels: ['title' => ..., 'singular' => ..., 'ranked' => bool] */
    abstract protected function labels(): array;

    public function index(Request $request)
    {
        $search = trim((string) $request->query('q', ''));
        $items = $this->model()::query()
            ->when($search !== '', fn ($q) => $q->where('title', 'like', '%' . addcslashes($search, '%_\\') . '%'))
            ->ordered()
            ->paginate(20)->withQueryString();

        return view('admin.headline-news.index', $this->shared() + compact('items', 'search'));
    }

    /** ?from=<News Inbox id>: start from a collected story (credit filled in, AI can draft from it). */
    public function create(Request $request)
    {
        $inbox = $request->query('from') ? NewsInboxItem::find((int) $request->query('from')) : null;
        $item = new ($this->model())(['status' => 'active', 'published_at' => now()] + ($inbox ? [
            'title' => $inbox->title,
            'source' => $inbox->source,
            'source_url' => $inbox->url,
        ] : []));

        return view('admin.headline-news.form', $this->shared() + compact('item', 'inbox'));
    }

    public function store(Request $request)
    {
        $this->model()::create($this->validated($request));

        // The story it was written from leaves the inbox
        if ($inboxId = (int) $request->input('inbox_id')) {
            NewsInboxItem::whereKey($inboxId)->update(['status' => 'used']);
        }

        return redirect()->route($this->routePrefix() . '.index')->with('success', $this->labels()['singular'] . ' created successfully.');
    }

    public function edit(int $item)
    {
        return view('admin.headline-news.form', $this->shared() + ['item' => $this->model()::findOrFail($item), 'inbox' => null]);
    }

    public function update(Request $request, int $item)
    {
        $item = $this->model()::findOrFail($item);
        $item->update($this->validated($request, $item));

        return redirect()->route($this->routePrefix() . '.index')->with('success', $this->labels()['singular'] . ' updated successfully.');
    }

    public function destroy(int $item)
    {
        $this->model()::findOrFail($item)->delete();

        return redirect()->route($this->routePrefix() . '.index')->with('success', $this->labels()['singular'] . ' deleted successfully.');
    }

    public function bulkDestroy(Request $request)
    {
        $validated = $request->validate(['ids' => 'required|array', 'ids.*' => 'integer']);
        $items = $this->model()::whereIn('id', $validated['ids'])->get();
        $items->each->delete(); // one by one so uploaded images are removed too

        return redirect()->route($this->routePrefix() . '.index')->with('success', $items->count() . ' item(s) deleted successfully.');
    }

    private function shared(): array
    {
        return ['routePrefix' => $this->routePrefix(), 'labels' => $this->labels()];
    }

    /** Image = an uploaded file, or a link; on edit, leaving both empty keeps the current image. */
    private function validated(Request $request, ?Model $existing = null): array
    {
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:5000',
            'content' => 'nullable|string',
            'source' => 'nullable|string|max:120',
            'source_url' => 'nullable|url|max:1000',
            'keywords' => 'nullable|string|max:500',
            'published_at' => 'nullable|date',
            'sort_order' => 'nullable|integer|min:0|max:100000',
            'image_file' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:4096',
            'image_url' => 'nullable|url|max:1000',
            'status' => 'required|in:active,inactive',
        ]);
        // No summary box on the form: taken from the start of the article; with no article, the one already saved stays
        $data['description'] = \App\Support\Html::summary($data['description'] ?? null, $data['content'] ?? null) ?: (string) $existing?->description;

        if ($request->hasFile('image_file')) {
            $image = \App\Support\ImageOptimizer::storeFilled($request->file('image_file'), 'headline-news');
        } elseif (!empty($data['image_url'])) {
            $image = $data['image_url'];
        } else {
            $image = $existing?->image;
        }
        // Replaced an uploaded image -> remove the old file
        if ($existing && $existing->image !== $image && $existing->isUploadedImage()) {
            Storage::disk('public')->delete($existing->image);
        }

        unset($data['image_file'], $data['image_url']);
        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);
        $data['published_at'] = $data['published_at'] ?? ($existing?->published_at ?? now());
        // Saved as Active by the admin = reviewed: the automatic fact-check won't hide it again
        if ($data['status'] === 'active' && !$existing?->fact_checked_at) {
            $data['fact_checked_at'] = now();
        }

        return $data + ['image' => $image];
    }
}
