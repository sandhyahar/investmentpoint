<?php

namespace App\Console\Commands;

use App\Support\ImageOptimizer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Shrinks pictures already uploaded to storage/app/public: too-wide or heavy files are resized and
 * re-compressed in place (same name and format, so every link keeps working). New uploads are
 * optimized automatically (App\Support\ImageOptimizer); run this once after copying old files over.
 */
class OptimizeImages extends Command
{
    protected $signature = 'images:optimize';

    protected $description = 'Resize and re-compress large pictures in storage/app/public';

    public function handle(): int
    {
        $disk = Storage::disk('public');
        $saved = 0;
        $count = 0;
        foreach ($disk->allFiles() as $path) {
            // Newspaper pages are read on screen: keep them wider and sharper
            $wide = str_starts_with($path, 'digital-newspaper/') || str_starts_with($path, 'epaper/');
            $bytes = ImageOptimizer::shrinkInPlace($disk->path($path), $wide ? 2000 : 1600, $wide ? 85 : 80);
            if ($bytes > 0) {
                $count++;
                $saved += $bytes;
                $this->line(sprintf('  %-70s -%d KB', $path, $bytes / 1024));
            }
        }
        $this->info(sprintf('%d picture(s) optimized, %.1f MB saved.', $count, $saved / 1048576));

        return self::SUCCESS;
    }
}
