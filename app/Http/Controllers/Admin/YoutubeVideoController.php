<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\YoutubeVideo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class YoutubeVideoController extends Controller
{
    public function index()
    {
        $videos = YoutubeVideo::latest()->paginate(20);
        return view('admin.youtube.index', compact('videos'));
    }

    public function create()
    {
        return view('admin.youtube.create');
    }

    public function store(Request $request)
    {
        // No thumbnail upload: the video's own YouTube thumbnail is always used
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'video_url' => 'required|url|max:500',
            'published_date' => 'required|date',
        ]);
        $validated = $this->legacyFields($validated);

        YoutubeVideo::create($validated);

        return redirect()->route('admin.youtube.index')->with('success', 'YouTube video posted successfully.');
    }

    public function edit(YoutubeVideo $youtube)
    {
        return view('admin.youtube.edit', compact('youtube'));
    }

    public function update(Request $request, YoutubeVideo $youtube)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'video_url' => 'required|url|max:500',
            'published_date' => 'required|date',
        ]);
        $validated['video_thumbnail'] = $youtube->video_thumbnail; // untouched; YouTube's own thumbnail is shown
        $validated = $this->legacyFields($validated);

        $youtube->update($validated);

        return redirect()->route('admin.youtube.index')->with('success', 'YouTube video updated successfully.');
    }

    public function destroy(YoutubeVideo $youtube)
    {
        if ($youtube->video_thumbnail) {
            Storage::disk('public')->delete($youtube->video_thumbnail);
        }

        $youtube->delete();

        return redirect()->route('admin.youtube.index')->with('success', 'YouTube video deleted successfully.');
    }

    public function bulkDestroy(Request $request)
    {
        $validated = $request->validate(['ids' => 'required|array', 'ids.*' => 'integer']);
        $items = YoutubeVideo::whereIn('id', $validated['ids'])->get();
        foreach ($items as $video) {
            if ($video->video_thumbnail) {
                Storage::disk('public')->delete($video->video_thumbnail);
            }
            $video->delete();
        }
        return redirect()->route('admin.youtube.index')->with('success', $items->count() . ' video(s) deleted successfully.');
    }

    private function legacyFields(array $validated): array
    {
        $validated['youtube_url'] = $validated['video_url'];
        $validated['thumbnail'] = $validated['video_thumbnail'] ?? null;
        $validated['publish_date'] = $validated['published_date'];
        $validated['description'] = $validated['title'];
        $validated['youtube_id'] = YoutubeVideo::extractId($validated['video_url']) ?? '';
        $validated['is_active'] = 1;
        $validated['sort_order'] = 0;

        return $validated;
    }
}
