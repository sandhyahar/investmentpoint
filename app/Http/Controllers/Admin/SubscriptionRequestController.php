<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SubscriptionRequest;
use App\Services\Razorpay;
use App\Services\WhatsApp;
use Illuminate\Http\Request;

/** Admin -> Subscriptions: subscribers with their "I agree" record, payment, paid period and WhatsApp message. */
class SubscriptionRequestController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->query('q', ''));
        $status = (string) $request->query('status', '');
        $payment = (string) $request->query('payment', '');

        $subscriptions = SubscriptionRequest::query()
            ->when($search !== '', fn ($q) => $q->where(fn ($q) => $q
                ->where('name', 'like', '%' . addcslashes($search, '%_\\') . '%')
                ->orWhere('phone', 'like', '%' . addcslashes($search, '%_\\') . '%')
                ->orWhere('whatsapp', 'like', '%' . addcslashes($search, '%_\\') . '%')
                ->orWhere('email', 'like', '%' . addcslashes($search, '%_\\') . '%')
                ->orWhere('razorpay_payment_id', $search)))
            ->when(isset(SubscriptionRequest::STATUSES[$status]), fn ($q) => $q->where('status', $status))
            ->when($payment === 'paid', fn ($q) => $q->where('payment_status', 'paid'))
            ->when($payment === 'pending', fn ($q) => $q->where('payment_status', '!=', 'paid'))
            ->when($payment === 'active', fn ($q) => $q->where('payment_status', 'paid')->where('expires_at', '>', now()))
            ->when($payment === 'expired', fn ($q) => $q->where('payment_status', 'paid')->where('expires_at', '<=', now()))
            ->latest()
            ->paginate(20)->withQueryString();

        return view('admin.subscriptions.index', compact('subscriptions', 'search', 'status', 'payment') + [
            'paymentMode' => app(Razorpay::class)->mode(),
            'whatsappReady' => app(WhatsApp::class)->configured(),
        ]);
    }

    /** Send the "subscription confirmed" WhatsApp message again (paid subscriptions only). */
    public function resendWhatsapp(SubscriptionRequest $subscription, WhatsApp $whatsapp)
    {
        abort_unless($subscription->isPaid(), 422);

        return $whatsapp->sendSubscriptionConfirmation($subscription)
            ? back()->with('success', 'WhatsApp message sent to ' . $subscription->name . '.')
            : back()->with('error', 'WhatsApp message not sent: ' . ($subscription->whatsapp_error ?: 'WhatsApp API is not set up; use "Send on WhatsApp" instead.'));
    }

    public function update(Request $request, SubscriptionRequest $subscription)
    {
        $data = $request->validate(['status' => 'required|in:' . implode(',', array_keys(SubscriptionRequest::STATUSES))]);
        $subscription->update($data);

        return back()->with('success', $subscription->name . ' marked as ' . SubscriptionRequest::STATUSES[$data['status']] . '.');
    }

    public function destroy(SubscriptionRequest $subscription)
    {
        $subscription->delete();

        return back()->with('success', 'Subscription request deleted.');
    }

    public function bulkDestroy(Request $request)
    {
        $validated = $request->validate(['ids' => 'required|array', 'ids.*' => 'integer']);
        $count = SubscriptionRequest::whereIn('id', $validated['ids'])->delete();

        return back()->with('success', $count . ' subscription request(s) deleted.');
    }
}
