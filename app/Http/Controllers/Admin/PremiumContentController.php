<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PremiumContent;
use Illuminate\Http\Request;

class PremiumContentController extends Controller
{
    public function index()
    {
        $premiumContents = PremiumContent::latest()->paginate(20);
        return view('admin.premium.index', compact('premiumContents'));
    }

    public function create()
    {
        return view('admin.premium.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate($this->rules());
        $validated['benefits'] = $this->benefits($request->input('benefits'));
        PremiumContent::create($this->withButton($validated));
        return redirect()->route('admin.premium.index')->with('success', 'Premium content created successfully.');
    }

    public function edit(PremiumContent $premium)
    {
        return view('admin.premium.edit', compact('premium'));
    }

    public function update(Request $request, PremiumContent $premium)
    {
        $validated = $request->validate($this->rules());
        $validated['benefits'] = $this->benefits($request->input('benefits'));
        $premium->update($this->withButton($validated));
        return redirect()->route('admin.premium.index')->with('success', 'Premium content updated successfully.');
    }

    public function destroy(PremiumContent $premium)
    {
        $premium->delete();
        return redirect()->route('admin.premium.index')->with('success', 'Premium content deleted successfully.');
    }

    public function bulkDestroy(Request $request)
    {
        $validated = $request->validate(['ids' => 'required|array', 'ids.*' => 'integer']);
        $deleted = PremiumContent::whereIn('id', $validated['ids'])->delete();
        return redirect()->route('admin.premium.index')->with('success', $deleted . ' premium item(s) deleted successfully.');
    }

    private function rules(): array
    {
        return [
            'badge' => 'required|string|max:255',
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'price' => 'required|numeric|min:0',
            'billing_period' => 'required|string|max:20',
            'benefits' => 'nullable|string',
            'status' => 'required|in:active,inactive',
        ];
    }

    /** The homepage card's button is always "Subscription" to the subscription page. */
    private function withButton(array $validated): array
    {
        return $validated + ['button_text' => 'Subscription', 'button_url' => route('subscription')];
    }

    private function benefits(?string $benefits): array
    {
        return collect(preg_split('/\\r?\\n/', $benefits ?? ''))
            ->map(fn ($benefit) => trim($benefit))
            ->filter()
            ->values()
            ->all();
    }
}
