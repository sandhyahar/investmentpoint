<?php

namespace App\Support;

/**
 * Link preview picture (WhatsApp / Facebook) for a writer's blog: the site banner (images/logo-default.png)
 * with the writer's photo in the place of the bull, on a 1200 x 630 card (the shape WhatsApp shows whole).
 * Only imagecopyresampled is used: the live server's GD turns pictures black when imagescale enlarges.
 */
class ShareCard
{
    public const WIDTH = 1200;
    public const HEIGHT = 630;

    /** Width of the photo on the left of the card */
    private const PHOTO_WIDTH = 440;

    /** JPEG bytes of the card, or null when GD or the pictures can't be read */
    public static function make(string $photoData): ?string
    {
        $banner = public_path('images/logo-default.png');
        if (!function_exists('imagecreatefromstring') || !is_file($banner)) {
            return null;
        }
        $photo = @imagecreatefromstring($photoData);
        $logo = @imagecreatefrompng($banner);
        if (!$photo || !$logo) {
            return null;
        }

        $card = imagecreatetruecolor(self::WIDTH, self::HEIGHT);
        imagefill($card, 0, 0, imagecolorallocate($card, 255, 255, 255));

        // Writer photo fills the left panel: cropped to the panel's shape, keeping the top (the face)
        [$pw, $ph] = [imagesx($photo), imagesy($photo)];
        $panelRatio = self::PHOTO_WIDTH / self::HEIGHT;
        if ($pw / $ph > $panelRatio) {
            $cropW = (int) round($ph * $panelRatio);
            imagecopyresampled($card, $photo, 0, 0, (int) (($pw - $cropW) / 2), 0, self::PHOTO_WIDTH, self::HEIGHT, $cropW, $ph);
        } else {
            $cropH = (int) round($pw / $panelRatio);
            imagecopyresampled($card, $photo, 0, 0, 0, 0, self::PHOTO_WIDTH, self::HEIGHT, $pw, $cropH);
        }

        // The banner's text part ("Investment Point / NEWS • MARKETS • INSIGHTS"), without the bull
        [$x0, $y0, $x1, $y1] = self::textBox($logo);
        $textW = $x1 - $x0 + 1;
        $textH = $y1 - $y0 + 1;
        $room = self::WIDTH - self::PHOTO_WIDTH - 2 * 56;
        $scale = $room / $textW;
        $dw = (int) round($textW * $scale);
        $dh = (int) round($textH * $scale);
        imagecopyresampled($card, $logo, self::PHOTO_WIDTH + 56, (int) ((self::HEIGHT - $dh) / 2), $x0, $y0, $dw, $dh, $textW, $textH);

        ob_start();
        imageinterlace($card, true);
        imagejpeg($card, null, 88);

        return (string) ob_get_clean() ?: null;
    }

    /**
     * Box around the banner's text: everything right of the first fully white gap after the bull,
     * trimmed of white space. [left, top, right, bottom]
     */
    private static function textBox(\GdImage $logo): array
    {
        [$w, $h] = [imagesx($logo), imagesy($logo)];
        $inked = function (int $x, int $y) use ($logo): bool {
            $c = imagecolorat($logo, $x, $y);
            $alpha = ($c >> 24) & 0x7F;

            return $alpha < 100 && min(($c >> 16) & 255, ($c >> 8) & 255, $c & 255) < 200;
        };
        $columnInked = function (int $x) use ($h, $inked): bool {
            for ($y = 0; $y < $h; $y++) {
                if ($inked($x, $y)) {
                    return true;
                }
            }

            return false;
        };

        // Past the bull (left part of the banner), the first empty column, then the first inked one
        $x = (int) ($w * 0.12);
        while ($x < $w && $columnInked($x)) {
            $x++;
        }
        while ($x < $w && !$columnInked($x)) {
            $x++;
        }
        $left = $x;
        $right = $w - 1;
        while ($right > $left && !$columnInked($right)) {
            $right--;
        }
        $top = 0;
        $bottom = $h - 1;
        $rowInked = function (int $y) use ($left, $right, $inked): bool {
            for ($x = $left; $x <= $right; $x++) {
                if ($inked($x, $y)) {
                    return true;
                }
            }

            return false;
        };
        while ($top < $bottom && !$rowInked($top)) {
            $top++;
        }
        while ($bottom > $top && !$rowInked($bottom)) {
            $bottom--;
        }

        return [$left, $top, $right, $bottom];
    }
}
