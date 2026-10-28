<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * How one newspaper shows in "Trusted by" on the home page (Admin -> Media Articles -> Newspaper Logos):
 * its official logo, display name, position, hidden or not, and an optional link of its own (e.g. a TV channel's
 * YouTube playlist) that the logo opens instead of the paper's Media Articles. name = MediaArticle::normalizePublication().
 * A paper added by hand has a row here but no Media Articles.
 */
class NewspaperLogo extends Model
{
    protected $fillable = ['name', 'display_name', 'logo', 'link', 'sort_order', 'is_hidden'];

    protected $casts = ['is_hidden' => 'boolean', 'sort_order' => 'integer'];

    public function url(): ?string
    {
        return $this->logo ? asset('storage/' . $this->logo) : null;
    }

    /** Width of the small copy shown in "Trusted by" (a card is 236 px wide, sharp on phone screens too) */
    public const SMALL_WIDTH = 480;

    /**
     * The logo for "Trusted by": a small copy (made once, next to the logo in newspaper-logos/small), so 40 logos are a
     * few hundred KB instead of several MB and are already there when they slide in on a phone. The logo itself when
     * the copy cannot be made.
     */
    public function smallUrl(): ?string
    {
        if (!$this->logo) {
            return null;
        }
        $disk = Storage::disk('public');
        $stem = 'newspaper-logos/small/' . pathinfo($this->logo, PATHINFO_FILENAME);
        foreach (['jpg', 'png'] as $ext) {
            if ($disk->exists("{$stem}.{$ext}")) {
                return asset("storage/{$stem}.{$ext}");
            }
        }
        if (!$disk->exists($this->logo)) {
            return $this->url();
        }
        [$bytes, $ext] = \App\Support\ImageOptimizer::optimize((string) $disk->get($this->logo), pathinfo($this->logo, PATHINFO_EXTENSION), self::SMALL_WIDTH, 85);
        if (!in_array($ext, ['jpg', 'png'], true) || !$disk->put("{$stem}.{$ext}", $bytes)) {
            return $this->url();
        }

        return asset("storage/{$stem}.{$ext}");
    }

    /** name => logo URL for every newspaper that has a logo */
    public static function urls(): array
    {
        return static::whereNotNull('logo')->get()->mapWithKeys(fn (self $l) => [$l->name => $l->url()])->all();
    }

    public function deleteLogoFile(): void
    {
        if ($this->logo) {
            $stem = 'newspaper-logos/small/' . pathinfo($this->logo, PATHINFO_FILENAME);
            Storage::disk('public')->delete([$this->logo, "{$stem}.jpg", "{$stem}.png"]);
        }
    }

    protected static function booted()
    {
        static::deleting(fn (self $l) => $l->deleteLogoFile());
    }
}
