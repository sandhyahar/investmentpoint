<?php

namespace App\Console\Commands;

use App\Models\TechnicalAnalysis;
use App\Support\ImageOptimizer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Technical Analysis pictures were saved inside a 16:9 frame with blurred bands (ImageOptimizer::frame);
 * since 19 Oct 2026 they keep their own shape. This takes the blurred bands off the pictures saved before,
 * in place (same file name, so every link keeps working). The files as they were are copied to
 * storage/app/unframe-backup/ first. Pictures without bands are left as they are; safe to run again.
 */
class UnframeAnalysisImages extends Command
{
    protected $signature = 'images:unframe-analysis {--dry-run : Only list what would be cut}';

    protected $description = 'Take the blurred bands off Technical Analysis pictures (keep their own shape)';

    public function handle(): int
    {
        $disk = Storage::disk('public');
        $paths = TechnicalAnalysis::query()->whereNotNull('image')->pluck('image')
            ->filter(fn ($p) => !preg_match('#^https?://#', $p) && $disk->exists($p))->unique()->values();
        $done = 0;
        foreach ($paths as $path) {
            $result = ImageOptimizer::unframe($disk->get($path));
            if (!$result) {
                $this->line("  {$path}: no blurred bands, left as it is");
                continue;
            }
            [$bytes, , $cut] = $result;
            $size = getimagesizefromstring($bytes);
            $this->line("  {$path}: cut {$cut} -> {$size[0]} x {$size[1]}");
            if ($this->option('dry-run')) {
                continue;
            }
            $backup = storage_path('app/unframe-backup/' . $path);
            if (!is_file($backup)) {
                @mkdir(dirname($backup), 0755, true);
                copy($disk->path($path), $backup);
            }
            $disk->put($path, $bytes); // framed pictures are always .jpg
            $done++;
        }
        $this->info(($this->option('dry-run') ? 'Dry run: ' : '') . "{$done} picture(s) changed of {$paths->count()}.");

        return self::SUCCESS;
    }
}
