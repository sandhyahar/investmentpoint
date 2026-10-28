<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DivyaBhaskarArticle;
use Illuminate\Http\Request;

class DivyaBhaskarController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->query('q', ''));
        $articles = DivyaBhaskarArticle::query()
            ->when($search !== '', fn ($q) => $q->where('headline', 'like', '%' . addcslashes($search, '%_\\') . '%'))
            ->orderByDesc('published_on')->orderByDesc('id')
            ->paginate(20)->withQueryString();

        return view('admin.divya-bhaskar.index', compact('articles', 'search'));
    }

    public function create()
    {
        return view('admin.divya-bhaskar.create');
    }

    public function store(Request $request)
    {
        DivyaBhaskarArticle::create($this->validated($request));

        return redirect()->route('admin.divya-bhaskar.index')->with('success', 'Divya Bhaskar column added successfully.');
    }

    public function edit(DivyaBhaskarArticle $divyaBhaskar)
    {
        return view('admin.divya-bhaskar.edit', ['article' => $divyaBhaskar]);
    }

    public function update(Request $request, DivyaBhaskarArticle $divyaBhaskar)
    {
        $divyaBhaskar->update($this->validated($request, $divyaBhaskar));

        return redirect()->route('admin.divya-bhaskar.index')->with('success', 'Divya Bhaskar column updated successfully.');
    }

    public function destroy(DivyaBhaskarArticle $divyaBhaskar)
    {
        $divyaBhaskar->delete();

        return redirect()->route('admin.divya-bhaskar.index')->with('success', 'Divya Bhaskar column deleted successfully.');
    }

    public function bulkDestroy(Request $request)
    {
        $validated = $request->validate(['ids' => 'required|array', 'ids.*' => 'integer']);
        $deleted = DivyaBhaskarArticle::whereIn('id', $validated['ids'])->delete();

        return redirect()->route('admin.divya-bhaskar.index')->with('success', $deleted . ' column(s) deleted successfully.');
    }

    private function validated(Request $request, ?DivyaBhaskarArticle $existing = null): array
    {
        return $request->validate([
            'headline' => 'required|string|max:500',
            // Same link twice would show the column twice on the website
            'link' => 'required|url|max:500|unique:divya_bhaskar_articles,link' . ($existing ? ',' . $existing->id : ''),
            'published_on' => 'required|date',
            'status' => 'required|in:active,inactive',
        ], [
            'link.unique' => 'This Divya Bhaskar link has already been added.',
        ]);
    }
}
