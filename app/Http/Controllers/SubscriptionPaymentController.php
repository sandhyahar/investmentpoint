<?php

namespace App\Http\Controllers;

use App\Models\SubscriptionRequest;
use App\Services\Razorpay;
use App\Services\WhatsApp;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Subscription payment: after the reader agrees on the subscription page they land here
 * (secret link), pay with Razorpay (or the demo button while no keys are set), and then
 * see their receipt with the subscription period. A WhatsApp confirmation follows.
 */
class SubscriptionPaymentController extends Controller
{
    public function show(SubscriptionRequest $subscription, Razorpay $razorpay)
    {
        $mode = $razorpay->mode();
        $orderId = null;
        $error = null;

        // Paid: the member login then shows the payment and has this mobile number filled in
        if ($subscription->isPaid() && $subscription->givesMemberAccess()) {
            session(['member_subscription' => $subscription->token]);
        }

        if (!$subscription->isPaid() && in_array($mode, ['test', 'live'], true)) {
            try {
                $orderId = $razorpay->ensureOrder($subscription);
            } catch (Throwable $e) {
                Log::error('Razorpay order could not be created', ['subscription' => $subscription->id, 'error' => $e->getMessage()]);
                $error = 'The payment could not be started right now. Please try again in a minute, or contact us.';
            }
        }

        return view('subscription-payment', [
            'subscription' => $subscription,
            'mode' => $mode,
            'orderId' => $orderId,
            'razorpayKey' => $razorpay->key(),
            'error' => $error,
            'plan' => config('subscription.plan'),
        ]);
    }

    /** Razorpay's popup posts here after a successful payment. */
    public function verify(Request $request, SubscriptionRequest $subscription, Razorpay $razorpay)
    {
        $data = $request->validate([
            'razorpay_payment_id' => 'required|string|max:60',
            'razorpay_order_id' => 'required|string|max:60',
            'razorpay_signature' => 'required|string|max:128',
        ]);

        if (!$subscription->isPaid()) {
            if (!in_array($razorpay->mode(), ['test', 'live'], true)
                || $data['razorpay_order_id'] !== $subscription->razorpay_order_id
                || !$razorpay->verifySignature($data['razorpay_order_id'], $data['razorpay_payment_id'], $data['razorpay_signature'])) {
                Log::warning('Razorpay payment verification failed', ['subscription' => $subscription->id, 'payment' => $data['razorpay_payment_id']]);

                return redirect()->route('subscription.pay', $subscription)
                    ->with('payment_error', 'We could not confirm this payment. If money was deducted, please contact us with payment ID ' . $data['razorpay_payment_id'] . '.');
            }

            $this->activate($subscription, $razorpay->mode(), $data['razorpay_payment_id'], $data['razorpay_signature']);
        }

        return $this->afterPayment($subscription);
    }

    /** Stand-in for Razorpay while no keys are set: nothing is charged. */
    public function demo(SubscriptionRequest $subscription, Razorpay $razorpay)
    {
        abort_unless($razorpay->mode() === 'demo', 404);

        if (!$subscription->isPaid()) {
            $this->activate($subscription, 'demo', 'demo_' . Str::random(14), null);
        }

        return $this->afterPayment($subscription);
    }

    /**
     * Paid: go straight to the member login with the subscription's WhatsApp number filled in; one
     * WhatsApp OTP to that number opens the member page. A member who is already logged in with the
     * same number goes straight to the member page. The receipt stays at its secret link.
     */
    private function afterPayment(SubscriptionRequest $subscription)
    {
        // A plan without the member page (the printed paper): show the receipt
        if (!$subscription->givesMemberAccess()) {
            return redirect()->route('subscription.pay', $subscription);
        }

        \App\Support\MemberLogin::rememberSubscription($subscription);
        $user = auth('member')->user();

        if ($user && SubscriptionRequest::normalizePhone((string) $user->phone) === $subscription->whatsapp_number) {
            SubscriptionRequest::attachPhoneTo($user);

            return redirect()->route('member.dashboard')->with('success', 'Payment successful! Your subscription is active till ' . SubscriptionRequest::day($subscription->expires_at) . '.');
        }

        session(['member_subscription' => $subscription->token]);

        return redirect()->route('member.login');
    }

    private function activate(SubscriptionRequest $subscription, string $mode, string $paymentId, ?string $signature): void
    {
        if (!$subscription->markPaid($mode, $paymentId, $signature)) {
            return; // a parallel request already confirmed it (and sends the message)
        }

        // Send the WhatsApp message after the reader already sees their receipt
        $id = $subscription->id;
        dispatch(fn () => app(WhatsApp::class)->sendSubscriptionConfirmation(SubscriptionRequest::findOrFail($id)))->afterResponse();
    }
}
