<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A picture in the admin Media Library (stored on the public disk under media/) */
class Media extends Model
{
    protected $table = 'media';

    protected $fillable = ['path', 'name', 'mime', 'size', 'width', 'height'];

    public function getUrlAttribute(): string
    {
        return asset('storage/' . $this->path);
    }

    /**
     * The same address without the domain ("/storage/media/x.jpg"): the admin page shows and downloads it
     * from whichever address the admin opened (www / no www / the PC's IP), never another domain
     */
    public function getSrcAttribute(): string
    {
        return parse_url($this->url, PHP_URL_PATH) ?: $this->url;
    }

    /** "245 KB" / "1.2 MB" */
    public function getSizeLabelAttribute(): string
    {
        return $this->size >= 1024 * 1024 ? round($this->size / 1048576, 1) . ' MB' : max(1, round($this->size / 1024)) . ' KB';
    }

    /** What the Media Library page and the picker show about a picture */
    public function details(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'url' => $this->url,
            'src' => $this->src,
            'width' => $this->width,
            'height' => $this->height,
            'size' => $this->size_label,
            'date' => $this->created_at?->timezone('Asia/Kolkata')->format('d M Y, h:i A'),
            'update_url' => route('admin.media.update', $this),
            'delete_url' => route('admin.media.destroy', $this),
        ];
    }
}
