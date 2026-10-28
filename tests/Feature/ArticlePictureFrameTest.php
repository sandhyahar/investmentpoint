<?php

namespace Tests\Feature;

use App\Models\TechnicalAnalysis;
use App\Models\User;
use App\Support\ImageOptimizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Article pictures (a chosen file or an AI picture) are saved 16:9 with the whole picture inside: nothing is cut.
 * Technical Analysis pictures keep their own shape instead (and unframe() takes the bands off older ones).
 */
class ArticlePictureFrameTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        if (!function_exists('imagecreatetruecolor')) {
            $this->markTestSkipped('Needs the GD extension.');
        }
    }

    /** A JPEG with a red block in each corner area, so a cut side would lose one */
    private function picture(int $w, int $h): string
    {
        $image = imagecreatetruecolor($w, $h);
        imagefill($image, 0, 0, imagecolorallocate($image, 30, 60, 160));
        $red = imagecolorallocate($image, 230, 20, 20);
        $box = (int) (min($w, $h) * .1);
        foreach ([[0, 0], [$w - $box, 0], [0, $h - $box], [$w - $box, $h - $box]] as [$x, $y]) {
            imagefilledrectangle($image, $x, $y, $x + $box - 1, $y + $box - 1, $red);
        }
        ob_start();
        imagejpeg($image, null, 92);

        return (string) ob_get_clean();
    }

    private static function isRed(\GdImage $image, int $x, int $y): bool
    {
        $c = imagecolorat($image, $x, $y);

        return (($c >> 16) & 0xFF) > 180 && (($c >> 8) & 0xFF) < 80 && ($c & 0xFF) < 80;
    }

    public function test_any_shape_becomes_16_by_9_with_all_four_corners_of_the_picture_still_there(): void
    {
        foreach ([[1024, 1024], [900, 1400], [2000, 700]] as [$w, $h]) {
            [$bytes, $ext] = ImageOptimizer::frame($this->picture($w, $h), 'jpg');
            $framed = imagecreatefromstring($bytes);

            $this->assertSame('jpg', $ext);
            $this->assertSame([1200, 675], [imagesx($framed), imagesy($framed)], "{$w}x{$h}");

            // Where the whole picture sits inside the frame
            $scale = min(1200 / $w, 675 / $h);
            [$pw, $ph] = [(int) round($w * $scale), (int) round($h * $scale)];
            [$left, $top] = [(int) ((1200 - $pw) / 2), (int) ((675 - $ph) / 2)];
            $in = (int) (min($pw, $ph) * .05); // the middle of each corner block
            foreach ([[$left + $in, $top + $in], [$left + $pw - $in, $top + $in], [$left + $in, $top + $ph - $in], [$left + $pw - $in, $top + $ph - $in]] as [$x, $y]) {
                $this->assertTrue(self::isRed($framed, $x, $y), "{$w}x{$h}: the corner at {$x},{$y} was cut");
            }
        }
    }

    public function test_a_16_by_9_picture_keeps_its_shape_and_a_small_one_is_not_blown_up(): void
    {
        [$bytes] = ImageOptimizer::frame($this->picture(1920, 1080), 'jpg');
        $size = getimagesizefromstring($bytes);
        $this->assertSame([1200, 675], [$size[0], $size[1]]);

        [$bytes] = ImageOptimizer::frame($this->picture(400, 400), 'jpg');
        $size = getimagesizefromstring($bytes);
        $this->assertSame(400, $size[1] - ($size[1] - 400)); // the picture keeps its own 400 px height...
        $this->assertLessThanOrEqual(402, $size[1]);
        $this->assertEqualsWithDelta(16 / 9, $size[0] / $size[1], 0.01); // ...inside a 16:9 frame
    }

    public function test_a_technical_analysis_picture_keeps_its_own_shape(): void
    {
        Storage::fake('public');
        $this->actingAs(User::forceCreate(['name' => 'Administrator', 'username' => 'admin-frame', 'email' => 'admin-frame@example.com', 'role' => 'admin', 'password' => bcrypt('secret')]));

        // A wide banner: no 16:9 frame, no blurred bands (the home cards show it whole)
        $this->post(route('admin.technical-analysis.store'), [
            'title' => 'SBI Cards: Support, Resistance & Key Levels', 'support' => '618', 'resistance' => '660',
            'description' => 'SBI Cards key levels.', 'content' => "First paragraph.\n\nSecond paragraph.", 'status' => 'active',
            'image' => UploadedFile::fake()->createWithContent('wide-banner.jpg', $this->picture(1200, 500)),
        ])->assertSessionHasNoErrors()->assertRedirect();

        $path = TechnicalAnalysis::firstOrFail()->image;
        $size = getimagesizefromstring(Storage::disk('public')->get($path));
        $this->assertSame([1200, 500], [$size[0], $size[1]]);
    }

    /** A photo-like picture (fine detail everywhere, as real pictures have) */
    private function photo(int $w, int $h): string
    {
        mt_srand($w * 7 + $h);
        $image = imagecreatetruecolor($w, $h);
        for ($y = 0; $y < $h; $y += 4) {
            for ($x = 0; $x < $w; $x += 4) {
                $c = imagecolorallocate($image, mt_rand(40, 220), (int) (120 + 100 * sin($x / 40)), (int) (120 + 100 * cos($y / 30)));
                imagefilledrectangle($image, $x, $y, $x + 3, $y + 3, $c);
            }
        }
        ob_start();
        imagejpeg($image, null, 92);

        return (string) ob_get_clean();
    }

    public function test_business_news_pictures_fill_the_16_by_9_box_without_bands(): void
    {
        // A square AI picture: its middle, 16:9, at its own width (not blown up)
        [$bytes, $ext] = ImageOptimizer::fill($this->photo(1024, 1024), 'jpg');
        $this->assertSame('jpg', $ext);
        $this->assertSame([1024, 576], array_slice(getimagesizefromstring($bytes), 0, 2));
        $this->assertNull(ImageOptimizer::unframe($bytes)); // no blurred bands in it
        // A big tall photo: 1200 x 675 at most
        [$bytes] = ImageOptimizer::fill($this->photo(1500, 2000), 'jpg');
        $this->assertSame([1200, 675], array_slice(getimagesizefromstring($bytes), 0, 2));
        // A picture that is 16:9 already: only made web-sized
        [$bytes] = ImageOptimizer::fill($this->photo(1920, 1080), 'jpg');
        $this->assertSame([1200, 675], array_slice(getimagesizefromstring($bytes), 0, 2));
    }

    public function test_unframe_takes_the_blurred_bands_off_and_leaves_other_pictures_alone(): void
    {
        // Framed wide / square pictures come back at their own shape (a pixel or two less at most)
        foreach ([[1200, 500, 1200, 500], [1024, 1024, 675, 675]] as [$w, $h, $wantW, $wantH]) {
            [$framed] = ImageOptimizer::frame($this->photo($w, $h), 'jpg');
            $result = ImageOptimizer::unframe($framed);
            $this->assertNotNull($result, "{$w}x{$h}");
            $size = getimagesizefromstring($result[0]);
            $this->assertEqualsWithDelta($wantW, $size[0], 4, "{$w}x{$h}: width");
            $this->assertEqualsWithDelta($wantH, $size[1], 4, "{$w}x{$h}: height");
        }
        // A plain 16:9 photo and a picture of another shape: no bands, nothing done
        $this->assertNull(ImageOptimizer::unframe($this->photo(1200, 675)));
        $this->assertNull(ImageOptimizer::unframe($this->photo(1200, 500)));
    }
}
