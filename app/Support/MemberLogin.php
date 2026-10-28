<?php

namespace App\Support;

use App\Models\SubscriptionRequest;
use App\Models\User;
use Illuminate\Support\Facades\Cookie;

/**
 * The header "Login" button is only for current subscribers. The browser remembers WHO it
 * belongs to (encrypted cookie: a member id, or the paid subscription before an account
 * exists), and the button is checked against the database on every page, so it disappears
 * when the subscription expires, the admin deletes the subscription, or deletes the account.
 */
class MemberLogin
{
    private const COOKIE = 'ip_subscriber';

    /** After paying (no account yet): remember the paid subscription. */
    public static function rememberSubscription(SubscriptionRequest $subscription): void
    {
        Cookie::queue(self::COOKIE, 't:' . $subscription->token, 60 * 24 * 365);
    }

    /** After logging in / creating an account: remember the member (kept after logout). */
    public static function rememberMember(User $user): void
    {
        Cookie::queue(self::COOKIE, 'u:' . $user->id, 60 * 24 * 365);
    }

    public static function showButton(): bool
    {
        static $show = null;

        return $show ??= self::check();
    }

    private static function check(): bool
    {
        $value = (string) request()->cookie(self::COOKIE);

        if (str_starts_with($value, 'u:')) {
            $user = User::where('role', 'user')->find((int) substr($value, 2));

            return (bool) $user?->activeSubscription();
        }

        $token = str_starts_with($value, 't:') ? substr($value, 2) : session('member_subscription');
        if (!$token) {
            return false;
        }

        $subscription = SubscriptionRequest::where('token', $token)->where('payment_status', 'paid')
            ->where('status', '!=', 'cancelled')->where('expires_at', '>', now())->first();
        if (!$subscription) {
            return false;
        }

        // Already attached to an account: that account must still exist and be active
        return !$subscription->user_id || (bool) $subscription->user?->activeSubscription();
    }
}
