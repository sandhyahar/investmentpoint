<?php

namespace App\Http\Controllers\Admin\Concerns;

use App\Models\PressContact;
use App\Services\ArticleDistributor;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * "Send this article" box on the Market Trend / Technical Analysis form: rows of
 * channel name + link and name + email, typed right on the form. The rows are remembered
 * (shared by both forms) and, when "Send this article" is ticked, the saved article goes to
 * all of them after the page has answered, so saving stays fast.
 */
trait SendsToPress
{
    /** "Attach files" limits: Gmail and most providers refuse emails over 25 MB (files grow ~1/3 when emailed). */
    private static int $pressMaxFiles = 10;
    private static int $pressMaxFileKb = 10240;
    private static int $pressMaxTotalKb = 18432;

    /** @return array{channels: array, emails: array, send: bool, present: bool} */
    protected function validatePress(Request $request): array
    {
        // The form sends all rows as one JSON field (press_json), so long imported lists aren't cut
        // short by PHP's max_input_vars; without JavaScript the row fields themselves are used
        if ($request->filled('press_json')) {
            $rows = json_decode((string) $request->input('press_json'), true);
            if (is_array($rows)) {
                $request->merge([
                    'channels' => array_values(array_filter((array) ($rows['channels'] ?? []), 'is_array')),
                    'emails' => array_values(array_filter((array) ($rows['emails'] ?? []), 'is_array')),
                ]);
            }
        }

        $request->validate([
            'channels' => 'nullable|array|max:300',
            'channels.*.name' => 'nullable|string|max:150',
            'channels.*.url' => 'nullable|url:http,https|max:500',
            'emails' => 'nullable|array|max:5000',
            'emails.*.name' => 'nullable|string|max:150',
            'emails.*.email' => 'nullable|email|max:190',
            'send_press' => 'nullable|boolean',
            // "Attach files": any type (Word, PDF, pictures, text...), sent with every email
            'press_files' => [
                'nullable', 'array', 'max:' . self::$pressMaxFiles,
                function ($attribute, $files, $fail) {
                    $total = collect($files)->sum(fn ($f) => $f instanceof UploadedFile ? $f->getSize() : 0);
                    if ($total > self::$pressMaxTotalKb * 1024) {
                        $fail('The attached files are too big together: ' . round($total / 1048576, 1) . ' MB. Keep them under ' . (self::$pressMaxTotalKb / 1024) . ' MB in total, or email providers will refuse the email.');
                    }
                },
            ],
            'press_files.*' => 'file|max:' . self::$pressMaxFileKb,
        ], [
            'channels.*.url.url' => 'Each channel link must be a full web address starting with https://',
            'emails.*.email.email' => 'Please check the email addresses: one of them is not valid.',
            'press_files.max' => 'You can attach up to ' . self::$pressMaxFiles . ' files.',
            'press_files.*.max' => 'Each attached file must be under ' . (self::$pressMaxFileKb / 1024) . ' MB.',
            'press_files.*.file' => 'One of the attached files did not upload. Please pick it again.',
            'press_files.*.uploaded' => 'One of the attached files did not upload (it may be too big). Please pick it again.',
        ]);

        $channels = collect($request->input('channels', []))
            ->filter(fn ($row) => filled($row['url'] ?? null))
            ->map(fn ($row) => ['name' => trim($row['name'] ?? '') ?: parse_url($row['url'], PHP_URL_HOST), 'url' => trim($row['url'])])
            ->unique('url')->values()->all();
        $emails = collect($request->input('emails', []))
            ->filter(fn ($row) => filled($row['email'] ?? null))
            ->map(fn ($row) => ['name' => trim($row['name'] ?? '') ?: Str::before(trim($row['email']), '@'), 'email' => strtolower(trim($row['email']))])
            ->unique('email')->values()->all();

        return [
            'channels' => $channels,
            'emails' => $emails,
            'files' => array_values(array_filter((array) $request->file('press_files', []), fn ($f) => $f instanceof UploadedFile && $f->isValid())),
            'send' => $request->boolean('send_press'),
            'present' => $request->has('press_list'), // the box was on the form (don't wipe the list otherwise)
        ];
    }

    /** Remember the rows for next time (same list on both forms). */
    protected function savePressList(array $press): void
    {
        if (!$press['present']) {
            return;
        }

        DB::transaction(function () use ($press) {
            PressContact::channels()->whereNotIn('url', array_column($press['channels'], 'url') ?: [''])->delete();
            foreach ($press['channels'] as $row) {
                PressContact::updateOrCreate(['type' => 'channel', 'url' => $row['url']], ['name' => mb_substr($row['name'], 0, 150), 'active' => true]);
            }

            PressContact::customers()->whereNotIn('email', array_column($press['emails'], 'email') ?: [''])->delete();
            foreach ($press['emails'] as $row) {
                PressContact::updateOrCreate(['type' => 'customer', 'email' => $row['email']], ['name' => mb_substr($row['name'], 0, 150), 'active' => true]);
            }
        });
    }

    /** @return string text added to the "saved" message */
    protected function sendToPress(Model $article, string $type, array $press): string
    {
        $this->savePressList($press);

        $files = $press['files'] ?? [];
        if (!$press['send']) {
            return $files ? ' The attached files were not sent: tick "Send this article" to email them.' : '';
        }
        if ($article->status !== 'active') {
            return ' Not sent: the article is Inactive, so its link would not open.';
        }

        $count = PressContact::active()->count();
        if (!$count) {
            return ' Not sent: add at least one channel link or email first.';
        }

        // Keep the attached files until the emails have gone out (sending runs after the page has
        // answered, when the uploaded temp files are already gone), then delete them
        $dir = 'press-attachments/' . Str::uuid();
        $attachments = collect($files)->map(fn (UploadedFile $f) => [
            'path' => Storage::disk('local')->path($f->storeAs($dir, Str::random(8) . '-' . $this->safeFileName($f), 'local')),
            'name' => $this->safeFileName($f),
            'mime' => $f->getClientMimeType() ?: null,
        ])->all();

        $class = get_class($article);
        $id = $article->getKey();
        dispatch(function () use ($class, $id, $type, $attachments, $dir) {
            try {
                if ($article = $class::find($id)) {
                    app(ArticleDistributor::class)->send($article, $type, PressContact::active()->get(), $attachments);
                }
            } finally {
                Storage::disk('local')->deleteDirectory($dir);
            }
        })->afterResponse();

        $withFiles = $attachments ? ' with ' . count($attachments) . ' attached ' . Str::plural('file', count($attachments)) : '';

        return " Sending it to {$count} " . ($count === 1 ? 'email' : 'emails') . $withFiles . ' (results show under "Send this article" when you edit it).';
    }

    /** The file's own name (e.g. "Report Oct.pdf"), without folders or characters email programs choke on. */
    private function safeFileName(UploadedFile $file): string
    {
        $name = trim(preg_replace('/[\x00-\x1F\x7F\/\\\\:*?"<>|]+/u', ' ', basename($file->getClientOriginalName())));

        return mb_substr($name, -150) ?: 'attachment.' . ($file->guessExtension() ?: 'bin');
    }
}
