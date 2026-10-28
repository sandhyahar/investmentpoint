<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A news channel (submit link and / or email) or a customer email that articles can be sent to. */
class PressContact extends Model
{
    protected $fillable = ['type', 'name', 'url', 'email', 'active'];

    protected $casts = ['active' => 'boolean'];

    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    public function scopeChannels($query)
    {
        return $query->where('type', 'channel');
    }

    public function scopeCustomers($query)
    {
        return $query->where('type', 'customer');
    }
}
