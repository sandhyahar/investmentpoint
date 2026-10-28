<?php

namespace App\Support;

use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * "Schedule Date / Schedule Time" on the E-Paper and Media Article forms (admin/partials/schedule-fields):
 * entered in India time, saved in UTC as publish_at. Empty = shown as soon as it is saved.
 */
class Schedule
{
    public const RULES = [
        'publish_date' => 'nullable|date',
        'publish_time' => 'nullable|date_format:H:i',
        'publish_at' => 'nullable|date',
    ];

    public const MESSAGES = ['publish_time.date_format' => 'Enter the time like 09:30.'];

    /**
     * The moment readers (and Blogger) get it, in UTC; null = at once. A date without a time = 12:00 AM.
     *
     * @throws ValidationException when only a time is given
     */
    public static function publishAt(array $data): ?Carbon
    {
        if (empty($data['publish_date']) && !empty($data['publish_time'])) {
            throw ValidationException::withMessages(['publish_date' => 'Choose the schedule date too (or clear the time).']);
        }
        $when = !empty($data['publish_date']) ? trim($data['publish_date'] . ' ' . ($data['publish_time'] ?? '00:00')) : ($data['publish_at'] ?? null);

        return $when ? Carbon::parse($when, 'Asia/Kolkata')->utc() : null;
    }
}
