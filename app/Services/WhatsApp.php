<?php

namespace App\Services;

use App\Models\SubscriptionRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * WhatsApp messages: the confirmation to a reader after they pay for a subscription, member login
 * OTPs, and the "member stocks are updated" reminder (Admin -> Member Picks). Sent through Secondtick (app.secondtick.com) or the WhatsApp Cloud API directly (Meta), see
 * config/subscription.php. The confirmation result is saved on the subscription (sent / failed /
 * not_configured) for Admin -> Subscriptions.
 */
class WhatsApp
{
    /** Secondtick's note on the last accepted message, e.g. "Message held for quality assessment ..." */
    public ?string $lastNotice = null;

    public function provider(): string
    {
        return config('subscription.whatsapp.provider') === 'secondtick' ? 'secondtick' : 'meta';
    }

    public function configured(): bool
    {
        if ($this->provider() === 'secondtick') {
            return config('subscription.whatsapp.secondtick.token') && config('subscription.whatsapp.secondtick.vendor_uid');
        }

        return config('subscription.whatsapp.token') && config('subscription.whatsapp.phone_number_id');
    }

    public function sendSubscriptionConfirmation(SubscriptionRequest $subscription): bool
    {
        if (!$this->configured()) {
            $this->record($subscription, ['whatsapp_status' => 'not_configured', 'whatsapp_error' => null]);

            return false;
        }

        $cfg = config('subscription.whatsapp');
        $error = $cfg['template']
            ? $this->sendTemplate($subscription->whatsapp_number, $cfg['template'], $cfg['template_language'], [
                $subscription->name,
                $subscription->plan,
                SubscriptionRequest::day($subscription->starts_at),
                SubscriptionRequest::day($subscription->expires_at),
            ])
            : $this->sendText($subscription->whatsapp_number, $subscription->whatsappMessage());

        if (!$error) {
            $this->record($subscription, ['whatsapp_status' => 'sent', 'whatsapp_sent_at' => now(), 'whatsapp_error' => null]);

            return true;
        }

        Log::warning('WhatsApp subscription message failed', ['subscription' => $subscription->id, 'error' => $error]);
        $this->record($subscription, ['whatsapp_status' => 'failed', 'whatsapp_error' => Str::limit($error, 250, '')]);

        return false;
    }

    /** Save the send result (an unsaved subscription, e.g. from `whatsapp:test`, only keeps it in memory). */
    private function record(SubscriptionRequest $subscription, array $result): void
    {
        $subscription->fill($result);
        if ($subscription->exists) {
            $subscription->save();
        }
    }

    /** Can login OTPs be sent? (API keys + an approved Authentication template) */
    public function otpConfigured(): bool
    {
        return $this->configured() && config('subscription.otp.whatsapp_template');
    }

    /**
     * Send a login code through the approved "Authentication" template: the code fills the body ({{1}})
     * and the "Copy code" button.
     *
     * @throws RuntimeException with a message for the visitor
     */
    public function sendOtp(string $number, string $code): void
    {
        $otp = config('subscription.otp');
        $error = $this->sendTemplate($number, $otp['whatsapp_template'], $otp['whatsapp_template_language'], [$code], $code);

        if ($error) {
            Log::warning('WhatsApp OTP failed', ['error' => $error]);
            // The API's own error rides along as the previous exception (shown by `whatsapp:test`, never to visitors)
            throw new RuntimeException('The OTP could not be sent on WhatsApp right now. Please check the number has WhatsApp, or try again in a minute.', 0, new RuntimeException($error));
        }
    }

    /** Can the "member stocks are updated" reminder be sent? (API keys + its approved template) */
    public function picksConfigured(): bool
    {
        return $this->configured() && config('subscription.picks.whatsapp_template');
    }

    /**
     * One "member stocks of {date} are updated" reminder: {{1}} name, {{2}} date, {{3}} the stocks added or
     * edited ("Reliance Industries Ltd, HDFC Bank Ltd"; names only, never the levels).
     * Returns the error, or null when sent.
     */
    public function sendPicksReminder(string $number, string $name, Carbon $date, array $stocks): ?string
    {
        $cfg = config('subscription.picks');
        // Template values can't hold line breaks or runs of spaces (Meta rejects them)
        $list = Str::limit(preg_replace('/\s+/u', ' ', implode(', ', array_filter(array_map('trim', $stocks)))), 300);

        return $this->sendTemplate($number, $cfg['whatsapp_template'], $cfg['whatsapp_template_language'], [$name, $date->format('d M Y'), $list]);
    }

    /** Is there a template of its own for "this stock achieved its level"? */
    public function achievedConfigured(): bool
    {
        return $this->configured() && config('subscription.picks.achieved_template');
    }

    /**
     * One "this stock achieved its level" message: {{1}} when it was updated ("13 Oct 2026, 1:05 PM", the top
     * line), {{2}} name, {{3}} achieved price ("₹2,265"), {{4}} stock name.
     * Returns the error, or null when sent.
     */
    public function sendAchievedReminder(string $number, string $name, string $stock, string $price, string $updated): ?string
    {
        $cfg = config('subscription.picks');
        // Template values can't hold line breaks or runs of spaces (Meta rejects them)
        $value = fn (string $v) => Str::limit(trim(preg_replace('/\s+/u', ' ', $v)), 120);

        return $this->sendTemplate($number, $cfg['achieved_template'], $cfg['achieved_template_language'], [$updated, $name, $value($price), $value($stock)]);
    }

    /**
     * The reminder to every running subscriber, once per WhatsApp number. With $achieved (the stock whose
     * achieved price was filled in: ['stock' => 'Glenmark Pharma', 'price' => '₹2,265', 'updated' => '13 Oct 2026, 1:05 PM']) the
     * achieved template is used; when WhatsApp refuses that one (not approved yet, other values), the
     * general template goes out instead.
     * Returns ['sent' => 2, 'failed' => 1, 'error' => the first error or null, 'fallback' => why the achieved template was not used, or null].
     */
    public function remindSubscribers(Carbon $date, array $stocks, ?array $achieved = null): array
    {
        $result = ['sent' => 0, 'failed' => 0, 'error' => null, 'fallback' => null];
        $ownTemplate = $achieved && $this->achievedConfigured();
        $subscribers = SubscriptionRequest::running()->withMemberAccess()->latest('id')->get()
            ->unique(fn (SubscriptionRequest $s) => SubscriptionRequest::normalizePhone($s->whatsapp_number));

        foreach ($subscribers as $subscriber) {
            $error = $ownTemplate
                ? $this->sendAchievedReminder($subscriber->whatsapp_number, $subscriber->name, $achieved['stock'], $achieved['price'], $achieved['updated'])
                : $this->sendPicksReminder($subscriber->whatsapp_number, $subscriber->name, $date, $stocks);
            if ($error && $ownTemplate && $this->picksConfigured()) {
                $result['fallback'] ??= $error;
                $error = $this->sendPicksReminder($subscriber->whatsapp_number, $subscriber->name, $date, $stocks);
            }
            if ($error) {
                Log::warning('WhatsApp member picks reminder failed', ['subscription' => $subscriber->id, 'error' => $error]);
                $result['failed']++;
                $result['error'] ??= $error;
            } else {
                $result['sent']++;
            }
        }

        return $result;
    }

    /**
     * Send an approved template. $fields fill the body ({{1}}, {{2}}, ...); $code fills the
     * "Copy code" button of an Authentication template. Returns the error, or null when sent.
     */
    private function sendTemplate(string $number, string $template, string $language, array $fields, ?string $code = null): ?string
    {
        if ($this->provider() === 'secondtick') {
            $payload = ['template_name' => $template, 'template_language' => $language];
            foreach (array_values($fields) as $i => $value) {
                $payload['field_' . ($i + 1)] = (string) $value;
            }
            if ($code !== null) {
                // Authentication templates: Secondtick fills the OTP button from button_0 / copy_code
                $payload += ['button_0' => $code, 'copy_code' => $code];
            }

            return $this->secondtick('send-template-message', $number, $payload);
        }

        $components = [['type' => 'body', 'parameters' => array_map(fn ($v) => ['type' => 'text', 'text' => (string) $v], $fields)]];
        if ($code !== null) {
            $components[] = ['type' => 'button', 'sub_type' => 'url', 'index' => '0', 'parameters' => [['type' => 'text', 'text' => $code]]];
        }

        return $this->meta($number, ['type' => 'template', 'template' => [
            'name' => $template,
            'language' => ['code' => $language],
            'components' => $components,
        ]]);
    }

    /** Plain text: only delivered if the reader messaged us in the last 24 hours. */
    private function sendText(string $number, string $text): ?string
    {
        return $this->provider() === 'secondtick'
            ? $this->secondtick('send-message', $number, ['message_body' => $text])
            : $this->meta($number, ['type' => 'text', 'text' => ['preview_url' => false, 'body' => $text]]);
    }

    /** POST {base}/{vendor uid}/contact/{endpoint} with the token as Bearer (kept out of URLs and logs). */
    private function secondtick(string $endpoint, string $number, array $payload): ?string
    {
        $cfg = config('subscription.whatsapp.secondtick');
        $payload = ['phone_number' => ltrim($number, '+')] + array_filter(['from_phone_number_id' => $cfg['from_phone_number_id']]) + $payload;

        try {
            $response = Http::withToken($cfg['token'])->acceptJson()->connectTimeout(5)->timeout(20)
                ->post("{$cfg['base_url']}/{$cfg['vendor_uid']}/contact/{$endpoint}", $payload);

            // Secondtick can answer HTTP 200 with {"result": "failed", "message": "..."}
            if ($response->successful() && !in_array($response->json('result'), ['failed', 'error'], true)) {
                $this->lastNotice = is_string($response->json('message')) ? $response->json('message') : null;
                if ($this->lastNotice && str_contains(strtolower($this->lastNotice), 'held')) {
                    // Meta may still drop it: keep a trace for "OTP never arrived" questions
                    Log::info('WhatsApp message held by Meta', ['endpoint' => $endpoint, 'notice' => $this->lastNotice, 'status' => $response->json('data.status')]);
                }

                return null;
            }

            $error = $response->json('message') ?: $response->json('error.message') ?: 'HTTP ' . $response->status();

            return is_string($error) ? $error : json_encode($error);
        } catch (Throwable $e) {
            return $e->getMessage();
        }
    }

    private function meta(string $number, array $message): ?string
    {
        $cfg = config('subscription.whatsapp');

        try {
            $response = Http::withToken($cfg['token'])->acceptJson()->connectTimeout(5)->timeout(15)
                ->post("https://graph.facebook.com/{$cfg['api_version']}/{$cfg['phone_number_id']}/messages",
                    ['messaging_product' => 'whatsapp', 'to' => ltrim($number, '+')] + $message);

            return $response->successful() ? null : ($response->json('error.message') ?? 'HTTP ' . $response->status());
        } catch (Throwable $e) {
            return $e->getMessage();
        }
    }
}
