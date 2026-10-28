<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PremiumContent extends Model
{
    use HasFactory;

    protected $fillable = [
        'badge',
        'title',
        'description',
        'price',
        'billing_period',
        'benefits',
        'button_text',
        'button_url',
        'status',
    ];

    protected $casts = [
        'benefits' => 'array',
    ];
}
