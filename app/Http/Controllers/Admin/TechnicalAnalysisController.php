<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PageView;
use App\Models\TechnicalAnalysis;
use App\Services\NewsWriter;
use App\Support\ShortLink;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class TechnicalAnalysisController extends Controller
{
    use Concerns\SendsToPress;

    public function index()
    {
        $analyses = TechnicalAnalysis::newest()->paginate(20);
        $views = PageView::viewsPerArticle('analysis', $analyses->pluck('id'));
        return view('admin.technical-analysis.index', compact('analyses', 'views'));
    }

    public function create()
    {
        return view('admin.technical-analysis.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'share_name' => ShortLink::rule(),
            'published_on' => 'nullable|date_format:Y-m-d',
            'cmp' => TechnicalAnalysis::LEVEL_RULE,
            'support' => TechnicalAnalysis::LEVEL_RULE,
            'resistance' => TechnicalAnalysis::LEVEL_RULE,
            'description' => 'nullable|string',
            'content' => 'nullable|string',
            'image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'status' => 'required|in:active,inactive',
        ]);
        $validated['description'] = \App\Support\Html::summary($validated['description'] ?? null, $validated['content'] ?? null);
        $validated['share_name'] = ShortLink::normalize($validated['share_name'] ?? null);
        $press = $this->validatePress($request);

        if ($request->hasFile('image')) {
            $validated['image'] = \App\Support\ImageOptimizer::store($request->file('image'), 'technical-analysis');
        }

        $analysis = TechnicalAnalysis::create($validated);

        return redirect()->route('admin.technical-analysis.index')->with('success', 'Technical analysis created successfully.' . $this->sendToPress($analysis, 'analysis', $press));
    }

    public function edit(TechnicalAnalysis $technicalAnalysis)
    {
        return view('admin.technical-analysis.edit', compact('technicalAnalysis'));
    }

    public function update(Request $request, TechnicalAnalysis $technicalAnalysis)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'share_name' => ShortLink::rule(),
            'published_on' => 'nullable|date_format:Y-m-d',
            'cmp' => TechnicalAnalysis::LEVEL_RULE,
            'support' => TechnicalAnalysis::LEVEL_RULE,
            'resistance' => TechnicalAnalysis::LEVEL_RULE,
            'description' => 'nullable|string',
            'content' => 'nullable|string',
            'image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'status' => 'required|in:active,inactive',
        ]);
        // No summary box on the form: taken from the start of the article; with no article, the one already saved stays
        $validated['description'] = \App\Support\Html::summary($validated['description'] ?? null, $validated['content'] ?? null) ?: (string) $technicalAnalysis->description;
        $validated['share_name'] = ShortLink::normalize($validated['share_name'] ?? null);
        $press = $this->validatePress($request);

        if ($request->hasFile('image')) {
            if ($technicalAnalysis->image) {
                Storage::disk('public')->delete($technicalAnalysis->image);
            }
            $validated['image'] = \App\Support\ImageOptimizer::store($request->file('image'), 'technical-analysis');
        }

        $technicalAnalysis->update($validated);

        return redirect()->route('admin.technical-analysis.index')->with('success', 'Technical analysis updated successfully.' . $this->sendToPress($technicalAnalysis, 'analysis', $press));
    }

    /** "Write with AI": an SEO-friendly 200-250 word analysis from the title + levels, returned as JSON for the form. */
    public function write(Request $request, NewsWriter $writer)
    {
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'support' => 'nullable|string|max:50',
            'resistance' => 'nullable|string|max:50',
            'cmp' => 'nullable|string|max:50',
            'published_on' => 'nullable|date',
        ]);

        // Shares the daily allowance with the news "Write with AI"
        $key = 'admin_ai_writer_' . now()->format('Y-m-d');
        $used = (int) cache($key, 0);
        $limit = (int) config('news.writer.daily_limit', 60);
        if ($used >= $limit) {
            return response()->json(['message' => "Daily AI writing limit reached ({$limit} articles). Please try again tomorrow."], 429);
        }

        try {
            $draft = $writer->analysis(trim($data['title']), $data['support'] ?? null, $data['resistance'] ?? null, $data['cmp'] ?? null, $data['published_on'] ?? null);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 502);
        }

        cache()->put($key, $used + 1, now()->endOfDay());

        return response()->json($draft);
    }

    public function destroy(TechnicalAnalysis $technicalAnalysis)
    {
        if ($technicalAnalysis->image) {
            Storage::disk('public')->delete($technicalAnalysis->image);
        }

        $technicalAnalysis->delete();

        return redirect()->route('admin.technical-analysis.index')->with('success', 'Technical analysis deleted successfully.');
    }

    public function bulkDestroy(Request $request)
    {
        $validated = $request->validate(['ids' => 'required|array', 'ids.*' => 'integer']);
        $items = TechnicalAnalysis::whereIn('id', $validated['ids'])->get();
        foreach ($items as $analysis) {
            if ($analysis->image) {
                Storage::disk('public')->delete($analysis->image);
            }
            $analysis->delete();
        }
        return redirect()->route('admin.technical-analysis.index')->with('success', $items->count() . ' analysis item(s) deleted successfully.');
    }
}
