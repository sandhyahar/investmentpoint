<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Media;
use App\Support\ImageOptimizer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Media Library, like WordPress: pictures are uploaded here once and then picked for any image field
 * in the admin panel ("Media Library" button next to "Choose File"). Picking copies the picture into
 * that form's file input, so the article keeps its own copy: deleting a picture here never breaks
 * an article that already uses it.
 */
class MediaController extends Controller
{
    /** The "Sort" choices (page and picker): key => label */
    public const SORTS = ['newest' => 'Newest first', 'oldest' => 'Oldest first', 'az' => 'Name A → Z', 'za' => 'Name Z → A'];

    public function index(Request $request)
    {
        $sort = isset(self::SORTS[$request->input('sort')]) ? $request->input('sort') : 'newest';
        $media = Media::query()
            ->when($request->filled('q'), fn ($q) => $q->where('name', 'like', '%' . $request->input('q') . '%'))
            ->when($sort === 'az', fn ($q) => $q->orderByRaw('LOWER(name) asc'))
            ->when($sort === 'za', fn ($q) => $q->orderByRaw('LOWER(name) desc'))
            ->orderBy('id', $sort === 'oldest' ? 'asc' : 'desc')
            ->paginate($request->wantsJson() ? 40 : 60)
            ->withQueryString();

        if ($request->wantsJson()) {
            return response()->json([
                'data' => $media->getCollection()->map(fn (Media $m) => $m->details()),
                'next' => $media->hasMorePages() ? $media->currentPage() + 1 : null,
                'total' => $media->total(),
            ]);
        }

        $sorts = self::SORTS;

        return view('admin.media.index', compact('media', 'sort', 'sorts'));
    }

    /** The picture's name from its file name ("ZYDUSLIFE - Zydus Life.jpg" -> "ZYDUSLIFE - Zydus Life") */
    private static function nameOf(\Illuminate\Http\UploadedFile $file): string
    {
        return mb_substr(trim(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)) ?: 'image', 0, 200);
    }

    /** A picture already in the library with this name (capitals don't count), other than $except */
    private static function sameName(string $name, ?int $except = null): ?Media
    {
        return Media::query()->whereRaw('LOWER(name) = ?', [mb_strtolower(trim($name))])
            ->when($except, fn ($q) => $q->whereKeyNot($except))->first();
    }

    /** Upload one or more pictures (the drop area on the Media Library page and in the picker) */
    public function store(Request $request)
    {
        $request->validate([
            'files' => 'required|array|max:50',
            'files.*' => 'image|mimes:jpg,jpeg,png,webp|max:10240',
        ], [
            'files.*.image' => 'Only pictures (JPG, PNG, WebP) can be added.',
            'files.*.mimes' => 'Only pictures (JPG, PNG, WebP) can be added.',
            'files.*.max' => 'A picture can be at most 10 MB.',
        ]);

        // One name, one picture: a file with the name of a picture already in the library (or twice in this
        // upload) is not added. The answer names the picture that is there, so the upload area can show it.
        $seen = [];
        foreach ($request->file('files') as $i => $file) {
            $name = self::nameOf($file);
            $existing = self::sameName($name);
            if ($existing || isset($seen[mb_strtolower($name)])) {
                $message = 'A picture named "' . $name . '" is already in the Media Library. Rename the file and upload it again, or use the picture that is already there.';
                if ($request->wantsJson()) {
                    return response()->json(['message' => $message, 'errors' => ["files.$i" => [$message]],
                        'duplicate' => $existing?->details()], 422);
                }

                return back()->withErrors(["files.$i" => $message]);
            }
            $seen[mb_strtolower($name)] = true;
        }

        $added = [];
        foreach ($request->file('files') as $file) {
            $path = ImageOptimizer::store($file, 'media');
            $absolute = Storage::disk('public')->path($path);
            $size = @getimagesize($absolute);
            $added[] = Media::create([
                'path' => $path,
                'name' => self::nameOf($file),
                'mime' => $size['mime'] ?? $file->getMimeType(),
                'size' => (int) @filesize($absolute),
                'width' => $size[0] ?? null,
                'height' => $size[1] ?? null,
            ]);
        }

        if ($request->wantsJson()) {
            return response()->json(['data' => collect($added)->map(fn (Media $m) => $m->details())]);
        }

        return back()->with('success', count($added) . ' ' . (count($added) === 1 ? 'picture' : 'pictures') . ' added to the Media Library.');
    }

    /** Rename a picture (the name is what the search finds) */
    public function update(Request $request, Media $media)
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:200', function ($attribute, $value, $fail) use ($media) {
            if (self::sameName($value, $media->id)) {
                $fail('Another picture is already named "' . trim($value) . '". Please choose a different name.');
            }
        }]]);
        $media->update(['name' => trim($data['name'])]);

        return $request->wantsJson() ? response()->json(['data' => $media->details()]) : back()->with('success', 'Picture renamed.');
    }

    public function destroy(Request $request, Media $media)
    {
        $this->remove($media);

        return $request->wantsJson() ? response()->json(['ok' => true]) : back()->with('success', 'Picture deleted from the Media Library.');
    }

    public function bulkDestroy(Request $request)
    {
        $ids = $request->validate(['ids' => 'required|array', 'ids.*' => 'integer'])['ids'];
        $items = Media::whereIn('id', $ids)->get();
        $items->each(fn (Media $m) => $this->remove($m));

        return back()->with('success', $items->count() . ' ' . ($items->count() === 1 ? 'picture' : 'pictures') . ' deleted from the Media Library.');
    }

    private function remove(Media $media): void
    {
        Storage::disk('public')->delete($media->path);
        $media->delete();
    }
}
