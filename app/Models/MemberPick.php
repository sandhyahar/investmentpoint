<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A stock on the member page for one date: name, CMP (current market price), support, resistance,
 * "achieved" (the level it reached, filled in later) and a full description (rich text) that opens
 * when the member clicks the stock's box. Educational levels only; the old "target" and "symbol"
 * columns are no longer shown or entered.
 */
class MemberPick extends Model
{
    protected $fillable = ['pick_date', 'stock_name', 'symbol', 'cmp', 'support', 'resistance', 'achieved', 'description', 'sort_order'];

    protected $casts = [
        'pick_date' => 'date',
        'sort_order' => 'integer',
    ];

    /** Always store a plain "2026-10-01" (the "date" cast would add 00:00:00 on some databases) */
    public function setPickDateAttribute($value): void
    {
        $this->attributes['pick_date'] = Carbon::parse($value)->format('Y-m-d');
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }

    /** "₹2,755" / "₹2,750-₹2,760", like the Technical Analysis levels */
    public function level(string $field): ?string
    {
        return TechnicalAnalysis::formatLevel($this->{$field});
    }

    /** Has the admin written a description? (an editor left empty still sends "<p></p>") */
    public function hasDescription(): bool
    {
        return \App\Support\Html::plain($this->description) !== '';
    }

    /** When the stock was added or last changed, in Indian time: "12 Oct 2026, 12:45 PM" */
    public function updatedLabel(): ?string
    {
        return ($this->updated_at ?? $this->created_at)?->copy()->timezone('Asia/Kolkata')->format('d M Y, g:i A');
    }

    /** Today in India, as a date string */
    public static function today(): string
    {
        return now('Asia/Kolkata')->format('Y-m-d');
    }
}
