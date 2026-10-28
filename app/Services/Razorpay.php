<?php

namespace App\Services;

use App\Models\SubscriptionRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Razorpay Standard Checkout for the subscription page (REST API, no SDK):
 * create an order on the server, the reader pays in Razorpay's popup, then the payment
 * signature is checked here before the subscription is marked paid.
 */
class Razorpay
{
    /** demo (no keys, demo button) | test (rzp_test_ keys) | live (rzp_live_ keys) | off (no keys, demo disabled) */
    public function mode(): string
    {
        $key = (string) config('services.razorpay.key');
        if ($key !== '' && config('services.razorpay.secret')) {
            return str_starts_with($key, 'rzp_live_') ? 'live' : 'test';
        }

        return config('subscription.demo_payments') ? 'demo' : 'off';
    }

    public function key(): string
    {
        return (string) config('services.razorpay.key');
    }

    /** Razorpay order for this subscription (amount in paise). Reused if one was already made. */
    public function ensureOrder(SubscriptionRequest $subscription): string
    {
        if ($subscription->razorpay_order_id) {
            return $subscription->razorpay_order_id;
        }

        $response = Http::withBasicAuth($this->key(), (string) config('services.razorpay.secret'))
            ->acceptJson()->connectTimeout(5)->timeout(15)
            ->post('https://api.razorpay.com/v1/orders', [
                'amount' => $subscription->price * 100,
                'currency' => 'INR',
                'receipt' => 'sub_' . $subscription->id,
                'notes' => ['name' => $subscription->name, 'phone' => $subscription->phone, 'plan' => $subscription->plan],
            ]);

        if (!$response->successful() || !$response->json('id')) {
            throw new RuntimeException('Razorpay order failed: ' . ($response->json('error.description') ?? 'HTTP ' . $response->status()));
        }

        $subscription->update(['razorpay_order_id' => $response->json('id')]);

        return $response->json('id');
    }

    /** The signature Razorpay's popup returns proves the payment is real and for this order. */
    public function verifySignature(string $orderId, string $paymentId, string $signature): bool
    {
        $expected = hash_hmac('sha256', $orderId . '|' . $paymentId, (string) config('services.razorpay.secret'));

        return $signature !== '' && hash_equals($expected, $signature);
    }
}
