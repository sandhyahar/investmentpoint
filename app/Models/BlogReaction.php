<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One reader's like (1) or dislike (-1) of a blog */
class BlogReaction extends Model
{
    public const LIKE = 1;
    public const DISLIKE = -1;

    protected $fillable = ['user_blog_id', 'visitor', 'reaction'];

    protected $casts = ['reaction' => 'integer'];
}
