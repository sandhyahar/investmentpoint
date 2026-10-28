<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SubscriptionRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Members: subscriber accounts that log in to the member dashboard. The admin can add, edit
 * and delete them, reset a password, and set the subscription period ("Subscribed on" /
 * "Expires on") shown on the member's dashboard.
 */
class MemberController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->input('q'));

        $members = User::where('role', 'user')
            ->when($search !== '', fn ($q) => $q->where(fn ($q) => $q->where('username', 'like', "%{$search}%")
                ->orWhere('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%")->orWhere('phone', 'like', "%{$search}%")))
            ->latest()->paginate(20)->withQueryString();

        $periods = $members->getCollection()->mapWithKeys(fn (User $m) => [$m->id => $this->period($m)]);

        return view('admin.members.index', compact('members', 'search', 'periods'));
    }

    public function create()
    {
        return view('admin.members.create', ['member' => null, 'period' => null]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        $username = 'm' . substr($data['phone'], -10);
        while (User::where('username', $username)->exists()) {
            $username = 'm' . substr($data['phone'], -10) . '_' . Str::lower(Str::random(3));
        }

        $member = User::forceCreate([
            'username' => $username, // internal only: members log in with mobile number + OTP
            'name' => $data['name'],
            'email' => isset($data['email']) ? Str::lower($data['email']) : null,
            'phone' => $data['phone'],
            'role' => 'user',
            'password' => Hash::make(Str::random(40)), // not used
        ]);
        $this->savePeriod($member, $data);

        return redirect()->route('admin.members.index')->with('success', 'Member ' . $member->name . ' added. They can log in with ' . $member->phone . ' and a WhatsApp OTP.');
    }

    public function edit(User $member)
    {
        $this->ensureMember($member);

        return view('admin.members.edit', ['member' => $member, 'period' => $this->period($member)]);
    }

    public function update(Request $request, User $member)
    {
        $this->ensureMember($member);
        $data = $this->validated($request, $member);

        $oldPhone = $member->phone;
        $member->fill([
            'name' => $data['name'],
            'email' => isset($data['email']) ? Str::lower($data['email']) : null,
            'phone' => $data['phone'],
        ])->save();
        // New WhatsApp number: the login (OTP) checks the subscription's WhatsApp number, so move them along
        if ($oldPhone !== $member->phone) {
            $member->subscriptions()->update(['whatsapp' => $member->phone]);
        }
        $this->savePeriod($member, $data);

        return redirect()->route('admin.members.index')->with('success', 'Member ' . $member->name . ' updated successfully.');
    }

    public function destroy(User $member)
    {
        $this->ensureMember($member);
        // Payment records stay in Admin -> Subscriptions, marked Cancelled so they give no access
        $member->subscriptions()->update(['status' => 'cancelled']);
        $member->delete();

        return redirect()->route('admin.members.index')->with('success', 'Member deleted successfully.');
    }

    /** Admin accounts are never edited or deleted from here. */
    private function ensureMember(User $member): void
    {
        abort_unless($member->role === 'user', 404);
    }

    /** The subscription the dashboard dates come from: the running one, else the latest paid one. */
    private function period(User $member): ?SubscriptionRequest
    {
        return $member->activeSubscription()
            ?? $member->subscriptions()->where('payment_status', 'paid')->orderByDesc('expires_at')->first();
    }

    /**
     * "Subscribed on" / "Expires on" (Indian dates): updates the member's current period, or adds
     * a paid period entered by the admin (payment mode "manual") when they have none.
     */
    private function savePeriod(User $member, array $data): void
    {
        if (empty($data['starts_on'])) {
            return;
        }

        $starts = Carbon::parse($data['starts_on'], 'Asia/Kolkata')->startOfDay()->utc();
        $expires = Carbon::parse($data['expires_on'], 'Asia/Kolkata')->endOfDay()->utc();

        if ($period = $this->period($member)) {
            $period->update(['starts_at' => $starts, 'expires_at' => $expires]);

            return;
        }

        SubscriptionRequest::create([
            'user_id' => $member->id,
            'name' => $member->name,
            'phone' => $member->phone,
            'whatsapp' => $member->phone,
            'email' => $member->email,
            'plan' => config('subscription.plan.name'),
            'price' => $data['amount'] ?? 0,
            'agreed_at' => now(),
            'agreed_to' => 'Added by admin',
            'status' => 'new',
            'payment_status' => 'paid',
            'payment_mode' => 'manual',
            'paid_at' => now(),
            'starts_at' => $starts,
            'expires_at' => $expires,
        ]);
    }

    private function validated(Request $request, ?User $existing = null): array
    {
        $data = $request->validate([
            'name' => 'required|string|max:100',
            'email' => ['nullable', 'email', 'max:150', Rule::unique('users', 'email')->ignore($existing)],
            'phone' => ['required', 'string', 'regex:/^(\+?91[\s-]?)?[6-9]\d{4}[\s-]?\d{5}$/'],
            'starts_on' => 'nullable|required_with:expires_on|date_format:Y-m-d',
            'expires_on' => 'nullable|required_with:starts_on|date_format:Y-m-d|after_or_equal:starts_on',
            'amount' => 'nullable|integer|min:0|max:10000000',
        ], [
            'phone.regex' => 'Please enter a valid 10-digit Indian WhatsApp number.',
            'expires_on.after_or_equal' => 'Expiry date must be on or after the subscription date.',
        ]);

        // The WhatsApp number is the member's login, so one member per number
        $data['phone'] = SubscriptionRequest::normalizePhone($data['phone']);
        if (User::where('role', 'user')->whereIn('phone', [$data['phone'], substr($data['phone'], 3)])
            ->when($existing, fn ($q) => $q->whereKeyNot($existing->id))->exists()) {
            throw ValidationException::withMessages(['phone' => 'Another member already uses this WhatsApp number.']);
        }

        return $data;
    }
}
