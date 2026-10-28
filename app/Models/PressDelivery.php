<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One send of an article to one channel link or email address, and whether it worked. */
class PressDelivery extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['article_type', 'article_id', 'article_title', 'press_contact_id', 'contact_name', 'via', 'target', 'status', 'message'];

    protected $casts = ['created_at' => 'datetime'];
}
