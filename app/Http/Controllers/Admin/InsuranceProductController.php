<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\InsuranceProduct;
use App\Models\Setting;
use Illuminate\Http\Request;

/**
 * Admin -> Services -> Insurance Products: the plan categories readers tick on the Life / General Insurance page
 * (add, rename, reorder, hide, delete), and whether General Insurance is in the website's menu yet.
 */
class InsuranceProductController extends Controller
{
    public function index(Request $request)
    {
        $insurance = $this->insurance($request);

        return view('admin.insurance.products', [
            'insurance' => $insurance,
            'products' => InsuranceProduct::for($insurance)->get(),
            'generalShown' => in_array('general', InsuranceProduct::shown(), true),
            'notifyEmail' => \App\Models\InsuranceEnquiry::notifyEmail(),
        ]);
    }

    public function store(Request $request)
    {
        $insurance = $this->insurance($request);
        $data = $this->validated($request);
        InsuranceProduct::create($data + ['insurance' => $insurance, 'sort_order' => $data['sort_order'] ?? (InsuranceProduct::for($insurance)->max('sort_order') + 1)]);

        return $this->back($insurance, 'Plan "' . $data['name'] . '" added.');
    }

    public function update(Request $request, InsuranceProduct $insuranceProduct)
    {
        $insuranceProduct->update($this->validated($request) + ['is_active' => $request->boolean('is_active')]);

        return $this->back($insuranceProduct->insurance, 'Plan "' . $insuranceProduct->name . '" saved.');
    }

    public function destroy(InsuranceProduct $insuranceProduct)
    {
        $insuranceProduct->delete(); // enquiries keep the plan names they were sent with

        return $this->back($insuranceProduct->insurance, 'Plan "' . $insuranceProduct->name . '" deleted.');
    }

    /** General Insurance in the Services menu (on / off) */
    public function settings(Request $request)
    {
        $request->validate(['notify_email' => 'nullable|email|max:150'], ['notify_email.email' => 'Please enter a valid email address.']);
        Setting::set('insurance_general_enabled', $request->boolean('general_enabled') ? '1' : '');
        if ($request->has('notify_email')) {
            Setting::set('insurance_notify_email', trim((string) $request->input('notify_email')));
        }

        return $this->back($this->insurance($request), 'Insurance settings saved.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => 'required|string|max:120',
            'description' => 'nullable|string|max:300',
            'sort_order' => 'nullable|integer|min:0|max:999',
        ], ['name.required' => 'Please enter the plan name.']);
    }

    private function insurance(Request $request): string
    {
        $insurance = (string) $request->input('insurance', 'life');

        return array_key_exists($insurance, InsuranceProduct::INSURANCES) ? $insurance : 'life';
    }

    private function back(string $insurance, string $message)
    {
        return redirect()->route('admin.insurance-products.index', ['insurance' => $insurance])->with('success', $message);
    }
}
