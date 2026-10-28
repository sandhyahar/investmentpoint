<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MediaArticle;
use App\Models\NewspaperLogo;
use App\Support\ImageOptimizer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Admin -> Media Articles -> Newspaper Logos: everything about the home page "Trusted by" band.
 * Add a newspaper (even one without Media Articles here), edit its logo, display name and position, hide or
 * show it, delete it. A paper without its own logo shows the masthead of its newest clipping.
 */
class NewspaperLogoController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->query('q', ''));
        $show = in_array($request->query('show'), ['trusted', 'hidden', 'all'], true) ? $request->query('show') : 'trusted';

        $all = collect(MediaArticle::allNewspapers());
        $onHome = collect(MediaArticle::trustedBy())->pluck('name')->flip();
        $all = $all->map(fn ($p) => $p + ['on_home' => $onHome->has($p['name'])]);
        $counts = ['trusted' => $onHome->count(), 'hidden' => $all->where('hidden', true)->count(), 'all' => $all->count()];

        $papers = $all
            ->when($show === 'trusted', fn ($c) => $c->where('on_home', true))
            ->when($show === 'hidden', fn ($c) => $c->where('hidden', true))
            ->when($search !== '', fn ($c) => $c->filter(fn ($p) => str_contains($p['name'], mb_strtoupper($search)) || stripos($p['label'], $search) !== false))
            ->values();

        return view('admin.media-articles.logos', compact('papers', 'search', 'show', 'counts'));
    }

    /** Add a newspaper by hand (or set up one that has articles): name, logo, display name, position */
    public function store(Request $request)
    {
        $data = $this->validated($request);
        $name = MediaArticle::normalizePublication($data['name']);
        if ($name === '') {
            return back()->withInput()->withErrors(['name' => 'Enter the newspaper name.']);
        }
        // A paper with no Media Articles here has no masthead to show: it needs its logo
        $found = collect(MediaArticle::allNewspapers())->firstWhere('name', $name);
        if (!$request->hasFile('logo') && !($found && !$found['manual']) && !NewspaperLogo::where('name', $name)->whereNotNull('logo')->exists()) {
            return back()->withInput()->withErrors(['logo' => 'Choose the newspaper\'s logo (it has no Media Articles here to take a masthead from).']);
        }

        $paper = NewspaperLogo::firstOrNew(['name' => $name]);
        $this->fill($paper, $data, $request);
        $paper->is_hidden = false;
        $paper->save();
        $this->saved($request, 'added', $paper);

        return redirect()->route('admin.media-articles.logos', ['show' => 'all', 'q' => $name])->with('success', $name . ' is in "Trusted by" on the home page.');
    }

    /** Edit one newspaper: logo (or back to the masthead), display name, position, shown / hidden */
    public function update(Request $request)
    {
        $data = $this->validated($request);
        $name = MediaArticle::normalizePublication($data['name']);
        $paper = NewspaperLogo::firstOrNew(['name' => $name]);
        $this->fill($paper, $data, $request);
        if ($request->has('is_hidden')) {
            $paper->is_hidden = $request->boolean('is_hidden');
        }
        $paper->save();
        $this->saved($request, 'changed', $paper);

        return back()->with('success', $name . ' saved.');
    }

    /**
     * Delete from "Trusted by". A paper added by hand is removed completely; a paper with Media Articles is
     * hidden (its articles stay; "Show again" brings it back) and its uploaded logo is removed.
     */
    public function destroy(Request $request)
    {
        $name = MediaArticle::normalizePublication((string) $request->input('name'));
        $found = collect(MediaArticle::allNewspapers())->firstWhere('name', $name);
        $hasArticles = $found && !$found['manual'];
        $paper = NewspaperLogo::firstOrNew(['name' => $name]);

        if ($hasArticles) {
            $paper->deleteLogoFile();
            $paper->forceFill(['logo' => null, 'is_hidden' => true])->save();
            $message = $name . ' is removed from "Trusted by" (its Media Articles stay). Find it under Hidden to show it again.';
        } else {
            $paper->exists && $paper->delete();
            $message = $name . ' is deleted.';
        }
        MediaArticle::forgetPublicationCaches();
        Log::warning('Trusted by newspaper deleted', ['name' => $name, 'by' => $request->user()->username]);

        return back()->with('success', $message);
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => 'required|string|max:120',
            'display_name' => 'nullable|string|max:120',
            'sort_order' => 'nullable|integer|min:1|max:999',
            'link' => 'nullable|url:https,http|max:500',
            'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:4096'],
            'remove_logo' => 'nullable|boolean',
            'is_hidden' => 'nullable|boolean',
        ], [
            'logo.mimes' => 'Use a PNG (best: transparent background), JPG or WEBP picture.',
            'sort_order.min' => 'Position 1 is the first logo.',
            'link.url' => 'Paste the full link, starting with https://',
        ]);
    }

    private function fill(NewspaperLogo $paper, array $data, Request $request): void
    {
        $name = MediaArticle::normalizePublication($data['name']);
        if (array_key_exists('display_name', $data)) {
            $label = trim((string) $data['display_name']);
            $paper->display_name = $label !== '' && $label !== $name ? $label : null; // "Sandesh" for SANDESH is kept
        }
        if (array_key_exists('sort_order', $data)) {
            $paper->sort_order = $data['sort_order'] ?: null;
        }
        if (array_key_exists('link', $data)) {
            $paper->link = trim((string) $data['link']) ?: null; // empty = the paper's Media Articles again
        }
        if ($request->hasFile('logo')) {
            // Logos stay sharp and keep a transparent background (PNG stays PNG)
            $file = $request->file('logo');
            $path = ImageOptimizer::put((string) file_get_contents($file->getRealPath()), $file->extension(), 'newspaper-logos/' . Str::slug($name) . '-' . Str::random(6), 800, 90);
            $paper->deleteLogoFile();
            $paper->logo = $path;
        } elseif ($request->boolean('remove_logo')) {
            $paper->deleteLogoFile();
            $paper->logo = null;
        }
    }

    private function saved(Request $request, string $what, NewspaperLogo $paper): void
    {
        MediaArticle::forgetPublicationCaches();
        Log::warning('Trusted by newspaper ' . $what, ['name' => $paper->name, 'by' => $request->user()->username]);
    }
}
