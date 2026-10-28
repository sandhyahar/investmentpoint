<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A plan category readers can tick on an Insurance page (Services -> Insurance), set in
 * Admin -> Services -> Insurance Products. `insurance`: life | general.
 */
class InsuranceProduct extends Model
{
    /** insurance key => menu / page name */
    public const INSURANCES = ['life' => 'Life Insurance', 'general' => 'General Insurance'];

    protected $fillable = ['insurance', 'name', 'description', 'sort_order', 'is_active'];

    protected $casts = ['is_active' => 'boolean', 'sort_order' => 'integer'];

    public function scopeFor($query, string $insurance)
    {
        return $query->where('insurance', $insurance)->orderBy('sort_order')->orderBy('id');
    }

    /** Shown on the website: Life Insurance always; General Insurance once the admin switches it on */
    public static function shown(): array
    {
        return array_keys(array_filter(['life' => true, 'general' => (bool) Setting::get('insurance_general_enabled')]));
    }
}
