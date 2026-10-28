<?php

namespace App\Http\Controllers;

use App\Mail\InsuranceEnquiryMail;
use App\Models\InsuranceEnquiry;
use App\Models\InsuranceProduct;
use App\Support\IndiaPincodes;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Throwable;

/**
 * Services menu in the header: Stock Market (the Subscription page) and Insurance -> the Life Insurance page (General
 * Insurance once the admin shows it). An Insurance page has two tabs: "Products" (tick the plan categories you want) and
 * "Join As Advisor"; both then ask name, mobile, age and the address with city and PIN code. What readers send is listed
 * in Admin -> Services -> Insurance Enquiries.
 */
class ServicesController extends Controller
{
    public function insurance(string $insurance)
    {
        abort_unless(in_array($insurance, InsuranceProduct::shown(), true), 404);

        return view('services.insurance', [
            'insurance' => $insurance,
            'label' => InsuranceProduct::INSURANCES[$insurance],
            'products' => InsuranceProduct::for($insurance)->where('is_active', true)->get(),
        ]);
    }

    public function enquire(Request $request, string $insurance): RedirectResponse
    {
        abort_unless(in_array($insurance, InsuranceProduct::shown(), true), 404);
        $kind = $request->input('kind') === 'adviser' ? 'adviser' : 'product';
        $back = redirect()->to(route('services.insurance', $insurance) . '#' . $kind);

        // Honeypot: a field hidden from people; bots that fill every input get silently ignored
        if ($request->filled('website')) {
            return $back->with('enquiry_sent', $kind);
        }

        $productIds = InsuranceProduct::for($insurance)->where('is_active', true)->pluck('id')->all();
        $data = $request->validateWithBag($kind, [
            'name' => 'required|string|max:100',
            // An Indian mobile number: 10 digits starting 6-9, with or without +91 / spaces / dashes
            'contact' => ['required', 'string', 'max:20', 'regex:/^(\+?91[\s-]?)?[6-9]\d{4}[\s-]?\d{5}$/'],
            'age' => 'required|integer|min:18|max:80',
            'address' => 'required|string|min:5|max:400',
            // City and PIN code from India Post's list (the state comes with the city), as for the Weekly News Paper
            'state' => ['required', 'string', Rule::in(array_keys(IndiaPincodes::all()))],
            'city' => 'required|string|max:60',
            'pincode' => ['required', 'digits:6', function ($attribute, $value, $fail) use ($request) {
                if (!IndiaPincodes::matches($request->input('state'), $request->input('city'), $value)) {
                    $fail('Please choose the PIN code from the list for your city.');
                }
            }],
            'products' => [$kind === 'product' ? 'required' : 'nullable', 'array'],
            'products.*' => ['integer', Rule::in($productIds)],
        ], [
            'name.required' => 'Please enter your name.',
            'contact.required' => 'Please enter your mobile number.',
            'contact.regex' => 'Please enter a 10-digit mobile number.',
            'age.required' => 'Please enter your age.',
            'age.integer' => 'Please enter your age in years.',
            'age.min' => 'You must be at least 18 years old.',
            'age.max' => 'Please enter an age up to 80.',
            'address.required' => 'Please enter your address.',
            'address.min' => 'Please enter your full address.',
            'state.required' => 'Please choose your city from the list.',
            'state.in' => 'Please choose your city from the list.',
            'city.required' => 'Please choose your city.',
            'pincode.required' => 'Please choose your PIN code.',
            'pincode.digits' => 'Please choose the PIN code from the list for your city.',
            'products.required' => 'Please tick at least one plan you are interested in.',
            'products.*.in' => 'Please tick the plans from the list.',
        ]);

        $enquiry = InsuranceEnquiry::create([
            'insurance' => $insurance,
            'kind' => $kind,
            'name' => $data['name'],
            'contact' => substr(preg_replace('/\D/', '', $data['contact']), -10),
            'age' => (int) $data['age'],
            'address' => trim($data['address']),
            'city' => $data['city'],
            'state' => $data['state'],
            'pincode' => $data['pincode'],
            'products' => $kind === 'product'
                ? InsuranceProduct::whereIn('id', $data['products'])->orderBy('sort_order')->orderBy('id')->pluck('name')->all()
                : null,
        ]);

        // Their details by email to the insurance contact; the enquiry is saved either way, so a mail problem never stops the form
        if ($to = InsuranceEnquiry::notifyEmail()) {
            try {
                Mail::to($to)->send(new InsuranceEnquiryMail($enquiry));
            } catch (Throwable $e) {
                report($e);
            }
        }

        return $back->with('enquiry_sent', $kind);
    }
}
