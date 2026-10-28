<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MediaArticle;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class MediaArticleController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->query('q', ''));
        $articles = MediaArticle::query()
            ->when($search !== '', fn ($q) => $q->where(fn ($q) => $q
                ->where('title', 'like', '%' . addcslashes($search, '%_\\') . '%')
                ->orWhere('publication', 'like', '%' . addcslashes($search, '%_\\') . '%')))
            ->orderByDesc('published_on')->orderByDesc('id')
            ->paginate(20)->withQueryString();

        $views = \App\Models\PageView::viewsPerArticle('media', $articles->pluck('id'));

        return view('admin.media-articles.index', compact('articles', 'search', 'views'));
    }

    public function create()
    {
        // Bulk upload: newspaper names already on the site, to recognise them in file and folder names
        return view('admin.media-articles.create', ['knownPublications' => MediaArticle::knownPublications()]);
    }

    /**
     * Bulk upload, before uploading: which of these files are already on the website
     * (same date + same newspaper, or same date + same title). items[] = {key, title, publication, published_on}
     */
    public function bulkCheck(Request $request)
    {
        $data = $request->validate([
            'items' => 'required|array|max:500',
            'items.*.key' => 'required|string|max:100',
            'items.*.title' => 'nullable|string|max:255',
            'items.*.publication' => 'nullable|string|max:120',
            'items.*.published_on' => 'nullable|date',
        ]);

        $found = [];
        foreach ($data['items'] as $item) {
            if (!empty($item['published_on']) && ($existing = MediaArticle::findDuplicate($item['title'] ?? '', $item['publication'] ?? null, $item['published_on']))) {
                $found[$item['key']] = ['id' => $existing->id, 'title' => $existing->title, 'url' => $existing->url];
            }
        }

        return response()->json(['duplicates' => (object) $found]);
    }

    /**
     * Bulk upload: one PDF = one Media Article, saved exactly like the single form (same fields, same page
     * pictures made in the browser). Answers in JSON; a duplicate is refused (409) unless "force" is sent.
     */
    public function bulkStore(Request $request)
    {
        $data = $this->validated($request);
        if (!$request->boolean('force') && ($existing = MediaArticle::findDuplicate($data['title'], $data['publication'] ?? null, $data['published_on']))) {
            // The pages were already stored by validated(): remove them again
            Storage::disk('public')->delete($data['pages'] ?? []);

            return response()->json(['message' => 'Already on the website: ' . $existing->title, 'duplicate' => ['id' => $existing->id, 'url' => $existing->url]], 409);
        }

        $article = MediaArticle::create($data);
        MediaArticle::forgetPublicationCaches();
        \App\Support\ContentVersion::bump();
        \App\Services\BloggerPublisher::soon();

        return response()->json(['id' => $article->id, 'title' => $article->title, 'url' => $article->url, 'pages' => count($article->pages ?? [])], 201);
    }

    public function store(Request $request)
    {
        MediaArticle::create($this->validated($request));
        MediaArticle::forgetPublicationCaches();
        \App\Services\BloggerPublisher::soon(); // also to the Media Article blog

        return redirect()->route('admin.media-articles.index')->with('success', 'Media article added successfully.');
    }

    public function edit(MediaArticle $mediaArticle)
    {
        return view('admin.media-articles.edit', compact('mediaArticle'));
    }

    public function update(Request $request, MediaArticle $mediaArticle)
    {
        $mediaArticle->update($this->validated($request, $mediaArticle));
        MediaArticle::forgetPublicationCaches();
        \App\Services\BloggerPublisher::soon(); // e.g. switched from inactive to active

        return redirect()->route('admin.media-articles.index')->with('success', 'Media article updated successfully.');
    }

    public function destroy(MediaArticle $mediaArticle)
    {
        $mediaArticle->delete();
        MediaArticle::forgetPublicationCaches();

        return redirect()->route('admin.media-articles.index')->with('success', 'Media article deleted successfully.');
    }

    public function bulkDestroy(Request $request)
    {
        $validated = $request->validate(['ids' => 'required|array', 'ids.*' => 'integer']);
        $items = MediaArticle::whereIn('id', $validated['ids'])->get();
        $items->each->delete(); // one by one so uploaded images are removed too
        MediaArticle::forgetPublicationCaches();

        return redirect()->route('admin.media-articles.index')->with('success', $items->count() . ' media article(s) deleted successfully.');
    }

    /**
     * The article is uploaded as a PDF: the form turns each page into an image (page_files[], in page order).
     * They replace all the old pages (old uploaded files are deleted); page 1 is the cover ("image") shown in
     * the lists. Without a new PDF an edited article keeps its pages. The description is no longer on the form,
     * so an older article keeps the one it has.
     */
    private function validated(Request $request, ?MediaArticle $existing = null): array
    {
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'column_name' => 'nullable|string|max:120',
            'publication' => 'nullable|string|max:120',
            'published_on' => 'required|date',
            // max_file_uploads on the server is usually 20
            'page_files' => 'nullable|array|max:20',
            'page_files.*' => 'image|mimes:jpg,jpeg,png,webp|max:8192',
            'status' => 'required|in:active,inactive',
        ] + \App\Support\Schedule::RULES, \App\Support\Schedule::MESSAGES + [
            'page_files.max' => 'Up to 20 pages can be uploaded at once.',
            'page_files.*.max' => 'Each page must be 8 MB or smaller.',
        ]);
        // Schedule date and time, India time (empty = as soon as it is saved)
        $data['publish_at'] = \App\Support\Schedule::publishAt($data);
        unset($data['page_files'], $data['publish_date'], $data['publish_time']);

        $uploads = $request->file('page_files', []);
        if (!$uploads) {
            if (!$existing) {
                throw ValidationException::withMessages(['page_files' => 'Please upload the article PDF.']);
            }

            return $data;
        }

        // Pages are read on screen: wider and sharper than photos (like the e-paper)
        $pages = collect($uploads)->map(fn ($file) => \App\Support\ImageOptimizer::store($file, 'media-articles', 2000, 85))->values()->all();
        if ($existing) {
            Storage::disk('public')->delete(array_diff($existing->uploadedFiles(), $pages));
        }

        return $data + ['image' => $pages[0], 'pages' => $pages];
    }
}
