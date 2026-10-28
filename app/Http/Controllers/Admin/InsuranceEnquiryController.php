<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\InsuranceEnquiry;
use Illuminate\Http\Request;

/**
 * Admin -> Services -> Insurance Enquiries: what readers send from the Insurance pages, newest first. "Products" =
 * the plans they ticked, "Join As Advisor" = they want to join as an advisor. New ones are marked once the list has shown them.
 */
class InsuranceEnquiryController extends Controller
{
    public function index(Request $request)
    {
        $kind = array_key_exists((string) $request->query('kind'), InsuranceEnquiry::KINDS) ? $request->query('kind') : null;
        $enquiries = InsuranceEnquiry::when($kind, fn ($q) => $q->where('kind', $kind))->latest()->paginate(25)->withQueryString();
        $counts = InsuranceEnquiry::selectRaw('kind, COUNT(*) as total')->groupBy('kind')->pluck('total', 'kind');

        $view = view('admin.insurance.enquiries', compact('enquiries', 'kind', 'counts'))->render();
        // Shown now: no longer "new" (this page still highlights them)
        InsuranceEnquiry::whereIn('id', $enquiries->pluck('id'))->whereNull('read_at')->update(['read_at' => now()]);

        return $view;
    }

    public function destroy(InsuranceEnquiry $insuranceEnquiry)
    {
        $insuranceEnquiry->delete();

        return redirect()->back()->with('success', 'Enquiry from ' . $insuranceEnquiry->name . ' deleted.');
    }

    public function bulkDestroy(Request $request)
    {
        $ids = $request->validate(['ids' => 'required|array', 'ids.*' => 'integer'])['ids'];
        $count = InsuranceEnquiry::whereIn('id', $ids)->delete();

        return redirect()->back()->with('success', $count . ' enquiry(ies) deleted.');
    }
}
