<?php

namespace App\Console\Commands;

use App\Models\EditorArticle;
use App\Models\LatestNews;
use App\Models\News;
use App\Models\PopularNews;
use App\Support\ImageOptimizer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Business News (Latest / Most Popular News) pictures were saved inside a 16:9 frame with blurred bands
 * (ImageOptimizer::frame); since 19 Oct 2026 they fill the 16:9 box instead (ImageOptimizer::fill), and Market Trend
 * and Editor's View pictures since 23 Oct 2026 (--section=market-trend / editors-view).
 * This changes the pictures saved before, in place (same file name, so every link keeps working): the
 * blurred bands are taken off and the picture's middle fills the box. The files as they were are copied to
 * storage/app/unframe-backup/ first. Pictures without bands are left as they are; safe to run again.
 */
class FillBusinessNewsImages extends Command
{
    protected $signature = 'images:fill-business-news {--section=business-news : business-news, market-trend or editors-view}
        {--dry-run : Only list what would change}';

    protected $description = 'Business News / Market Trend / Editor\'s View pictures: blurred bands off, the picture filling the 16:9 box';

    public function handle(): int
    {
        $disk = Storage::disk('public');
        $models = match ($this->option('section')) {
            'market-trend' => [News::class],
            'editors-view' => [EditorArticle::class],
            default => [LatestNews::class, PopularNews::class],
        };
        $paths = collect($models)->flatMap(fn ($model) => $model::query()->whereNotNull('image')->pluck('image'))
            ->filter(fn ($p) => !preg_match('#^https?://#', $p) && $disk->exists($p))->unique()->values();
        $done = 0;
        foreach ($paths as $path) {
            if (is_file(storage_path('app/unframe-backup/' . $path))) {
                $this->line("  {$path}: done before, left as it is");
                continue;
            }
            $data = $disk->get($path);
            $unframed = ImageOptimizer::unframe($data);
            $size = @getimagesizefromstring($data);
            $wide = $size && abs($size[0] / $size[1] - 16 / 9) / (16 / 9) < 0.02;
            if (!$unframed && ($wide || !$size)) {
                $this->line("  {$path}: 16:9, no blurred bands, left as it is");
                continue;
            }
            // Bands off, then the middle filling the box; an older picture of another shape (square AI) only filled
            [$bytes] = ImageOptimizer::fill($unframed[0] ?? $data, $unframed ? 'jpg' : pathinfo($path, PATHINFO_EXTENSION));
            $size = getimagesizefromstring($bytes);
            $this->line("  {$path}: " . ($unframed ? "cut {$unframed[2]}, " : '') . "filled -> {$size[0]} x {$size[1]}");
            if ($this->option('dry-run')) {
                continue;
            }
            $backup = storage_path('app/unframe-backup/' . $path);
            if (!is_file($backup)) {
                @mkdir(dirname($backup), 0755, true);
                copy($disk->path($path), $backup);
            }
            $disk->put($path, $bytes); // JPEG data; browsers go by the data, so a .png / .webp name is fine
            $done++;
        }
        $this->info(($this->option('dry-run') ? 'Dry run: ' : '') . "{$done} picture(s) changed of {$paths->count()}.");

        return self::SUCCESS;
    }
}
