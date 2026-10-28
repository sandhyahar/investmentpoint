<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class ReaderQuery extends Model
{
    /** Private disk folder: attachments are only downloadable through the admin panel. */
    public const FOLDER = 'reader-queries';

    protected $fillable = ['name', 'contact', 'message', 'attachments', 'read_at'];

    protected $casts = [
        'attachments' => 'array',
        'read_at' => 'datetime',
    ];

    protected static function booted()
    {
        static::deleting(function (ReaderQuery $query) {
            Storage::disk('local')->delete(array_column($query->attachments ?? [], 'path'));
        });
    }
}
