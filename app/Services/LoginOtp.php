<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

/**
 * One-time login codes for members, sent on WhatsApp to their WhatsApp number (WhatsApp Cloud API),
 * or shown on screen in demo mode while WhatsApp isn't set up (local testing only). Codes are stored
 * hashed in the cache, expire after a few minutes and allow only a few wrong tries.
 */
class LoginOtp
{
    public function __construct(private WhatsApp $whatsapp)
    {
    }

    /** whatsapp | demo | off */
    public function mode(): string
    {
        return match (true) {
            $this->whatsapp->otpConfigured() => 'whatsapp',
            (bool) config('subscription.otp.demo') => 'demo',
            default => 'off',
        };
    }

    /** Seconds until another OTP may be sent to this number (0 = now). */
    public function waitSeconds(string $phone): int
    {
        $sentAt = Cache::get($this->key($phone))['sent_at'] ?? null;

        return $sentAt ? max(0, config('subscription.otp.resend_seconds') - (now()->timestamp - $sentAt)) : 0;
    }

    /**
     * Create and send a new code. Returns the code in demo mode (to show on screen), otherwise null.
     *
     * @throws RuntimeException with a message for the visitor
     */
    public function send(string $phone): ?string
    {
        $c = config('subscription.otp');
        $mode = $this->mode();
        if ($mode === 'off') {
            throw new RuntimeException('WhatsApp login is not available right now. Please contact us.');
        }
        if ($wait = $this->waitSeconds($phone)) {
            throw new RuntimeException("Please wait {$wait} seconds before asking for a new OTP.");
        }

        // A few OTPs per number per hour (stops message flooding)
        $hourKey = 'member-otp-hour:' . $phone;
        Cache::add($hourKey, 0, now()->addHour());
        if (Cache::increment($hourKey) > $c['max_per_hour']) {
            throw new RuntimeException('Too many OTPs were requested for this number. Please try again in an hour.');
        }

        $code = (string) random_int(10 ** ($c['length'] - 1), 10 ** $c['length'] - 1);

        if ($mode === 'whatsapp') {
            $this->whatsapp->sendOtp($phone, $code);
        }

        Cache::put($this->key($phone), [
            'hash' => Hash::make($code),
            'sent_at' => now()->timestamp,
            'attempts' => 0,
        ], now()->addMinutes($c['valid_minutes']));

        return $mode === 'demo' ? $code : null;
    }

    /**
     * Check a code. True once; afterwards the code is used up.
     *
     * @throws RuntimeException when the code expired or had too many wrong tries
     */
    public function verify(string $phone, string $code): bool
    {
        $key = $this->key($phone);
        $otp = Cache::get($key);
        if (!$otp) {
            throw new RuntimeException('This OTP has expired. Please ask for a new one.');
        }
        if ($otp['attempts'] >= config('subscription.otp.max_attempts')) {
            Cache::forget($key);
            throw new RuntimeException('Too many wrong OTPs. Please ask for a new one.');
        }

        if (Hash::check($code, $otp['hash'])) {
            Cache::forget($key);

            return true;
        }

        $otp['attempts']++;
        Cache::put($key, $otp, now()->addMinutes(config('subscription.otp.valid_minutes')));

        return false;
    }

    private function key(string $phone): string
    {
        return 'member-otp:' . $phone;
    }
}
