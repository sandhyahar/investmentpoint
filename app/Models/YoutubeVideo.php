<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class YoutubeVideo extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'video_thumbnail',
        'video_url',
        'published_date',
        'youtube_url',
        'youtube_id',
        'thumbnail',
        'description',
        'publish_date',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'published_date' => 'date',
    ];

    /**
     * Pull the 11-character video id out of any common YouTube link:
     * watch?v=, youtu.be/, shorts/, live/, embed/.
     */
    public static function extractId(?string $url): ?string
    {
        if (!$url) {
            return null;
        }

        $patterns = [
            '~[?&]v=([A-Za-z0-9_-]{11})~',
            '~youtu\.be/([A-Za-z0-9_-]{11})~',
            '~youtube(?:-nocookie)?\.com/(?:shorts|live|embed)/([A-Za-z0-9_-]{11})~',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $url, $match)) {
                return $match[1];
            }
        }

        return null;
    }

    public function getVideoIdAttribute(): ?string
    {
        return self::extractId($this->video_url ?: $this->youtube_url);
    }

    public function getWatchUrlAttribute(): string
    {
        return $this->video_url ?: ($this->youtube_url ?: 'https://www.youtube.com/c/NikhilBhattResearchAnalystSEBI');
    }

    /**
     * Embed URL that auto-plays muted and loops (browsers only allow muted autoplay),
     * honouring "&t=90" / "&t=1m30s" in the link.
     */
    public function getEmbedUrlAttribute(): ?string
    {
        if (!$this->video_id) {
            return null;
        }

        $params = [
            'autoplay' => 1,
            'mute' => 1,
            'loop' => 1,
            'playlist' => $this->video_id, // required for loop to work on a single video
            'controls' => 0,
            'rel' => 0,
            'playsinline' => 1,
            'modestbranding' => 1,
            'enablejsapi' => 1, // lets the homepage slider send "play" when a slide comes into view
        ];

        if (preg_match('~[?&]t=(?:(\d+)h)?(?:(\d+)m)?(\d+)?s?~', (string) $this->watch_url, $t)) {
            $start = ((int) ($t[1] ?? 0)) * 3600 + ((int) ($t[2] ?? 0)) * 60 + (int) ($t[3] ?? 0);
            if ($start > 0) {
                $params['start'] = $start;
            }
        }

        return 'https://www.youtube.com/embed/' . $this->video_id . '?' . http_build_query($params);
    }

    /**
     * Embed URL for click-to-play: starts with sound and normal player controls.
     */
    public function getPlayerUrlAttribute(): ?string
    {
        if (!$this->video_id) {
            return null;
        }

        $params = ['autoplay' => 1, 'rel' => 0, 'playsinline' => 1, 'modestbranding' => 1, 'enablejsapi' => 1];
        if (preg_match('~[?&]start=(\d+)~', (string) $this->embed_url, $m)) {
            $params['start'] = $m[1];
        }

        return 'https://www.youtube.com/embed/' . $this->video_id . '?' . http_build_query($params);
    }

    /**
     * YouTube's own thumbnail; an older uploaded one only for links that aren't a YouTube video.
     */
    public function getThumbnailUrlAttribute(): string
    {
        if ($this->video_id) {
            return 'https://i.ytimg.com/vi/' . $this->video_id . '/hqdefault.jpg';
        }

        $uploaded = $this->video_thumbnail ?: $this->thumbnail;

        if ($uploaded) {
            return Str::startsWith($uploaded, ['http://', 'https://']) ? $uploaded : asset('storage/' . $uploaded);
        }

        return 'https://images.unsplash.com/photo-1551288049-bebda4e38f71?auto=format&fit=crop&w=500&q=75';
    }
}
