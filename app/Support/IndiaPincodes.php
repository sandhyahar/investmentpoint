<?php

namespace App\Support;

/**
 * Indian states -> cities (India Post districts) -> PIN codes, for the State / City / PIN code drop-downs
 * of the postal address (Weekly Gujarati News Paper). The list is public/data/india-pincodes.json, built
 * from India Post's "All India Pincode Directory" (data.gov.in) by resources/data/build-india-pincodes.php.
 */
class IndiaPincodes
{
    public const FILE = 'data/india-pincodes.json';

    private static ?array $data = null;

    /** @return array<string, array<string, string[]>> */
    public static function all(): array
    {
        return self::$data ??= json_decode(file_get_contents(public_path(self::FILE)), true, 512, JSON_THROW_ON_ERROR);
    }

    /** Is this PIN code one of this city's, and the city one of this state's? */
    public static function matches(?string $state, ?string $city, ?string $pincode): bool
    {
        return in_array((string) $pincode, self::all()[$state][$city] ?? [], true);
    }

    /** The file's address for the browser (changes when the list is rebuilt, so no stale copy is used). */
    public static function url(): string
    {
        return asset(self::FILE) . '?v=' . filemtime(public_path(self::FILE));
    }
}
