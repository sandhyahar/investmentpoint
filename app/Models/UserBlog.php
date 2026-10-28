<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Support\ShareCard;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class UserBlog extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'author_name',
        'author_email',
        'author_photo',
        'author_designation',
        'author_reg_no',
        'author_website',
        'title',
        'slug',
        'content',
        'status',
        'approved_at',
    ];

    protected $casts = [
        'approved_at' => 'datetime',
    ];

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Writer's name as typed on the blog form (older blogs fall back to their account name).
     */
    public function getWriterNameAttribute(): string
    {
        return $this->author_name ?: ($this->author?->name ?: 'Investment Point');
    }

    /**
     * Uploaded writer photo, or the site logo when none was uploaded.
     */
    public function getWriterPhotoUrlAttribute(): string
    {
        return $this->author_photo ? asset('storage/' . $this->author_photo) : Setting::logoIconUrl();
    }

    /**
     * What the writer typed in "Website link" as a full address: "www.site.com" -> "https://www.site.com".
     * Only http(s) addresses with a real domain; anything else (javascript:, plain words) gives null.
     */
    public static function normalizeWebsite(?string $input): ?string
    {
        $url = trim((string) $input);
        if ($url === '') {
            return null;
        }
        if (!preg_match('~^https?://~i', $url)) {
            $url = 'https://' . ltrim($url, '/');
        }
        $host = parse_url($url, PHP_URL_HOST);

        return filter_var($url, FILTER_VALIDATE_URL) && $host && str_contains($host, '.') ? $url : null;
    }

    /** The website shown on the blog without "https://" and "www.", e.g. "nikhilbhatt.in/about" */
    public function getWebsiteLabelAttribute(): ?string
    {
        if (!$this->author_website) {
            return null;
        }
        $label = preg_replace('~^https?://(www\.)?~i', '', rtrim($this->author_website, '/'));

        return mb_strlen($label) > 40 ? mb_substr($label, 0, 39) . '…' : $label;
    }

    public function getHasWriterPhotoAttribute(): bool
    {
        return (bool) $this->author_photo;
    }

    /**
     * Picture for WhatsApp / Facebook link previews: the Investment Point banner with the writer's photo
     * in the place of the bull (App\Support\ShareCard). Made once, the first time the blog page is opened.
     * Null without a writer photo (the site's banner is used then).
     */
    public function getShareImageUrlAttribute(): ?string
    {
        if (!$this->author_photo) {
            return null;
        }
        $disk = Storage::disk('public');
        $path = self::sharePath($this->author_photo);
        if (!$disk->exists($path) && $disk->exists($this->author_photo)) {
            $card = ShareCard::make($disk->get($this->author_photo));
            if ($card) {
                $disk->put($path, $card);
            }
        }

        return $disk->exists($path) ? asset('storage/' . $path) : $this->writer_photo_url;
    }

    public function reactions(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(BlogReaction::class);
    }

    /**
     * Like / dislike counts and this reader's own vote ('like', 'dislike' or null) for the buttons
     *
     * @return array{likes: int, dislikes: int, mine: ?string}
     */
    public function reactionSummary(?string $visitor): array
    {
        $counts = $this->reactions()->selectRaw('reaction, count(*) as total')->groupBy('reaction')->pluck('total', 'reaction');
        $mine = $visitor ? $this->reactions()->where('visitor', $visitor)->value('reaction') : null;

        return [
            'likes' => (int) ($counts[BlogReaction::LIKE] ?? 0),
            'dislikes' => (int) ($counts[BlogReaction::DISLIKE] ?? 0),
            'mine' => match ((int) $mine) { BlogReaction::LIKE => 'like', BlogReaction::DISLIKE => 'dislike', default => null },
        ];
    }

    /** Delete the writer photo and its share picture */
    public function deleteWriterPhoto(): void
    {
        if ($this->author_photo) {
            $name = pathinfo($this->author_photo, PATHINFO_FILENAME);
            // (also the earlier blurred-frame version, blog-authors/share/<name>.jpg)
            Storage::disk('public')->delete([$this->author_photo, self::sharePath($this->author_photo), "blog-authors/share/{$name}.jpg"]);
            $this->author_photo = null;
        }
    }

    /** Delete everything this blog stored: writer photo, its share picture, pictures added with "Add image" */
    public function deleteFiles(): void
    {
        $this->deleteWriterPhoto();
        preg_match_all('~/storage/(blog-images/[A-Za-z0-9._-]+)~', (string) $this->content, $m);
        if ($m[1]) {
            Storage::disk('public')->delete(array_unique($m[1]));
        }
    }

    /** "-card" in the name: a new address, so WhatsApp fetches this picture instead of an older one */
    private static function sharePath(string $photo): string
    {
        return 'blog-authors/share/' . pathinfo($photo, PATHINFO_FILENAME) . '-card.jpg';
    }
}
