<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * AI pictures from free services only (Gemini, which needs billing for pictures, is used for text only).
 * Tried in this order until one works:
 *  1. Cloudflare Workers AI, FLUX.1 [schnell] (free plan: 10,000 neurons a day, about 100 pictures;
 *     resets at 5:30 AM India time). Needs CLOUDFLARE_ACCOUNT_ID + CLOUDFLARE_API_TOKEN.
 *  2. Pollinations.ai. With a free key (POLLINATIONS_API_KEY, from enter.pollinations.ai) the picture
 *     is clean; with no key a small "pollinations.ai" mark is added in a corner, so that one is only
 *     used where the admin sees the picture before saving it.
 *  3. The free FLUX.1 [schnell] Hugging Face Space (HF_TOKEN raises its small daily limit).
 */
class AiPictures
{
    public const SQUARE_FOR_WIDE = 'Square picture that will be cut to a wide 16:9 strip across its middle: the whole scene, the animal from the top of its head '
        . 'to its feet and the whole arrow, sits inside the middle half of the picture height; the top quarter is only empty sky and the bottom quarter only plain ground.';

    /** The service that made the last picture (cloudflare, pollinations, huggingface) */
    public ?string $provider = null;

    /**
     * @param bool $allowMarked also use the no-key Pollinations picture (it has a small logo in a corner)
     * @param bool $premium first try Cloudflare's FLUX.2 [klein] (Market Trend pictures): a real 16:9 picture that follows
     *                      the prompt far better (arrow direction, colours, the NSE sign); FLUX.1 and the others are the backup
     * @return array{0: string, 1: string} [binary, extension]
     * @throws RuntimeException with a message for the admin when every service fails
     */
    public function make(string $prompt, int $width, int $height, bool $allowMarked = true, bool $premium = false): array
    {
        $services = array_keys(array_filter([
            'cloudflare-flux2' => $premium && $this->cloudflareReady() && config('services.cloudflare_ai.premium_model'),
            'cloudflare' => $this->cloudflareReady(),
            'pollinations' => config('services.pollinations.enabled') && (config('services.pollinations.key') || $allowMarked),
            'huggingface' => true,
        ]));

        $first = null;
        foreach ($services as $service) {
            try {
                $picture = match ($service) {
                    'cloudflare-flux2' => $this->cloudflarePremium($prompt, $width, $height),
                    // A square picture cut to 16:9 later (ImageOptimizer::fill): a Market Trend scene drawn square lost the
                    // bear's head (27 Oct 2026), so the subjects are kept in the band that stays
                    'cloudflare' => $this->cloudflare($premium && $width > $height * 1.2 ? $prompt . ' ' . self::SQUARE_FOR_WIDE : $prompt),
                    'pollinations' => $this->pollinations($prompt, $width, $height),
                    default => $this->huggingFace($prompt, $width, $height),
                };
                $this->provider = $service;

                return $picture;
            } catch (RuntimeException $e) {
                $first ??= $e;
            } catch (Throwable $e) {
                Log::warning("AI picture ({$service}) failed: " . $e->getMessage());
                $first ??= new RuntimeException('Could not reach the free AI service. Please try again in a moment.');
            }
        }

        throw new RuntimeException(count($services) > 1
            ? $first->getMessage() . ' The other free AI services are also busy right now. Try again later, or use "Find real photo (free)".'
            : $first->getMessage());
    }

    private function cloudflareReady(): bool
    {
        return config('services.cloudflare_ai.account_id') && config('services.cloudflare_ai.token');
    }

    /**
     * Cloudflare Workers AI, FLUX.2 [klein] (services.cloudflare_ai.premium_model): the size asked for (16:9), sent as a
     * form; tried 4 times over about half a minute, as it sometimes answers "Internal server error" for a while (27 Oct 2026:
     * 3 tries 2 seconds apart all failed, and the square backup picture was used)
     */
    private function cloudflarePremium(string $prompt, int $width, int $height): array
    {
        $config = config('services.cloudflare_ai');
        // Sizes in steps of 16 pixels
        [$width, $height] = [intdiv($width, 16) * 16, intdiv($height, 16) * 16];
        $response = Http::withToken($config['token'])->connectTimeout(8)->timeout(90)->retry([3000, 10000, 20000], throw: false)->asMultipart()
            ->post("https://api.cloudflare.com/client/v4/accounts/{$config['account_id']}/ai/run/{$config['premium_model']}", [
                ['name' => 'prompt', 'contents' => $prompt],
                ['name' => 'width', 'contents' => (string) $width],
                ['name' => 'height', 'contents' => (string) $height],
            ]);

        $image = $response->json('result.image');
        if ($response->successful() && $image && ($data = base64_decode($image, true))) {
            return [$data, 'jpg'];
        }
        $error = (string) ($response->json('errors.0.message') ?? 'HTTP ' . $response->status());
        Log::warning('AI picture (Cloudflare FLUX.2) failed: ' . $error);

        throw new RuntimeException('The premium AI picture could not be made (' . $error . ').');
    }

    /** Cloudflare Workers AI (a square picture; the admin popup / news frame crops it to the right shape) */
    private function cloudflare(string $prompt): array
    {
        $config = config('services.cloudflare_ai');
        $response = Http::withToken($config['token'])->connectTimeout(8)->timeout(90)
            ->post("https://api.cloudflare.com/client/v4/accounts/{$config['account_id']}/ai/run/{$config['image_model']}", [
                'prompt' => $prompt,
                'steps' => 8, // FLUX schnell maximum = best quality (Cloudflare rejects a 'seed')
            ]);

        $image = $response->json('result.image');
        if ($response->successful() && $image && ($data = base64_decode($image, true))) {
            return [$data, 'jpg'];
        }

        $error = (string) ($response->json('errors.0.message') ?? 'HTTP ' . $response->status());
        Log::warning('AI picture (Cloudflare) failed: ' . $error);

        throw new RuntimeException(match (true) {
            // The allowance is for the whole Cloudflare account and resets at 00:00 UTC
            $response->status() === 429 || str_contains(strtolower($error), 'neuron') => "Cloudflare's free AI pictures for today are used up (they come back at 5:30 AM India time).",
            in_array($response->status(), [401, 403], true) => 'The Cloudflare API token was rejected. Check CLOUDFLARE_API_TOKEN (it needs the Workers AI permission).',
            default => 'The free AI could not create this picture (' . $error . '). Try other words.',
        });
    }

    private function pollinations(string $prompt, int $width, int $height, bool $retry = true): array
    {
        $key = config('services.pollinations.key');
        $query = ['width' => $width, 'height' => $height, 'seed' => random_int(1, 999999999), 'nologo' => 'true', 'model' => config('services.pollinations.model')];
        // With a key: the current API (clean picture). Without: the old no-account address (small logo, its own model)
        $url = $key ? 'https://gen.pollinations.ai/image/' : 'https://image.pollinations.ai/prompt/';
        $response = Http::withHeaders($key ? ['Authorization' => 'Bearer ' . $key] : [])->connectTimeout(8)->timeout(120)
            ->get($url . rawurlencode($prompt), $key ? $query : array_diff_key($query, ['model' => 1]));

        $type = (string) $response->header('Content-Type');
        if ($response->successful() && str_starts_with($type, 'image/') && $response->body() !== '') {
            return [$response->body(), str_contains($type, 'png') ? 'png' : (str_contains($type, 'webp') ? 'webp' : 'jpg')];
        }

        $error = (string) ($response->json('error.message') ?? $response->json('error') ?? 'HTTP ' . $response->status());
        // No key: one picture every 45 seconds per server, and a busy moment now and then ("Retry after 4s")
        if ($retry && preg_match('/retry after (\d+)\s*s/i', $error, $m) && (int) $m[1] <= 15) {
            sleep((int) $m[1] + 1);

            return $this->pollinations($prompt, $width, $height, false);
        }
        Log::warning('AI picture (Pollinations) failed: ' . $error);

        throw new RuntimeException(match (true) {
            $response->status() === 401 || $response->status() === 403 => 'Pollinations did not accept POLLINATIONS_API_KEY. Copy it again from enter.pollinations.ai.',
            in_array($response->status(), [402, 429], true) => "Pollinations' free AI pictures are at their limit right now (without a key: 1 picture every 45 seconds). Please try again in a minute.",
            default => 'The free Pollinations AI is busy right now. Please try again in a minute.',
        });
    }

    /** Gradio API of the FLUX.1 [schnell] Space: queue the job, then read the result stream. */
    private function huggingFace(string $prompt, int $width, int $height): array
    {
        $config = config('services.huggingface_ai');
        $space = rtrim((string) $config['space'], '/');
        $http = fn () => Http::timeout(120)->withHeaders($config['token'] ? ['Authorization' => 'Bearer ' . $config['token']] : []);

        // Inputs: prompt, seed, randomize seed, width, height, steps
        $eventId = $http()->post("{$space}/gradio_api/call/infer", ['data' => [$prompt, 0, true, $width, $height, 4]])->json('event_id');
        if (!$eventId) {
            throw new RuntimeException('The free Hugging Face AI is busy right now. Please try again in a minute.');
        }

        $stream = $http()->get("{$space}/gradio_api/call/infer/{$eventId}")->body();
        if (!preg_match('/event:\s*complete\s*\ndata:\s*(.+)/', $stream, $m)) {
            Log::info('AI picture (Hugging Face) did not complete: ' . substr($stream, 0, 300));
            throw new RuntimeException($config['token']
                ? 'The free Hugging Face limit for today is used up (or the service is busy). Try again later.'
                : 'The free no-account Hugging Face limit (about 3 pictures a day) is used up. Try again tomorrow.');
        }

        $url = json_decode($m[1], true)[0]['url'] ?? null;
        if (!$url || !str_starts_with($url, $space . '/')) {
            throw new RuntimeException('The free AI returned an unexpected result. Please try again.');
        }

        $file = $http()->get($url);
        if (!$file->successful()) {
            throw new RuntimeException('Could not download the AI picture. Please try again.');
        }
        $type = (string) $file->header('Content-Type');

        return [$file->body(), str_contains($type, 'png') ? 'png' : (str_contains($type, 'jpeg') ? 'jpg' : 'webp')];
    }
}
