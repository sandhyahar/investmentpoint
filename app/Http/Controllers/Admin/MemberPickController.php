<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MemberPick;
use App\Services\WhatsApp;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

/**
 * Member Picks: the daily stocks on the member page (stock name, CMP, support, resistance, achieved).
 * "Add" takes one date and several stocks at once; each stock can then be edited or deleted.
 * Saving with "Send a WhatsApp reminder" ticked tells every running subscriber that the date's stocks are updated;
 * an edited stock that has an achieved level goes out with the "achieved" template (stock name + achieved price).
 */
class MemberPickController extends Controller
{
    private const ROWS = 6; // stock rows on the "Add" form

    public function index(Request $request)
    {
        $request->validate(['date' => 'nullable|date_format:Y-m-d', 'q' => 'nullable|string|max:100']);
        $q = trim((string) $request->input('q'));

        $picks = MemberPick::query()
            ->when($request->filled('date'), fn ($query) => $query->whereDate('pick_date', $request->input('date')))
            ->when($q !== '', fn ($query) => $query->where('stock_name', 'like', "%{$q}%"))
            ->orderByDesc('pick_date')->ordered()
            ->paginate(40)->withQueryString();

        return view('admin.member-picks.index', [
            'picks' => $picks,
            'groups' => $picks->getCollection()->groupBy(fn (MemberPick $p) => $p->pick_date->format('Y-m-d')),
            'today' => MemberPick::today(),
        ]);
    }

    public function create(Request $request)
    {
        $date = $request->string('date', MemberPick::today())->toString();

        return view('admin.member-picks.create', [
            'date' => $date,
            'rows' => self::ROWS,
            'names' => $this->knownStocks(),
            'canRemind' => app(WhatsApp::class)->picksConfigured(),
            'remindedAt' => $this->remindedAt($date),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'pick_date' => 'required|date_format:Y-m-d',
            'rows' => 'required|array|max:' . self::ROWS,
            'rows.*.stock_name' => 'nullable|string|max:120',
            'rows.*.cmp' => 'nullable|string|max:60',
            'rows.*.support' => 'nullable|string|max:60',
            'rows.*.resistance' => 'nullable|string|max:60',
            'rows.*.achieved' => 'nullable|string|max:60',
            'rows.*.description' => 'nullable|string|max:50000',
            'notify' => 'nullable|boolean',
        ]);

        // Rows without a stock name are ignored; a filled row needs at least one level
        $rows = collect($data['rows'])->filter(fn ($r) => trim((string) ($r['stock_name'] ?? '')) !== '')->values();
        if ($rows->isEmpty()) {
            throw ValidationException::withMessages(['rows' => 'Enter at least one stock.']);
        }
        foreach ($data['rows'] as $i => $r) {
            if (trim((string) ($r['stock_name'] ?? '')) !== '' && !array_filter([$r['cmp'] ?? null, $r['support'] ?? null, $r['resistance'] ?? null])) {
                throw ValidationException::withMessages(["rows.$i.support" => 'Enter CMP, support or resistance for ' . $r['stock_name'] . '.']);
            }
        }

        $order = (int) MemberPick::whereDate('pick_date', $data['pick_date'])->max('sort_order');
        foreach ($rows as $r) {
            MemberPick::create($this->clean($r) + ['pick_date' => $data['pick_date'], 'sort_order' => ++$order]);
        }

        [$reminded, $error] = $request->boolean('notify') ? $this->remind($data['pick_date'], $rows->pluck('stock_name')->all()) : [null, null];

        return redirect()->route('admin.member-picks.index', ['date' => $data['pick_date']])
            ->with('success', $rows->count() . ' stock(s) added for ' . Carbon::parse($data['pick_date'])->format('d M Y') . '.' . $reminded)
            ->with('error', $error);
    }

    public function edit(MemberPick $memberPick)
    {
        return view('admin.member-picks.edit', [
            'pick' => $memberPick,
            'names' => $this->knownStocks(),
            'canRemind' => app(WhatsApp::class)->picksConfigured(),
            'remindedAt' => $this->remindedAt($memberPick->pick_date->format('Y-m-d')),
        ]);
    }

    public function update(Request $request, MemberPick $memberPick)
    {
        $data = $request->validate([
            'pick_date' => 'required|date_format:Y-m-d',
            'stock_name' => 'required|string|max:120',
            'cmp' => 'nullable|string|max:60',
            'support' => 'nullable|string|max:60',
            'resistance' => 'nullable|string|max:60',
            'achieved' => 'nullable|string|max:60',
            'description' => 'nullable|string|max:50000',
            'sort_order' => 'nullable|integer|min:0|max:9999',
            'notify' => 'nullable|boolean',
        ]);
        if (!array_filter([$data['cmp'] ?? null, $data['support'] ?? null, $data['resistance'] ?? null])) {
            throw ValidationException::withMessages(['support' => 'Enter CMP, support or resistance.']);
        }

        $memberPick->update($this->clean($data) + ['pick_date' => $data['pick_date'], 'sort_order' => $data['sort_order'] ?? $memberPick->sort_order]);

        $date = $memberPick->pick_date->format('Y-m-d');
        // A stock with an achieved level goes out with the "achieved" template: when it was updated (as on the
        // member card), its name and the achieved price
        $achieved = $memberPick->achieved ? ['stock' => $memberPick->stock_name, 'price' => $memberPick->level('achieved') ?? $memberPick->achieved,
            'updated' => (string) $memberPick->updatedLabel()] : null;
        [$reminded, $error] = $request->boolean('notify') ? $this->remind($date, [$memberPick->stock_name], $achieved) : [null, null];

        return redirect()->route('admin.member-picks.index', ['date' => $date])
            ->with('success', 'Stock updated successfully.' . $reminded)->with('error', $error);
    }

    public function destroy(MemberPick $memberPick)
    {
        $memberPick->delete();

        return back()->with('success', 'Stock deleted successfully.');
    }

    public function bulkDestroy(Request $request)
    {
        $validated = $request->validate(['ids' => 'required|array', 'ids.*' => 'integer']);
        $deleted = MemberPick::whereIn('id', $validated['ids'])->delete();

        return back()->with('success', $deleted . ' stock(s) deleted successfully.');
    }

    /**
     * WhatsApp reminder to every running subscriber that these stocks of the date are added / updated.
     * Returns [a sentence for the success message, an error message]; either can be null.
     */
    private function remind(string $date, array $stocks, ?array $achieved = null): array
    {
        $whatsapp = app(WhatsApp::class);
        $day = Carbon::parse($date);

        if ($date > MemberPick::today()) {
            return [' WhatsApp reminder not sent: members only see these stocks from ' . $day->format('d M Y') . '.', null];
        }
        if (!$whatsapp->picksConfigured() && !($achieved && $whatsapp->achievedConfigured())) {
            return [null, 'WhatsApp reminder not sent: WHATSAPP_PICKS_TEMPLATE (with the WhatsApp API keys) is not set in .env.'];
        }

        @set_time_limit(300); // one API call per subscriber
        $result = $whatsapp->remindSubscribers($day, $stocks, $achieved);
        if ($result['sent']) {
            Cache::put("member-picks.reminded.{$date}", now()->toIso8601String(), now()->addDay());
        }
        if (!$result['sent'] && !$result['failed']) {
            return [' No active subscribers to remind on WhatsApp.', null];
        }

        // The achieved template was refused and the general one went out instead: the admin should know why
        $fallback = $result['fallback'] && $result['sent']
            ? 'The "' . config('subscription.picks.achieved_template') . '" template was not accepted by WhatsApp (' . $result['fallback'] . '), so the general reminder template was used.'
            : null;

        return [
            $result['sent'] ? " WhatsApp reminder sent to {$result['sent']} subscriber(s)." : null,
            $result['failed'] ? "WhatsApp reminder failed for {$result['failed']} subscriber(s): {$result['error']}" : $fallback,
        ];
    }

    /** When this date's WhatsApp reminder last went out (the forms then leave the box unticked), Indian time */
    private function remindedAt(string $date): ?Carbon
    {
        $at = Cache::get("member-picks.reminded.{$date}");

        return $at ? Carbon::parse($at)->timezone('Asia/Kolkata') : null;
    }

    /** Trimmed values; an empty box is saved as nothing */
    private function clean(array $r): array
    {
        $v = fn ($k) => ($s = trim((string) ($r[$k] ?? ''))) === '' ? null : $s;

        return [
            'stock_name' => $v('stock_name'),
            'cmp' => $v('cmp'),
            'support' => $v('support'),
            'resistance' => $v('resistance'),
            'achieved' => $v('achieved'),
            // Rich text, cleaned when shown; an editor left empty ("<p></p>") is saved as nothing
            'description' => \App\Support\Html::plain($r['description'] ?? null) === '' ? null : trim($r['description']),
        ];
    }

    /** Stock names used before (suggestions while typing) */
    private function knownStocks(): array
    {
        return MemberPick::latest('id')->limit(300)->pluck('stock_name')->unique()->values()->all();
    }
}
