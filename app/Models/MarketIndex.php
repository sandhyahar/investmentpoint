<?php

namespace App\Models;

use App\Services\MarketDataService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MarketIndex extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'symbol',
        'value',
        'change_percent',
        'direction',
    ];

    protected static function booted()
    {
        // Any admin change shows up on the site at the next refresh
        static::saved(fn () => MarketDataService::flush());
        static::deleted(fn () => MarketDataService::flush());
    }

    /** The rows shown in the site's live ticker bar: the first few by ID. */
    public static function tickers()
    {
        return static::orderBy('id')->take((int) config('market.ticker_count', 4))->get();
    }
}
