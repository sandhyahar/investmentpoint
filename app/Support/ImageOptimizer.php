<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Makes pictures web-sized before they are saved: at most $maxWidth pixels wide and re-compressed
 * (a 4 MB phone photo becomes ~150 KB). Photos are saved as JPEG, not WebP, so WhatsApp / Facebook
 * link previews of articles keep working; pictures with transparency stay PNG.
 * Without the GD extension, or for files it can't read, the original is kept unchanged.
 */
class ImageOptimizer
{
    /** Already small enough to keep as it is (no needless quality loss) */
    private const SMALL_BYTES = 200 * 1024;

    /**
     * Optimized copy of raw image bytes.
     *
     * @return array{0: string, 1: string} [bytes, file extension]
     */
    public static function optimize(string $data, string $ext, int $maxWidth = 1600, int $quality = 80): array
    {
        $ext = strtolower($ext) === 'jpeg' ? 'jpg' : strtolower($ext);
        $size = @getimagesizefromstring($data);
        if (!$size || !function_exists('imagecreatefromstring') || $ext === 'gif') {
            return [$data, $ext];
        }
        if ($size[0] <= $maxWidth && strlen($data) <= self::SMALL_BYTES) {
            return [$data, $ext];
        }

        $image = @imagecreatefromstring($data);
        if (!$image) {
            return [$data, $ext];
        }
        if ($size[0] > $maxWidth) {
            $image = self::shrink($image, $maxWidth);
        }

        $keepAlpha = $ext === 'png' && self::hasTransparency($image);
        ob_start();
        if ($keepAlpha) {
            imagesavealpha($image, true);
            imagepng($image, null, 8);
        } else {
            imageinterlace($image, true); // progressive JPEG: shows a preview while loading
            imagejpeg(self::onWhite($image), null, $quality);
        }
        $out = (string) ob_get_clean();

        // Never make a file bigger than it was
        return strlen($out) > 0 && strlen($out) < strlen($data) ? [$out, $keepAlpha ? 'png' : 'jpg'] : [$data, $ext];
    }

    /** The shape every article picture is saved in (16:9), in pixels */
    public const FRAME = [1200, 675];

    /**
     * The whole picture inside a 16:9 frame, so every article picture has the same shape on the site and
     * no side is ever cut. A picture that is already 16:9 is only made web-sized. Any other shape (a square
     * AI picture, a tall phone photo, a very wide chart) is fitted inside the frame, and the space left over
     * is filled with a soft, blurred copy of the same picture. A small picture is not blown up: its frame
     * is only as big as it needs.
     *
     * @return array{0: string, 1: string} [bytes, file extension]
     */
    public static function frame(string $data, string $ext, int $quality = 82): array
    {
        [$width, $height] = self::FRAME;
        $ext = strtolower($ext) === 'jpeg' ? 'jpg' : strtolower($ext);
        $size = @getimagesizefromstring($data);
        if (!$size || !function_exists('imagecreatefromstring') || $ext === 'gif') {
            return [$data, $ext];
        }

        $ratio = $width / $height;
        if (abs($size[0] / $size[1] - $ratio) / $ratio < 0.02) {
            return self::optimize($data, $ext, $width, $quality); // the right shape already
        }

        $image = @imagecreatefromstring($data);
        if (!$image) {
            return [$data, $ext];
        }
        $image = self::onWhite($image);
        [$w, $h] = [imagesx($image), imagesy($image)];

        // The frame: 16:9, big enough for the picture at its own size, at most 1200 x 675
        $frameW = (int) min($width, max($w, ceil($h * $ratio)));
        $frameH = (int) round($frameW / $ratio);
        $scale = min($frameW / $w, $frameH / $h);
        [$pictureW, $pictureH] = [max(1, (int) round($w * $scale)), max(1, (int) round($h * $scale))];

        $canvas = self::backdrop($image, $frameW, $frameH);
        imagecopyresampled($canvas, $image, (int) (($frameW - $pictureW) / 2), (int) (($frameH - $pictureH) / 2), 0, 0, $pictureW, $pictureH, $w, $h);

        ob_start();
        imageinterlace($canvas, true);
        imagejpeg($canvas, null, $quality);
        $out = (string) ob_get_clean();

        return $out !== '' ? [$out, 'jpg'] : [$data, $ext];
    }

    /**
     * The frame's backdrop: the middle of the picture stretched over a $frameW x $frameH frame, blurred (shrunk
     * to a few pixels and smoothly enlarged again) and a little darker; drawn at $outW x $outH (default: the frame).
     */
    private static function backdrop(\GdImage $image, int $frameW, int $frameH, ?int $outW = null, ?int $outH = null): \GdImage
    {
        [$w, $h] = [imagesx($image), imagesy($image)];
        $cover = max($frameW / $w, $frameH / $h);
        [$cropW, $cropH] = [$frameW / $cover, $frameH / $cover];
        $tiny = imagecreatetruecolor(48, 27);
        imagecopyresampled($tiny, $image, 0, 0, (int) (($w - $cropW) / 2), (int) (($h - $cropH) / 2), 48, 27, (int) $cropW, (int) $cropH);
        // ...enlarged in two steps with a blur in between, so no little squares show. imagecopyresampled, not
        // imagescale: the server's GD 2.3.3 returns an all-black picture when imagescale enlarges (bicubic)
        $soft = imagecreatetruecolor(240, 135);
        imagecopyresampled($soft, $tiny, 0, 0, 0, 0, 240, 135, 48, 27);
        for ($i = 0; $i < 8; $i++) {
            imagefilter($soft, IMG_FILTER_GAUSSIAN_BLUR);
        }
        $canvas = imagecreatetruecolor($outW ?? $frameW, $outH ?? $frameH);
        imagecopyresampled($canvas, $soft, 0, 0, 0, 0, $outW ?? $frameW, $outH ?? $frameH, 240, 135);
        imagefilter($canvas, IMG_FILTER_BRIGHTNESS, -30);

        return $canvas;
    }

    /**
     * The opposite of frame(): a framed picture (16:9 with blurred bands) back to the picture alone, its own
     * shape. The picture sits in the middle of the frame, so the bands are equal; their size is found by
     * redrawing the blurred backdrop from each possible middle part and keeping the one that matches the bands.
     * Null when the picture has no such bands (not framed, or it was 16:9 already).
     *
     * @return array{0: string, 1: string, 2: string}|null [bytes, 'jpg', what was cut: "top/bottom 87px"]
     */
    public static function unframe(string $data, int $quality = 88): ?array
    {
        $size = @getimagesizefromstring($data);
        $ratio = self::FRAME[0] / self::FRAME[1];
        if (!$size || !function_exists('imagecreatefromstring') || abs($size[0] / $size[1] - $ratio) / $ratio >= 0.02) {
            return null;
        }
        $image = @imagecreatefromstring($data);
        if (!$image) {
            return null;
        }
        [$w, $h] = [imagesx($image), imagesy($image)];

        // How well bands of $t pixels (top/bottom or left/right) of $src match the backdrop redrawn from its
        // middle part: mean difference per colour (0 = the same)
        $miss = function (\GdImage $src, bool $rows, int $t): float {
            [$cw, $ch] = [imagesx($src), imagesy($src)];
            $middle = $rows ? imagecrop($src, ['x' => 0, 'y' => $t, 'width' => $cw, 'height' => $ch - 2 * $t])
                            : imagecrop($src, ['x' => $t, 'y' => 0, 'width' => $cw - 2 * $t, 'height' => $ch]);
            $drawn = self::backdrop($middle, $cw, $ch);
            $band = $t - 1; // less its edge (mixed with the picture)
            [$sum, $n] = [0, 0];
            $step = max(1, (int) round($cw / 300));
            for ($i = 0; $i < $band; $i += $step) {
                foreach ([$i, ($rows ? $ch : $cw) - 1 - $i] as $line) {
                    for ($j = 0; $j < ($rows ? $cw : $ch); $j += $step * 2) {
                        [$x, $y] = $rows ? [$j, $line] : [$line, $j];
                        $a = imagecolorat($src, $x, $y);
                        $b = imagecolorat($drawn, $x, $y);
                        $sum += abs(($a >> 16 & 255) - ($b >> 16 & 255)) + abs(($a >> 8 & 255) - ($b >> 8 & 255)) + abs(($a & 255) - ($b & 255));
                        $n += 3;
                    }
                }
            }

            return $n ? $sum / $n : INF;
        };

        // 0. the bands are flat (almost no detail): how far flat lines reach in from both edges. The band is
        //    at most that wide (the picture's own edge may be flat too, so it can be less)
        $detail = function (bool $rows, int $i) use ($image, $w, $h): float {
            [$sum, $n] = [0, 0];
            for ($j = 2; $j < ($rows ? $w : $h) - 3; $j += 3) {
                [$a, $b] = $rows ? [imagecolorat($image, $j, $i), imagecolorat($image, $j + 1, $i)] : [imagecolorat($image, $i, $j), imagecolorat($image, $i, $j + 1)];
                $sum += abs(($a >> 8 & 255) - ($b >> 8 & 255));
                $n++;
            }

            return $sum / max(1, $n);
        };
        $flat = [];
        foreach ([true, false] as $rows) {
            $len = $rows ? $h : $w;
            [$a, $b] = [0, 0];
            while ($a < $len / 2 && $detail($rows, $a) < 3) $a++;
            while ($b < $len / 2 && $detail($rows, $len - 1 - $b) < 3) $b++;
            $flat[(int) $rows] = min($a, $b);
        }

        // 1. rough: on a quarter-size copy, every pixel there (4 here), up to the flat width
        $k = 4;
        $small = imagecreatetruecolor(intdiv($w, $k), intdiv($h, $k));
        imagecopyresampled($small, $image, 0, 0, 0, 0, intdiv($w, $k), intdiv($h, $k), $w, $h);
        $best = [INF, true, 0];
        // First near the end of the flat lines; when that finds nothing (a soft-focus photo: its own blurred
        // background makes the flat lines reach far into the picture), every band width
        foreach ([false, true] as $everywhere) {
            foreach ([true, false] as $rows) {
                if ($flat[(int) $rows] < 12) {
                    continue; // no flat bands on these sides
                }
                $limit = min(intdiv($flat[(int) $rows], $k) + 1, intdiv($rows ? $h : $w, 2 * $k) - 5);
                for ($t = $everywhere ? 3 : max(3, $limit - 24); $t <= $limit; $t++) {
                    $m = $miss($small, $rows, $t);
                    if ($m < $best[0]) {
                        $best = [$m, $rows, $t * $k];
                    }
                }
            }
            if ($best[0] <= 6) {
                break;
            }
        }
        // Bands that don't match any backdrop: not a framed picture
        if ($best[0] > 6) {
            return null;
        }
        // 2. exact: each pixel near it, full size
        [, $rows, $rough] = $best;
        $exact = [INF, $rough];
        for ($t = max(4, $rough - $k - 1); $t <= $rough + $k + 1; $t++) {
            $m = $miss($image, $rows, $t);
            if ($m < $exact[0]) {
                $exact = [$m, $t];
            }
        }
        $t = $exact[1] + 1; // one pixel more: the picture's edge row is mixed with the band in the JPEG

        $picture = $rows ? imagecrop($image, ['x' => 0, 'y' => $t, 'width' => $w, 'height' => $h - 2 * $t])
                         : imagecrop($image, ['x' => $t, 'y' => 0, 'width' => $w - 2 * $t, 'height' => $h]);
        ob_start();
        imageinterlace($picture, true);
        imagejpeg($picture, null, $quality);
        $out = (string) ob_get_clean();

        return $out !== '' ? [$out, 'jpg', ($rows ? 'top/bottom ' : 'left/right ') . $t . 'px'] : null;
    }

    /**
     * The picture filling a 16:9 box: its middle part, cut to 16:9 (a square AI picture loses a strip at the
     * top and bottom, a tall photo more). No blurred bands. At most 1200 x 675; a smaller one is not blown up.
     *
     * @return array{0: string, 1: string} [bytes, file extension]
     */
    public static function fill(string $data, string $ext, int $quality = 85): array
    {
        [$width, $height] = self::FRAME;
        $ext = strtolower($ext) === 'jpeg' ? 'jpg' : strtolower($ext);
        $size = @getimagesizefromstring($data);
        if (!$size || !function_exists('imagecreatefromstring') || $ext === 'gif') {
            return [$data, $ext];
        }
        $ratio = $width / $height;
        if (abs($size[0] / $size[1] - $ratio) / $ratio < 0.02) {
            return self::optimize($data, $ext, $width, $quality); // the right shape already
        }
        $image = @imagecreatefromstring($data);
        if (!$image) {
            return [$data, $ext];
        }
        $image = self::onWhite($image);
        [$w, $h] = [imagesx($image), imagesy($image)];

        // The biggest 16:9 part in the middle
        [$cropW, $cropH] = $w / $h > $ratio ? [(int) round($h * $ratio), $h] : [$w, (int) round($w / $ratio)];
        $outW = min($width, $cropW);
        $outH = (int) round($outW / $ratio);
        $canvas = imagecreatetruecolor($outW, $outH);
        // imagecopyresampled, not imagescale (the server's GD 2.3.3 bug, see backdrop())
        imagecopyresampled($canvas, $image, 0, 0, (int) (($w - $cropW) / 2), (int) (($h - $cropH) / 2), $outW, $outH, $cropW, $cropH);

        ob_start();
        imageinterlace($canvas, true);
        imagejpeg($canvas, null, $quality);
        $out = (string) ob_get_clean();

        return $out !== '' ? [$out, 'jpg'] : [$data, $ext];
    }

    /** Save raw bytes (a Business News AI / stock picture) filling a 16:9 box (see fill()). Returns the path. */
    public static function putFilled(string $data, string $ext, string $pathWithoutExt): string
    {
        [$data, $ext] = self::fill($data, self::safeExtension($ext));
        $path = $pathWithoutExt . '.' . $ext;
        Storage::disk('public')->put($path, $data);

        return $path;
    }

    /** Store an uploaded Business News picture filling a 16:9 box; returns its path. */
    public static function storeFilled(UploadedFile $file, string $folder): string
    {
        return self::putFilled((string) file_get_contents($file->getRealPath()), $file->extension(), trim($folder, '/') . '/' . Str::random(40));
    }

    /** Save raw bytes (an AI / stock picture) as an article picture: framed 16:9, nothing cut. Returns the path. */
    public static function putFramed(string $data, string $ext, string $pathWithoutExt): string
    {
        [$data, $ext] = self::frame($data, self::safeExtension($ext));
        $path = $pathWithoutExt . '.' . $ext;
        Storage::disk('public')->put($path, $data);

        return $path;
    }

    /** Store an uploaded article picture (chosen file or AI) framed 16:9, nothing cut; returns its path. */
    public static function storeFramed(UploadedFile $file, string $folder): string
    {
        return self::putFramed((string) file_get_contents($file->getRealPath()), $file->extension(), trim($folder, '/') . '/' . Str::random(40));
    }

    /** Save raw bytes (e.g. a downloaded / AI picture) optimized; returns the path on the public disk. */
    public static function put(string $data, string $ext, string $pathWithoutExt, int $maxWidth = 1600, int $quality = 80): string
    {
        [$data, $ext] = self::optimize($data, self::safeExtension($ext), $maxWidth, $quality);
        $path = $pathWithoutExt . '.' . $ext;
        // The disk does not throw: a full disk or a folder it cannot write to would leave a link to a missing picture
        if (!Storage::disk('public')->put($path, $data) || Storage::disk('public')->size($path) !== strlen($data)) {
            Storage::disk('public')->delete($path);
            throw new \RuntimeException('The picture could not be saved on the server (disk full or folder not writable).');
        }

        return $path;
    }

    /** Store an uploaded picture optimized in $folder on the public disk; returns its path. */
    public static function store(UploadedFile $file, string $folder, int $maxWidth = 1600, int $quality = 80): string
    {
        // Extension from what the file really is, never the name the uploader chose: a picture-looking
        // file called "x.html" or "x.svg" must not be saved (and served by the web server) as a web page
        $ext = self::safeExtension($file->extension());

        return self::put((string) file_get_contents($file->getRealPath()), $ext, trim($folder, '/') . '/' . Str::random(40), $maxWidth, $quality);
    }

    /**
     * Shrink a stored file in place, keeping its name and format (so database links stay valid).
     * Returns the bytes saved (0 when it was already fine).
     */
    public static function shrinkInPlace(string $absolutePath, int $maxWidth = 1600, int $quality = 80): int
    {
        $ext = strtolower(pathinfo($absolutePath, PATHINFO_EXTENSION));
        $before = (string) @file_get_contents($absolutePath);
        $size = @getimagesizefromstring($before);
        if (!$size || !in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true) || !function_exists('imagecreatefromstring')
            || ($size[0] <= $maxWidth && strlen($before) <= self::SMALL_BYTES)) {
            return 0;
        }
        $image = @imagecreatefromstring($before);
        if (!$image) {
            return 0;
        }
        if ($size[0] > $maxWidth) {
            $image = self::shrink($image, $maxWidth);
        }

        ob_start();
        if ($ext === 'png') {
            imagesavealpha($image, true);
            imagepng($image, null, 8);
        } elseif ($ext === 'webp') {
            if (function_exists('imagewebp')) {
                imagewebp($image, null, $quality);
            }
        } else {
            imageinterlace($image, true);
            imagejpeg(self::onWhite($image), null, $quality);
        }
        $after = (string) ob_get_clean();

        if ($after === '' || strlen($after) >= strlen($before)) {
            return 0;
        }
        file_put_contents($absolutePath, $after);

        return strlen($before) - strlen($after);
    }

    /**
     * The picture made $width pixels wide, keeping its shape (and any transparency). imagecopyresampled, not
     * imagescale: on the live server (GD 2.3.3) imagescale with IMG_BICUBIC returns false, so nothing was shrunk there.
     */
    private static function shrink(\GdImage $image, int $width): \GdImage
    {
        $w = imagesx($image);
        $h = imagesy($image);
        $height = max(1, (int) round($h * $width / $w));
        $small = imagecreatetruecolor($width, $height);
        imagealphablending($small, false);
        imagesavealpha($small, true);
        imagefill($small, 0, 0, imagecolorallocatealpha($small, 0, 0, 0, 127));
        imagecopyresampled($small, $image, 0, 0, 0, 0, $width, $height, $w, $h);

        return $small;
    }

    /** Only picture extensions are ever written to the public disk (anything else is saved as .jpg) */
    private static function safeExtension(?string $ext): string
    {
        $ext = strtolower((string) $ext) === 'jpeg' ? 'jpg' : strtolower((string) $ext);

        return in_array($ext, ['jpg', 'png', 'webp', 'gif'], true) ? $ext : 'jpg';
    }

    /** JPEG has no transparency: put see-through parts on white instead of black */
    private static function onWhite(\GdImage $image): \GdImage
    {
        if (imageistruecolor($image) && !self::hasTransparency($image)) {
            return $image;
        }
        $w = imagesx($image);
        $h = imagesy($image);
        $canvas = imagecreatetruecolor($w, $h);
        imagefill($canvas, 0, 0, imagecolorallocate($canvas, 255, 255, 255));
        imagecopy($canvas, $image, 0, 0, 0, 0, $w, $h);

        return $canvas;
    }

    /** Samples a grid of pixels for any transparency */
    private static function hasTransparency(\GdImage $image): bool
    {
        $w = imagesx($image);
        $h = imagesy($image);
        $step = max(1, (int) (min($w, $h) / 40));
        for ($x = 0; $x < $w; $x += $step) {
            for ($y = 0; $y < $h; $y += $step) {
                if ((imagecolorat($image, $x, $y) >> 24) & 0x7F) {
                    return true;
                }
            }
        }

        return false;
    }
}
