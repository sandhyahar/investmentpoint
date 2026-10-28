<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/** A "Subscription" from the subscription page: the reader's agreement record, payment and paid period. */
class SubscriptionRequest extends Model
{
    // Admin labels only; the stored keys stay the same
    public const STATUSES = ['new' => 'New User', 'contacted' => 'Old User', 'cancelled' => 'Cancelled'];

    /** Admin filter: payment / period */
    public const PAYMENT_FILTERS = ['paid' => 'Paid', 'pending' => 'Awaiting payment', 'active' => 'Active', 'expired' => 'Expired'];

    /** What ticking the box means (stored with every request). */
    public const AGREED_TO = 'Terms & Conditions, Privacy Policy, Refund & Cancellation Policy and Disclaimer; sharing the information entered with Shree Investment Point and Razorpay';

    protected $fillable = ['token', 'user_id', 'name', 'phone', 'whatsapp', 'email', 'address', 'plan', 'plan_key', 'price', 'months', 'agreed_at', 'agreed_to', 'ip', 'user_agent', 'status',
        'payment_status', 'payment_mode', 'razorpay_order_id', 'razorpay_payment_id', 'razorpay_signature', 'paid_at', 'starts_at', 'expires_at',
        'whatsapp_status', 'whatsapp_sent_at', 'whatsapp_error'];

    protected $casts = [
        'agreed_at' => 'datetime',
        'paid_at' => 'datetime',
        'starts_at' => 'datetime',
        'expires_at' => 'datetime',
        'whatsapp_sent_at' => 'datetime',
        'price' => 'integer',
        'months' => 'integer',
    ];

    /**
     * The plans on the subscription page, with the prices set in Admin -> Site Settings.
     *
     * @return array<string, array{name: string, price: int, months: int, period: string, deliver: string, member_access: bool, tagline: string, benefits: array}>
     */
    public static function plans(): array
    {
        return config('subscription.plans', []);
    }

    /** The prices saved in Admin -> Site Settings replace the defaults in config/subscription.php (run at start-up) */
    public static function applySavedPrices(): void
    {
        foreach (array_keys(config('subscription.plans', [])) as $key) {
            if ($price = (int) Setting::get('price_' . $key)) {
                config(["subscription.plans.{$key}.price" => $price]);
            }
        }
        // Members added by hand (Admin -> Members) get the plan with member access
        config(['subscription.plan.price' => config('subscription.plans.newsletter.price', config('subscription.plan.price'))]);
    }

    /** Plan keys whose subscribers may log in to the member page */
    public static function memberPlanKeys(): array
    {
        return array_keys(array_filter(static::plans(), fn ($p) => $p['member_access'] ?? false));
    }

    /** This subscription's plan settings (older Premium subscriptions: null) */
    public function planConfig(): ?array
    {
        return $this->plan_key ? (static::plans()[$this->plan_key] ?? null) : null;
    }

    /** Opens the member page: older Premium subscriptions (no plan key) and plans with member access */
    public function givesMemberAccess(): bool
    {
        return !$this->plan_key || in_array($this->plan_key, static::memberPlanKeys(), true);
    }

    /** Sent by post (the printed paper): has a postal address instead of WhatsApp delivery */
    public function isByPost(): bool
    {
        return ($this->planConfig()['deliver'] ?? null) === 'post';
    }

    /** "month" / "year" for the price line */
    public function getPeriodAttribute(): string
    {
        return ($this->months ?? 1) % 12 === 0 ? 'year' : 'month';
    }

    /** Subscriptions that open the member page (see givesMemberAccess) */
    public function scopeWithMemberAccess($query)
    {
        return $query->where(fn ($q) => $q->whereNull('plan_key')->orWhereIn('plan_key', static::memberPlanKeys()));
    }

    protected static function booted(): void
    {
        static::creating(fn (self $s) => $s->token ??= Str::random(40));
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /** "+91XXXXXXXXXX" from any way of writing an Indian mobile number. */
    public static function normalizePhone(string $phone): string
    {
        return '+91' . substr(preg_replace('/\D/', '', $phone), -10);
    }

    /**
     * Payments that give access right now. Real Razorpay payments and periods added by the admin
     * always count; free demo payments only while the site is in demo mode, and Razorpay test-mode
     * payments only while test keys are in use. So once the real (live) keys are added, every
     * subscription made while testing stops giving access automatically.
     */
    public static function trustedModes(): array
    {
        $modes = ['live', 'manual'];
        $current = app(\App\Services\Razorpay::class)->mode();
        if (in_array($current, ['demo', 'test'], true)) {
            $modes[] = $current;
        }

        return $modes;
    }

    /** Paid (for real, see trustedModes), not cancelled and not yet expired (may start later when renewed early). */
    public function scopeRunning($query)
    {
        return $query->where('payment_status', 'paid')->whereIn('payment_mode', static::trustedModes())
            ->where('status', '!=', 'cancelled')->where('expires_at', '>', now());
    }

    /** Where WhatsApp messages (login OTP, payment confirmation) go: the WhatsApp number, or the mobile number when it's the same. */
    public function getWhatsappNumberAttribute(): string
    {
        return $this->whatsapp ?: $this->phone;
    }

    /** Running subscriptions whose WhatsApp number is this one (older ones without a WhatsApp number: their mobile number). */
    public static function runningForWhatsapp(string $number)
    {
        $number = static::normalizePhone($number);

        return static::running()->withMemberAccess()->where(fn ($q) => $q->where('whatsapp', $number)
            ->orWhere(fn ($q) => $q->whereNull('whatsapp')->where('phone', $number)));
    }

    /** Can this WhatsApp number log in? (a running paid subscription uses it) */
    public static function whatsappHasAccess(string $number): bool
    {
        return static::runningForWhatsapp($number)->exists();
    }

    /**
     * The member has just proved (WhatsApp OTP) that they own this number (the account's phone): attach every
     * paid subscription with it as the WhatsApp number that isn't attached to an account yet.
     */
    public static function attachPhoneTo(User $user): void
    {
        if (!$user->isAdmin() && $user->phone) {
            $number = static::normalizePhone($user->phone);
            static::where(fn ($q) => $q->where('whatsapp', $number)->orWhere(fn ($q) => $q->whereNull('whatsapp')->where('phone', $number)))
                ->where('payment_status', 'paid')->whereNull('user_id')->update(['user_id' => $user->id]);
        }
    }

    public function isPaid(): bool
    {
        return $this->payment_status === 'paid';
    }

    public function isActive(): bool
    {
        return $this->isPaid() && $this->expires_at?->isFuture();
    }

    /**
     * Payment confirmed: the paid period starts now, or when the reader's current subscription
     * ends if they renew early (so no paid days are lost). Safe to call twice, even at the same
     * moment (the row is locked, so a doubled callback can't start two periods).
     * Returns true only for the call that actually marked it paid.
     */
    public function markPaid(string $mode, string $paymentId, ?string $signature = null): bool
    {
        $paid = \Illuminate\Support\Facades\DB::transaction(function () use ($mode, $paymentId, $signature) {
            $fresh = static::whereKey($this->getKey())->lockForUpdate()->first();
            if (!$fresh || $fresh->isPaid()) {
                return false;
            }

            // Renewing the same plan early: the new period follows the running one
            $currentEnd = static::running()->where('phone', $fresh->phone)
                ->where(fn ($q) => $fresh->plan_key ? $q->where('plan_key', $fresh->plan_key) : $q->whereNull('plan_key'))
                ->max('expires_at');
            $start = $currentEnd ? Carbon::parse($currentEnd) : now();

            $fresh->update([
                'payment_status' => 'paid',
                'payment_mode' => $mode,
                'razorpay_payment_id' => $paymentId,
                'razorpay_signature' => $signature,
                'paid_at' => now(),
                'starts_at' => $start,
                'expires_at' => $start->copy()->addMonthsNoOverflow($fresh->months ?: (int) config('subscription.plan.months', 1)),
            ]);

            return true;
        });
        $this->refresh();

        return $paid;
    }

    /** "30 Sep 2026" in Indian time */
    public static function day(?Carbon $date): string
    {
        return $date ? $date->copy()->timezone('Asia/Kolkata')->format('d M Y') : '-';
    }

    /** WhatsApp confirmation sent after payment (also used for the admin's manual "Send on WhatsApp" link). */
    public function whatsappMessage(): string
    {
        $plan = config('subscription.plan');
        $contact = preg_replace('/^\+91(\d{5})(\d{5})$/', '+91 $1 $2', $plan['contact_phone']);

        return "Hello {$this->name},\n\n"
            . "Thank you for subscribing to *Investment Point {$this->plan}*.\n"
            . '✅ Payment received: ₹' . number_format($this->price) . ($this->razorpay_payment_id ? " (ID: {$this->razorpay_payment_id})" : '') . "\n"
            . '📅 Subscription: ' . static::day($this->starts_at) . ' to ' . static::day($this->expires_at) . "\n"
            . ($this->isByPost() && $this->address ? "📮 Paper posted to: {$this->address}\n" : '') . "\n"
            . "For any help, contact {$plan['contact_name']}: {$contact}\n\n"
            . 'Content is for educational purposes only. No buy/sell recommendations.';
    }
}
