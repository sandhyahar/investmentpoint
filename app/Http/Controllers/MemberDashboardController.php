<?php

namespace App\Http\Controllers;

use App\Models\MemberPick;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Member page: the stock picks of the latest date on top (today's, once the admin adds them),
 * the 7 dates before that behind a "Previous day data" button, and a date search for any other day.
 * Picks come from Admin -> Member Picks. Needs an active paid subscription (admins can always look).
 */
class MemberDashboardController extends Controller
{
    // "Previous day data" button: the 7 days before the latest (older days: Search by date)
    private const PREVIOUS_DAYS = 7;

    public function __invoke(Request $request)
    {
        $user = $request->user();
        $endsAt = $user->subscriptionEndsAt();
        $current = $user->activeSubscription();

        if (!$user->isAdmin() && !$current) {
            return view('member.dashboard', ['active' => false, 'endsAt' => $endsAt, 'current' => null]);
        }

        $request->validate(['date' => 'nullable|date_format:Y-m-d']);
        $today = MemberPick::today();
        $search = $request->filled('date') ? Carbon::parse($request->input('date')) : null;

        if ($search) {
            // A searched date (picks added for later dates stay hidden until that day)
            $latestDate = $search->format('Y-m-d') <= $today ? $search : null;
            $previous = collect();
        } else {
            // Top: today's stocks only (until the admin adds them, an "updating soon" message);
            // "Previous day data": the 7 dates before today that have stocks
            $latestDate = Carbon::parse($today);

            $previousDates = MemberPick::where('pick_date', '<', $today)->distinct()
                ->orderByDesc('pick_date')->limit(self::PREVIOUS_DAYS)->pluck('pick_date')
                ->map(fn ($d) => Carbon::parse($d)->format('Y-m-d'));

            $previous = MemberPick::whereIn('pick_date', $previousDates)->ordered()->get()
                ->groupBy(fn (MemberPick $p) => $p->pick_date->format('Y-m-d'))
                ->sortKeysDesc();
        }

        $picks = $latestDate
            ? MemberPick::whereDate('pick_date', $latestDate->format('Y-m-d'))->ordered()->get()
            : collect();

        return view('member.dashboard', [
            'active' => true,
            'endsAt' => $endsAt,
            'current' => $current,
            'search' => $search,
            'latestDate' => $latestDate,
            'isWeekend' => now('Asia/Kolkata')->isWeekend(),
            'picks' => $picks,
            'previous' => $previous,
        ]);
    }
}
