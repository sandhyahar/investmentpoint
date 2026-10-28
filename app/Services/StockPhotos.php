<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Real business photos from a free stock library: Pixabay (PIXABAY_API_KEY) or Pexels (PEXELS_API_KEY),
 * or, with no key at all, public-domain photos from Openverse (admin "Find real photo" only).
 * A headline becomes a search for its sector ("gold bars", "oil refinery", "stock market screen"...),
 * and a landscape photo that was not used recently is picked, so news pictures look real and don't repeat.
 * Photos are downloaded to our own server (both libraries ask for that) and search results are kept
 * for 24 hours (Pixabay's rule).
 */
class StockPhotos
{
    private const USED_KEY = 'stock-photos.used';

    /** pixabay | pexels | openverse | null */
    public function provider(): ?string
    {
        return match (true) {
            (bool) config('services.pixabay.key') => 'pixabay',
            (bool) config('services.pexels.key') => 'pexels',
            (bool) config('services.openverse.enabled') => 'openverse',
            default => null,
        };
    }

    public function configured(): bool
    {
        return $this->provider() !== null;
    }

    /** A library with an API key (Pixabay / Pexels): needed for the site-wide footer credit and auto news photos. */
    public function keyed(): bool
    {
        return in_array($this->provider(), ['pixabay', 'pexels'], true);
    }

    /** "Pixabay" / "Pexels" / "Openverse", for credits */
    public function providerName(): string
    {
        return match ($this->provider()) {
            'pexels' => 'Pexels',
            'openverse' => 'Openverse',
            default => 'Pixabay',
        };
    }

    /** Search words for a headline's topic (no company names: they rarely match good photos). */
    public static function queryFor(string $headline): string
    {
        $h = mb_strtolower($headline);
        // First match wins, like NewsWriter::imageTopic; several choices per topic for variety
        $topics = [
            'ipo|listing|drhp|sebi' => ['stock exchange', 'stock market trading screen', 'financial district skyscrapers'],
            'tax|gst|audit|itr|budget' => ['tax documents calculator', 'accountant desk calculator', 'financial paperwork office'],
            'software|systems|infotech|\bit\b|\bai\b|tech\b|technology' => ['data center servers', 'technology office', 'server room'],
            'gold|silver|jewel' => ['gold bars', 'gold bullion', 'gold coins'],
            'rupee|dollar|forex|currency' => ['indian rupee', 'currency exchange', 'money banknotes'],
            'bank|lender|rbi|repo|loan|credit|nbfc|upi|mdr' => ['bank building', 'banking finance', 'digital payment'],
            'crude|oil|petroleum|refin|gas|fuel' => ['oil refinery', 'oil industry', 'petrochemical plant'],
            'metal|steel|alumin|zinc|copper|mining|coal' => ['steel factory', 'steel industry', 'mining industry'],
            'pharma|drug|health|hospital' => ['pharmaceutical laboratory', 'medicine production', 'pharmacy pills'],
            'auto|car|vehicle|ev\b|two-wheeler' => ['car factory', 'automobile production line', 'electric car charging'],
            'realty|real estate|housing|property|cement' => ['construction site crane', 'skyscraper construction', 'modern apartment building'],
            'telecom|5g|tariff' => ['telecommunication tower', 'cell tower', '5g network'],
            'power|energy|solar|renewable' => ['solar panels', 'wind turbines', 'renewable energy'],
            'fund|sip|investor|block deal|stake|dividend' => ['financial charts laptop', 'investment planning', 'business meeting finance'],
            'futures|wall street|global|iran|china|us ' => ['stock market screens', 'global finance', 'trading floor'],
        ];
        foreach ($topics as $pattern => $queries) {
            if (preg_match('/' . $pattern . '/u', $h)) {
                return $queries[array_rand($queries)];
            }
        }
        $general = preg_match('/fall|decline|drop|slump|crash|loss|down/u', $h)
            ? ['stock market crash chart', 'stock market red chart', 'stock market trading screen']
            : ['stock market chart', 'stock market trading screen', 'business finance office'];

        return $general[array_rand($general)];
    }

    /**
     * A landscape photo for the search, not one of the recently used ones.
     *
     * @return array{id: string, url: string, credit: string, credit_url: string, alt: string, provider: string}|null
     */
    public function find(string $query): ?array
    {
        $provider = $this->provider();
        if (!$provider) {
            return null;
        }

        // Search results are kept for 24 hours (Pixabay requires it; it also saves requests)
        $key = 'stock-photos.search.' . $provider . '.' . md5(mb_strtolower(trim($query)));
        $photos = Cache::get($key);
        if ($photos === null) {
            $photos = match ($provider) {
                'pixabay' => $this->searchPixabay($query),
                'pexels' => $this->searchPexels($query),
                default => $this->searchOpenverse($query),
            };
            if (!$photos) {
                return null; // request failed or nothing found: search again next time
            }
            Cache::put($key, $photos, now()->addDay());
        }

        $used = Cache::get(self::USED_KEY, []);
        $fresh = array_values(array_filter($photos, fn ($p) => !in_array($p['id'], $used, true)));
        // Pick from the best matches (both libraries rank by relevance / popularity), avoiding repeats
        $pool = array_slice($fresh ?: $photos, 0, 20);

        return $pool ? $pool[array_rand($pool)] + ['provider' => $provider] : null;
    }

    /** @return array<int, array{id: string, url: string, credit: string, credit_url: string, alt: string}>|null */
    private function searchPixabay(string $query): ?array
    {
        try {
            $response = Http::acceptJson()->connectTimeout(5)->timeout(15)->get('https://pixabay.com/api/', [
                'key' => config('services.pixabay.key'),
                'q' => Str::limit($query, 90, ''),
                'image_type' => 'photo',
                'orientation' => 'horizontal',
                'safesearch' => 'true',
                'min_width' => 1200,
                'per_page' => 40,
            ]);
        } catch (Throwable $e) {
            Log::warning('Pixabay search failed: ' . $e->getMessage());

            return null;
        }
        if (!$response->successful()) {
            Log::warning('Pixabay search failed: HTTP ' . $response->status());

            return null;
        }

        return collect($response->json('hits', []))->filter(fn ($p) => !empty($p['largeImageURL']))->map(fn ($p) => [
            'id' => 'pixabay:' . $p['id'],
            'url' => $p['largeImageURL'], // up to 1280 px wide
            'credit' => Str::limit((string) ($p['user'] ?? 'Pixabay'), 140, ''),
            'credit_url' => (string) ($p['pageURL'] ?? 'https://pixabay.com'),
            'alt' => (string) ($p['tags'] ?? ''),
        ])->values()->all();
    }

    /** @return array<int, array{id: string, url: string, credit: string, credit_url: string, alt: string}>|null */
    private function searchPexels(string $query): ?array
    {
        try {
            $response = Http::withHeaders(['Authorization' => config('services.pexels.key')])
                ->acceptJson()->connectTimeout(5)->timeout(15)
                ->get('https://api.pexels.com/v1/search', ['query' => $query, 'orientation' => 'landscape', 'size' => 'large', 'per_page' => 40]);
        } catch (Throwable $e) {
            Log::warning('Pexels search failed: ' . $e->getMessage());

            return null;
        }
        if (!$response->successful()) {
            Log::warning('Pexels search failed: HTTP ' . $response->status());

            return null;
        }

        return collect($response->json('photos', []))->filter(fn ($p) => !empty($p['src']['landscape']))->map(fn ($p) => [
            'id' => 'pexels:' . $p['id'],
            'url' => $p['src']['landscape'], // 1200 x 627, cropped by Pexels
            'credit' => Str::limit((string) ($p['photographer'] ?? 'Pexels'), 140, ''),
            'credit_url' => (string) ($p['url'] ?? 'https://www.pexels.com'),
            'alt' => (string) ($p['alt'] ?? ''),
        ])->values()->all();
    }

    /**
     * Openverse (no key): public-domain photographs only (CC0 / Public Domain Mark), so no credit
     * is required; we still show the photographer in the admin popup.
     *
     * @return array<int, array{id: string, url: string, credit: string, credit_url: string, alt: string}>|null
     */
    private function searchOpenverse(string $query): ?array
    {
        // Its search is literal, so widen step by step until there is a choice of pictures:
        // photographs only -> any image type -> the first two words ("stock market trading screen" -> "stock market")
        $short = implode(' ', array_slice(preg_split('/\s+/', trim($query)), 0, 2));
        $attempts = [[$query, true], [$query, false]];
        if ($short !== trim($query)) {
            $attempts[] = [$short, true];
            $attempts[] = [$short, false];
        }

        $photos = [];
        foreach ($attempts as [$q, $photosOnly]) {
            $found = $this->openverseRequest($q, $photosOnly);
            if ($found === null) {
                return $photos ?: null; // request failed (limit reached / offline)
            }
            $photos = collect($photos)->concat($found)->unique('id')->values()->all();
            if (count($photos) >= 5) {
                break;
            }
        }

        return $photos;
    }

    private function openverseRequest(string $query, bool $photosOnly): ?array
    {
        try {
            $response = Http::acceptJson()->withUserAgent(self::USER_AGENT)->connectTimeout(5)->timeout(20)
                ->get('https://api.openverse.org/v1/images/', array_filter([
                    'q' => Str::limit($query, 90, ''),
                    'license' => 'cc0,pdm',
                    'category' => $photosOnly ? 'photograph' : null,
                    'aspect_ratio' => 'wide',
                    'size' => 'large',
                    'mature' => 'false',
                    'page_size' => 20, // the most allowed without an Openverse key
                ]));
        } catch (Throwable $e) {
            Log::warning('Openverse search failed: ' . $e->getMessage());

            return null;
        }
        if (!$response->successful()) {
            Log::warning('Openverse search failed: HTTP ' . $response->status());

            return null;
        }

        return collect($response->json('results', []))
            ->filter(fn ($p) => !empty($p['url']) && preg_match('/\.jpe?g$/i', parse_url($p['url'], PHP_URL_PATH) ?? '')
                // scanned pages of old books are not news pictures
                && !preg_match('/internet archive book images|(^|\|)image from page/i', ($p['creator'] ?? '') . '|' . ($p['title'] ?? '')))
            ->map(fn ($p) => [
                'id' => 'openverse:' . $p['id'],
                'url' => self::wikimediaResized($p['url'], (int) ($p['width'] ?? 0)),
                'credit' => Str::limit((string) ($p['creator'] ?: 'Openverse'), 140, ''),
                'credit_url' => (string) ($p['foreign_landing_url'] ?? 'https://openverse.org'),
                'alt' => (string) ($p['title'] ?? ''),
            ])->values()->all();
    }

    /** Wikimedia originals can be 5000 px / 10 MB: ask for its 1280 px copy instead. */
    private static function wikimediaResized(string $url, int $width): string
    {
        if ($width > 1280 && preg_match('#^(https://upload\.wikimedia\.org/wikipedia/commons)/([0-9a-f]/[0-9a-f]{2})/([^/]+)$#', $url, $m)) {
            return "{$m[1]}/thumb/{$m[2]}/{$m[3]}/1280px-{$m[3]}";
        }

        return $url;
    }

    // Wikimedia and Openverse ask callers to say who they are
    private const USER_AGENT = 'InvestmentPoint/1.0 (https://www.investmentpoint.in; shreeinvestmentpoint@gmail.com)';

    /** Download a found photo: [bytes, extension], or null. Marks it as used so it isn't repeated soon. */
    public function download(array $photo): ?array
    {
        try {
            $file = Http::withUserAgent(self::USER_AGENT)->connectTimeout(5)->timeout(30)->get($photo['url']);
        } catch (Throwable $e) {
            Log::warning('Stock photo download failed: ' . $e->getMessage());

            return null;
        }
        if (!$file->successful() || $file->body() === '') {
            return null;
        }

        $used = Cache::get(self::USED_KEY, []);
        $used[] = $photo['id'];
        Cache::forever(self::USED_KEY, array_slice(array_values(array_unique($used)), -500));

        $type = (string) $file->header('Content-Type');

        return [$file->body(), str_contains($type, 'png') ? 'png' : (str_contains($type, 'webp') ? 'webp' : 'jpg')];
    }

    /**
     * A real photo for a news headline, saved on the public disk.
     *
     * @return array{path: string, credit: string, credit_url: string}|null
     */
    public function forHeadline(string $headline): ?array
    {
        // Auto-published news only uses the keyed libraries; otherwise it keeps its AI picture
        if (!$this->keyed()) {
            return null;
        }
        $photo = $this->find(self::queryFor($headline));
        [$data, $ext] = $photo ? ($this->download($photo) ?? [null, null]) : [null, null];
        if (!$data) {
            return null;
        }

        $path = \App\Support\ImageOptimizer::putFilled($data, $ext, 'headline-news/stock-' . str_replace(':', '-', $photo['id']) . '-' . Str::random(6));

        return ['path' => $path, 'credit' => $photo['credit'], 'credit_url' => $photo['credit_url']];
    }
}
