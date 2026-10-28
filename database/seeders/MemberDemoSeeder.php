<?php

namespace Database\Seeders;

use App\Models\MemberPick;
use App\Models\SubscriptionRequest;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * DEMO data for the member page (local testing only, not called by DatabaseSeeder):
 *  - daily stock picks (support / resistance / target) for today and the 10 weekdays before:
 *    today = Reliance, HDFC Bank, Bharti Airtel, ICICI Bank; earlier days = 3 or 4 stocks each
 *  - a demo member with a paid (demo) subscription
 *
 *   php artisan db:seed --class=MemberDemoSeeder
 *
 * Demo login: mobile 9000000001 + the OTP (shown on screen while SMS_DEMO_OTP=true).
 * Delete the demo member (and the demo picks in Admin -> Member Picks) before going live.
 */
class MemberDemoSeeder extends Seeder
{
    public function run(): void
    {
        $stocks = [
            'RELIANCE' => ['Reliance Industries Ltd', 2842],
            'HDFCBANK' => ['HDFC Bank Ltd', 1652],
            'BHARTIARTL' => ['Bharti Airtel Ltd', 1739],
            'ICICIBANK' => ['ICICI Bank Ltd', 1366],
            'TCS' => ['Tata Consultancy Services Ltd', 3380],
            'INFY' => ['Infosys Ltd', 1540],
            'SBIN' => ['State Bank of India', 812],
            'ITC' => ['ITC Ltd', 458],
            'LT' => ['Larsen & Toubro Ltd', 3615],
            'TATAMOTORS' => ['Tata Motors Ltd', 982],
        ];

        // Today + the 10 weekdays before it (Indian time)
        $days = collect();
        for ($d = Carbon::now('Asia/Kolkata')->startOfDay(); $days->count() < 11; $d->subDay()) {
            if (!$d->isWeekend() || $days->isEmpty()) {
                $days->push($d->copy());
            }
        }

        mt_srand(2026); // same demo picks on every run
        $symbols = array_keys($stocks);
        foreach ($days as $i => $day) {
            $picks = $i === 0
                ? ['RELIANCE', 'HDFCBANK', 'BHARTIARTL', 'ICICIBANK']
                : array_slice($symbols, ($i * 3) % count($symbols), $i % 2 ? 3 : 4);
            if (count($picks) < 3) {
                $picks = array_merge($picks, array_slice($symbols, 0, 3 - count($picks)));
            }

            MemberPick::whereDate('pick_date', $day->format('Y-m-d'))->delete();
            foreach (array_values($picks) as $order => $symbol) {
                [$name, $base] = $stocks[$symbol];
                $price = $base * (1 + mt_rand(-40, 40) / 1000);
                $round = $price > 1000 ? 5 : 1;
                $level = fn ($factor) => (string) (round($price * $factor / $round) * $round);

                MemberPick::create([
                    'pick_date' => $day->format('Y-m-d'),
                    'stock_name' => $name,
                    'symbol' => $symbol,
                    'support' => $level(0.98),
                    'resistance' => $level(1.015),
                    'target' => $level(1.035),
                    'sort_order' => $order + 1,
                ]);
            }
        }

        $user = User::updateOrCreate(['username' => 'demomember'], [
            'name' => 'Demo Member',
            'email' => 'demomember@example.com',
            'phone' => '+919000000001',
            'role' => 'user',
            'password' => Hash::make(Str::random(40)), // not used: members log in with OTP
        ]);

        if (!$user->activeSubscription()) {
            $sub = SubscriptionRequest::create([
                'user_id' => $user->id,
                'name' => $user->name,
                'phone' => $user->phone,
                'email' => $user->email,
                'plan' => config('subscription.plan.name'),
                'price' => config('subscription.plan.price'),
                'agreed_at' => now(),
                'agreed_to' => SubscriptionRequest::AGREED_TO,
                'status' => 'new',
            ]);
            $sub->markPaid('demo', 'demo_seed_' . $sub->id);
        }
    }
}
