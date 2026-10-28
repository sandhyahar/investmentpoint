<?php

namespace App\Http\Controllers;

use App\Models\SubscriptionRequest;
use App\Models\User;
use App\Services\LoginOtp;
use App\Support\MemberLogin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Member login with the subscriber's WhatsApp number + an OTP sent on WhatsApp (no username / password).
 * Step 1: the number (only WhatsApp numbers of a running paid subscription get an OTP).
 * Step 2: the OTP. The first login creates the member account for that number automatically.
 * Admin accounts never log in here (they use the admin login).
 */
class MemberAuthController extends Controller
{
    public function showLogin()
    {
        if (Auth::guard('member')->check()) {
            return redirect()->route('member.dashboard');
        }

        $paid = $this->paidSubscription();

        return view('member.login', [
            'paid' => $paid,
            'otpPhone' => session('otp_phone'),                       // step 2 when set
            'prefill' => old('phone', $paid ? substr($paid->whatsapp_number, -10) : ''),
            'mode' => app(LoginOtp::class)->mode(),
        ]);
    }

    /** Step 1: send an OTP to a subscriber's WhatsApp number. */
    public function sendOtp(Request $request, LoginOtp $otp)
    {
        $data = $request->validate(
            ['phone' => ['required', 'string', 'regex:/^(\+?91[\s-]?)?[6-9]\d{4}[\s-]?\d{5}$/']],
            ['phone.regex' => 'Please enter a valid 10-digit Indian WhatsApp number.']
        );
        $phone = SubscriptionRequest::normalizePhone($data['phone']);

        // Stops anyone trying number after number to find out who subscribes: 10 unknown numbers per hour per connection
        $lookupKey = 'member-otp-unknown|' . $request->ip();
        if (RateLimiter::tooManyAttempts($lookupKey, 10)) {
            return back()->withErrors(['phone' => 'Too many tries. Please try again in an hour, or contact us.'])->withInput();
        }

        if (!SubscriptionRequest::whatsappHasAccess($phone)) {
            RateLimiter::hit($lookupKey, 3600);
            if (RateLimiter::tooManyAttempts($lookupKey, 10)) {
                Log::warning('Member login: many unknown numbers tried', ['ip' => $request->ip()]);
            }
            $request->session()->forget('otp_phone');

            return back()->withErrors(['phone' => 'No active subscription was found for this WhatsApp number. Please use the WhatsApp number you gave when subscribing, or subscribe first.'])->withInput();
        }

        try {
            $demoCode = $otp->send($phone);
        } catch (RuntimeException $e) {
            return back()->withErrors(['phone' => $e->getMessage()])->withInput();
        }

        session(['otp_phone' => $phone]);

        return redirect()->route('member.login')
            ->with('otp_sent', 'We have sent a ' . config('subscription.otp.length') . '-digit OTP on WhatsApp to ' . self::masked($phone) . '.')
            ->with('otp_demo_code', $demoCode);
    }

    /** Step 2: check the OTP and log in. */
    public function verifyOtp(Request $request, LoginOtp $otp)
    {
        $phone = session('otp_phone');
        if (!$phone) {
            return redirect()->route('member.login');
        }
        $data = $request->validate(['otp' => 'required|digits:' . config('subscription.otp.length')], ['otp.digits' => 'Please enter the ' . config('subscription.otp.length') . '-digit OTP.']);

        // Also limit guessing across many OTPs from one connection
        $key = 'member-otp-verify|' . $request->ip();
        if (RateLimiter::tooManyAttempts($key, 20)) {
            Log::warning('Member login: too many wrong OTPs', ['ip' => $request->ip()]);

            return back()->withErrors(['otp' => 'Too many tries. Please wait a few minutes.']);
        }

        try {
            $ok = $otp->verify($phone, $data['otp']);
        } catch (RuntimeException $e) {
            return back()->withErrors(['otp' => $e->getMessage()]);
        }
        if (!$ok) {
            RateLimiter::hit($key, 600);

            return back()->withErrors(['otp' => 'Wrong OTP. Please check the WhatsApp message and try again.']);
        }

        // Subscription could have been cancelled / expired meanwhile
        if (!SubscriptionRequest::whatsappHasAccess($phone)) {
            $request->session()->forget('otp_phone');

            return redirect()->route('member.login')->withErrors(['phone' => 'No active subscription was found for this WhatsApp number.']);
        }

        $user = $this->memberFor($phone);
        SubscriptionRequest::attachPhoneTo($user);

        // A fresh login starts the "away too long" clock again (App\Http\Middleware\KeepMemberSignedIn)
        $user->forceFill(['last_seen_at' => now()])->saveQuietly();

        Auth::guard('member')->login($user, true);
        $request->session()->regenerate();
        $request->session()->forget(['otp_phone', 'member_subscription']);
        MemberLogin::rememberMember($user);

        return redirect()->intended(route('member.dashboard'));
    }

    /** "Change number": back to step 1. */
    public function changeNumber(Request $request)
    {
        $request->session()->forget('otp_phone');

        return redirect()->route('member.login');
    }

    public function logout(Request $request)
    {
        // Only this browser: the member's other devices stay signed in (Auth::logout() would sign them out too)
        Auth::guard('member')->logoutCurrentDevice();
        // The admin signed in to the panel in this browser stays signed in: only the member's part ends
        Auth::guard('web')->check() ? $request->session()->regenerate() : $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('member.login')->with('success', 'You have been logged out.');
    }

    /** The member account for this (WhatsApp) number: the existing one, or a new one on the first login. */
    private function memberFor(string $phone): User
    {
        $user = User::where('role', 'user')->whereIn('phone', [$phone, substr($phone, 3)])
            ->withCount('subscriptions')->orderByDesc('subscriptions_count')->orderBy('id')->first();

        if ($user) {
            if ($user->phone !== $phone) {
                $user->update(['phone' => $phone]); // tidy old "9876543210" style numbers
            }

            return $user;
        }

        $latest = SubscriptionRequest::runningForWhatsapp($phone)->latest('paid_at')->first();
        $username = 'm' . substr($phone, -10);
        while (User::where('username', $username)->exists()) {
            $username = 'm' . substr($phone, -10) . '_' . Str::lower(Str::random(3));
        }

        return User::forceCreate([
            'name' => $latest?->name ?: $username,
            'username' => $username,
            'email' => $latest?->email && !User::where('email', Str::lower($latest->email))->exists() ? Str::lower($latest->email) : null,
            'phone' => $phone,
            'role' => 'user',
            'password' => Hash::make(Str::random(40)), // not used: members log in with OTP
        ]);
    }

    /** "+91 98XXX XX210" */
    public static function masked(string $phone): string
    {
        $d = substr($phone, -10);

        return '+91 ' . substr($d, 0, 2) . 'XXX XX' . substr($d, -3);
    }

    /** The just-paid subscription remembered after payment (for the "Payment successful" note). */
    private function paidSubscription(): ?SubscriptionRequest
    {
        $token = session('member_subscription');

        return $token ? SubscriptionRequest::where('token', $token)->where('payment_status', 'paid')->first() : null;
    }
}
