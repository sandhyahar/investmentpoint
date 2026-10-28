<?php

namespace App\Console\Commands;

use App\Models\SubscriptionRequest;
use App\Services\WhatsApp;
use Illuminate\Console\Command;
use RuntimeException;

/**
 * Sends the WhatsApp templates to one number to check the API setup (token, phone number id, template
 * names and languages). Nothing is saved; the API's own error (Secondtick or Meta) is printed when a send fails.
 *   php artisan whatsapp:test 98XXXXXXXX            (login OTP)
 *   php artisan whatsapp:test 98XXXXXXXX --subscription
 *   php artisan whatsapp:test 98XXXXXXXX --picks    (Member Picks "stocks are updated" reminder)
 */
class TestWhatsApp extends Command
{
    protected $signature = 'whatsapp:test {number : WhatsApp number (10 digits or +91...)}
        {--subscription : Send the "subscription active" message instead of an OTP}
        {--picks : Send the "member stocks are updated" reminder instead of an OTP}';

    protected $description = 'Send a test login OTP, subscription message or member picks reminder on WhatsApp';

    public function handle(WhatsApp $whatsapp): int
    {
        if (!$whatsapp->configured()) {
            $this->error(($whatsapp->provider() === 'secondtick' ? 'SECONDTICK_TOKEN and SECONDTICK_VENDOR_UID are' : 'WHATSAPP_TOKEN and WHATSAPP_PHONE_NUMBER_ID are')
                . ' not set in .env (run php artisan config:clear after editing it).');

            return self::FAILURE;
        }

        $number = SubscriptionRequest::normalizePhone($this->argument('number'));

        if ($this->option('picks')) {
            return $this->picks($whatsapp, $number);
        }

        return $this->option('subscription') ? $this->subscription($whatsapp, $number) : $this->otp($whatsapp, $number);
    }

    private function picks(WhatsApp $whatsapp, string $number): int
    {
        if (!$whatsapp->picksConfigured()) {
            $this->error('WHATSAPP_PICKS_TEMPLATE is not set in .env.');

            return self::FAILURE;
        }

        if ($error = $whatsapp->sendPicksReminder($number, 'Test Member', now('Asia/Kolkata'), ['Reliance Industries Ltd', 'HDFC Bank Ltd'])) {
            $this->error('Failed: ' . $error);

            return self::FAILURE;
        }

        $this->info("Member picks reminder sent to {$number} (template " . config('subscription.picks.whatsapp_template') . ').');
        $this->notice($whatsapp);

        return self::SUCCESS;
    }

    private function otp(WhatsApp $whatsapp, string $number): int
    {
        if (!$whatsapp->otpConfigured()) {
            $this->error('WHATSAPP_OTP_TEMPLATE is not set in .env.');

            return self::FAILURE;
        }

        $code = (string) random_int(100000, 999999);
        try {
            $whatsapp->sendOtp($number, $code);
        } catch (RuntimeException $e) {
            $this->error('Failed: ' . ($e->getPrevious()?->getMessage() ?? $e->getMessage()));

            return self::FAILURE;
        }

        $this->info("OTP {$code} sent to {$number} (template " . config('subscription.otp.whatsapp_template') . ').');
        $this->notice($whatsapp);

        return self::SUCCESS;
    }

    private function subscription(WhatsApp $whatsapp, string $number): int
    {
        // Same plan and period as a real subscriber gets (config/subscription.php: Monthly Plan, 1 month)
        $plan = config('subscription.plan');
        $test = new SubscriptionRequest([
            'name' => 'Test Member',
            'whatsapp' => $number,
            'plan' => $plan['name'],
            'price' => $plan['price'],
            'starts_at' => now(),
            'expires_at' => now()->addMonthsNoOverflow($plan['months']),
        ]);

        if (!$whatsapp->sendSubscriptionConfirmation($test)) {
            $this->error('Failed: ' . $test->whatsapp_error);

            return self::FAILURE;
        }

        $this->info("Subscription message sent to {$number} (template " . (config('subscription.whatsapp.template') ?: 'none - plain text') . ').');
        $this->notice($whatsapp);

        return self::SUCCESS;
    }

    /** "Sent" only means Secondtick accepted it; show its note (e.g. held by Meta for quality assessment). */
    private function notice(WhatsApp $whatsapp): void
    {
        if (!$whatsapp->lastNotice) {
            return;
        }

        $this->line('Secondtick: ' . $whatsapp->lastNotice);
        if (str_contains(strtolower($whatsapp->lastNotice), 'held')) {
            $this->warn('Meta is holding this message and may not deliver it. Fix on Meta: payment method, business verification, display name.');
        }
    }
}
