<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DigitalNewspaper;
use App\Services\BloggerPublisher;
use App\Services\EpaperByEmail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Admin -> E-Paper: the editions of one paper at a time (?edition=gujarati | hindi | english | weekly),
 * shown under E-Paper on the site. Each edition is uploaded as a PDF (turned into page images in the browser).
 *
 * The daily Gujarati / Hindi / English e-papers go straight to their Blogger blog by email (EpaperByEmail): Blogger
 * keeps the pictures, nothing is kept on the website server, and the website lists the edition like every edition
 * imported from the blog (a row holding only the Blogger links). When Google blocks those emails, the admin posts the
 * edition in Blogger itself and clicks "Get all editions from Blogger" (fromBlogger), which copies every post of the blog
 * that the website does not have yet, without copies of the same edition (newspaper:import also checks every 5 minutes).
 * The Weekly News Paper is kept on the website and posted to Blogger after saving (BloggerPublisher::publishPending).
 */
class DigitalNewspaperController extends Controller
{
    public function index(Request $request)
    {
        $edition = $this->edition($request);
        $search = trim((string) $request->query('q', ''));
        $papers = DigitalNewspaper::edition($edition)
            ->when($search !== '', fn ($q) => \App\Http\Controllers\DigitalNewspaperController::search($q, $search))
            ->latestFirst()
            ->paginate(20)->withQueryString();

        $views = \App\Models\PageView::viewsPerArticle($edition === 'weekly' ? 'newspaper' : 'epaper', $papers->pluck('id'));

        return view('admin.digital-newspaper.index', compact('papers', 'search', 'views', 'edition'));
    }

    public function create(Request $request)
    {
        $edition = $this->edition($request);

        return view('admin.digital-newspaper.create', ['edition' => $edition, 'blocked' => $this->blocked($edition)]);
    }

    public function store(Request $request)
    {
        $edition = $this->edition($request);
        if ($edition !== 'weekly') {
            return redirect()->route('admin.digital-newspaper.index', ['edition' => $edition])->with('success', $this->sendByEmail($request));
        }

        $paper = DigitalNewspaper::create($this->validated($request));
        BloggerPublisher::soon(); // also to the Weekly blog

        return $this->backToList($paper, 'Edition added successfully.');
    }

    /**
     * Bulk upload, before uploading: which of these files are already on the website (same paper and the same
     * edition date / week start, or the same title). items[] = {key, title, published_on}
     */
    public function bulkCheck(Request $request)
    {
        $edition = $this->edition($request);
        $data = $request->validate([
            'items' => 'required|array|max:500',
            'items.*.key' => 'required|string|max:100',
            'items.*.title' => 'nullable|string|max:255',
            'items.*.published_on' => 'nullable|date',
        ]);

        $found = [];
        foreach ($data['items'] as $item) {
            if ($existing = DigitalNewspaper::findDuplicate($edition, $item['title'] ?? '', $item['published_on'] ?? null)) {
                $found[$item['key']] = ['id' => $existing->id, 'title' => $existing->title, 'url' => route('admin.digital-newspaper.edit', $existing)];
            } elseif ($sent = EpaperByEmail::waitingDuplicate($edition, $item['title'] ?? '', $item['published_on'] ?? null)) {
                $found[$item['key']] = ['id' => null, 'title' => $sent['title'] . ' (on its way to Blogger)', 'url' => route('admin.digital-newspaper.index', ['edition' => $edition])];
            }
        }

        return response()->json(['duplicates' => (object) $found]);
    }

    /**
     * Bulk upload: one PDF = one edition, made exactly like the single form (same fields, same page pictures made
     * in the browser). Answers in JSON; a duplicate is refused (409) before anything is uploaded, unless "force".
     */
    public function bulkStore(Request $request)
    {
        $edition = $this->edition($request);
        $request->validate(['title' => 'required|string|max:255', 'published_on' => 'nullable|date', 'week_from' => 'nullable|date']);
        $date = $edition === 'weekly' ? ($request->input('week_from') ?: $request->input('published_on')) : $request->input('published_on');
        if (!$request->boolean('force') && ($existing = DigitalNewspaper::findDuplicate($edition, (string) $request->input('title'), $date ?: null))) {
            return response()->json(['message' => 'Already on the website: ' . $existing->title], 409);
        }
        if (!$request->boolean('force') && ($sent = EpaperByEmail::waitingDuplicate($edition, (string) $request->input('title'), $date ?: null))) {
            return response()->json(['message' => 'Already on its way to Blogger: ' . $sent['title']], 409);
        }

        if ($edition !== 'weekly') {
            $message = $this->sendByEmail($request);

            return response()->json(['id' => null, 'title' => $request->input('title'), 'url' => BloggerPublisher::blogs()[$edition]['url'] ?? null,
                'pages' => count($request->file('page_files', [])), 'message' => $message], 201);
        }

        $paper = DigitalNewspaper::create($this->validated($request));
        BloggerPublisher::soon(); // also to the Weekly blog

        return response()->json(['id' => $paper->id, 'title' => $paper->title, 'url' => route('admin.digital-newspaper.edit', $paper), 'pages' => count($paper->pages ?? [])], 201);
    }

    public function edit(DigitalNewspaper $digitalNewspaper)
    {
        return view('admin.digital-newspaper.edit', ['paper' => $digitalNewspaper, 'edition' => $digitalNewspaper->edition, 'blocked' => $this->blocked($digitalNewspaper->edition)]);
    }

    public function update(Request $request, DigitalNewspaper $digitalNewspaper)
    {
        if ($digitalNewspaper->isWeekly()) {
            $before = [$digitalNewspaper->title, $digitalNewspaper->pages];
            $digitalNewspaper->update($this->validated($request, $digitalNewspaper));
            BloggerPublisher::soon(); // e.g. switched from inactive to active

            // Already on Blogger: its post gets the new title / pages too
            $note = '';
            if ($digitalNewspaper->blogger_url && [$digitalNewspaper->title, $digitalNewspaper->pages] !== $before && app(BloggerPublisher::class)->isConnected()) {
                try {
                    app(BloggerPublisher::class)->updatePost($digitalNewspaper);
                    $note = ' The Blogger post was updated too.';
                } catch (RuntimeException $e) {
                    $note = ' ' . $e->getMessage();
                }
            }

            return $this->backToList($digitalNewspaper, 'Edition updated successfully.' . $note);
        }

        // Daily: the pages are on Blogger and are not changed from here; a new title goes to the Blogger post too
        if ($request->file('page_files')) {
            throw ValidationException::withMessages(['page_files' => 'The pages of a daily e-paper are kept on Blogger. To change them, delete this edition here and in Blogger, then upload the PDF again.']);
        }
        [$data] = $this->fields($request, $digitalNewspaper);
        $changed = $data['title'] !== $digitalNewspaper->title;
        $digitalNewspaper->update($data);

        $note = '';
        if ($changed && $digitalNewspaper->blogger_id && app(BloggerPublisher::class)->isConnected()) {
            try {
                app(BloggerPublisher::class)->updatePost($digitalNewspaper);
                $note = ' The Blogger post was updated too.';
            } catch (RuntimeException $e) {
                $note = ' ' . $e->getMessage();
            }
        }

        return $this->backToList($digitalNewspaper, 'Edition updated successfully.' . $note);
    }

    public function destroy(DigitalNewspaper $digitalNewspaper)
    {
        $digitalNewspaper->delete();

        return $this->backToList($digitalNewspaper, 'Edition deleted successfully.');
    }

    public function bulkDestroy(Request $request)
    {
        $validated = $request->validate(['ids' => 'required|array', 'ids.*' => 'integer']);
        $edition = $this->edition($request);
        $items = DigitalNewspaper::edition($edition)->whereIn('id', $validated['ids'])->get();
        $items->each->delete(); // one by one so uploaded page images are removed too

        return redirect()->route('admin.digital-newspaper.index', ['edition' => $edition])->with('success', $items->count() . ' edition(s) deleted successfully.');
    }

    /**
     * "Get all editions from Blogger": reads the whole blog of this paper and copies to the website every post it does
     * not have yet (the list shows the newest date first), leaving out copies of an edition that is already here.
     */
    public function fromBlogger(Request $request)
    {
        $edition = $this->edition($request);
        @set_time_limit(600);
        ignore_user_abort(true);

        try {
            \Illuminate\Support\Facades\Artisan::call('newspaper:import', ['--edition' => $edition, '--full' => true]);
        } catch (\Throwable $e) {
            return redirect()->route('admin.digital-newspaper.index', ['edition' => $edition])
                ->with('error', 'Could not read the ' . DigitalNewspaper::EDITIONS[$edition] . ' blog (' . $e->getMessage() . '). Please try again.');
        }
        $output = \Illuminate\Support\Facades\Artisan::output();
        $count = fn (string $pattern) => preg_match($pattern, $output, $m) ? (int) $m[1] : 0;
        $new = $count('/Done\. (\d+) new/');
        $copies = $count('/(\d+) copy\(ies\) of editions already here skipped/');
        $removed = $count('/(\d+) copy\(ies\) of the same edition removed/');

        $blog = DigitalNewspaper::EDITIONS[$edition] . ' blog';
        $message = $new ? $new . ' edition(s) brought in from the ' . $blog . '.' : 'The website already has every edition of the ' . $blog . '.';
        if ($copies) {
            $message .= ' ' . $copies . ' post(s) left out: the same edition (date and title) is already here.';
        }
        if ($removed) {
            $message .= ' ' . $removed . ' extra copy(ies) of an edition removed from the website list.';
        }

        return redirect()->route('admin.digital-newspaper.index', ['edition' => $edition])->with('success', $message);
    }

    /** The paper being managed: ?edition= (or the form field), Weekly News Paper by default. */
    private function edition(Request $request): string
    {
        $edition = (string) $request->input('edition', 'weekly');

        return array_key_exists($edition, DigitalNewspaper::EDITIONS) ? $edition : 'weekly';
    }

    private function backToList(DigitalNewspaper $paper, string $message)
    {
        return redirect()->route('admin.digital-newspaper.index', ['edition' => $paper->edition])->with('success', $message);
    }

    /** Daily e-paper: what is missing before a PDF can be uploaded (shown on the form), or null */
    private function blocked(string $edition): ?string
    {
        return $edition === 'weekly' ? null : app(EpaperByEmail::class)->problem($edition);
    }

    /**
     * Daily e-paper straight to its Blogger blog by email: the pages (web-sized) are emailed and Blogger keeps them.
     * Nothing is saved on the website server; the edition shows on the website a minute or two later, once Blogger
     * has made the post (EpaperByEmail::finishPending). Returns the message for the admin.
     */
    private function sendByEmail(Request $request): string
    {
        [$data, $uploads] = $this->fields($request);
        $paper = new DigitalNewspaper($data);

        $pages = [];
        foreach (array_values($uploads) as $i => $file) {
            // Newspaper pages are read on screen: wider and sharper than photos
            [$bytes, $ext] = \App\Support\ImageOptimizer::optimize((string) file_get_contents($file->getRealPath()), (string) $file->extension(), 2000, 85);
            $png = $ext === 'png';
            $pages[] = [$bytes, sprintf('page-%02d.%s', $i + 1, $png ? 'png' : 'jpg'), $png ? 'image/png' : 'image/jpeg'];
        }
        if (array_sum(array_map(fn ($p) => strlen($p[0]), $pages)) > 20 * 1048576) {
            throw ValidationException::withMessages(['page_files' => 'The pages are more than 20 MB together, too big for one email. Split the PDF. Nothing was uploaded.']);
        }

        try {
            app(EpaperByEmail::class)->queue($paper, $pages);
        } catch (RuntimeException $e) {
            throw ValidationException::withMessages(['page_files' => $e->getMessage() . ' Nothing was uploaded.']);
        }

        return 'Added to the ' . DigitalNewspaper::EDITIONS[$paper->edition] . ' blog\'s queue. The website sends the editions to Blogger one by one, by itself (you can close this page); each shows here and on the blog, dated ' . $paper->published_on->format('d.m.Y') . ', a few minutes after its turn.';
    }

    /**
     * Pages = the page images made from the uploaded PDF (in the admin's browser, one JPEG per page,
     * in page order). They replace all the old pages; old uploaded page files are deleted. Without a
     * new PDF an edited edition keeps its pages.
     */
    private function validated(Request $request, ?DigitalNewspaper $existing = null): array
    {
        [$data, $uploads] = $this->fields($request, $existing);

        if ($uploads) {
            $pages = collect();
            try {
                foreach ($uploads as $file) {
                    $pages->push(\App\Support\ImageOptimizer::store($file, $data['edition'] === 'weekly' ? 'digital-newspaper' : 'epaper/' . $data['edition'], 2000, 85));
                }
            } catch (RuntimeException $e) {
                Storage::disk('public')->delete($pages->all()); // no edition with some of its pages missing
                throw ValidationException::withMessages(['page_files' => $e->getMessage() . ' Nothing was saved; please try again.']);
            }
            // On Blogger (posted from here): its post may still show the old pictures, keep them
            if ($existing && !$existing->blogger_url) {
                Storage::disk('public')->delete($existing->uploadedPages());
            }
        } else {
            $pages = collect($existing->pages);
        }

        return $data + ['pages' => $pages->unique()->values()->all()];
    }

    /**
     * The form's fields (without the pages) and the page pictures sent. Daily e-papers have an edition date
     * instead of a week.
     *
     * @return array{0: array, 1: array}
     */
    private function fields(Request $request, ?DigitalNewspaper $existing = null): array
    {
        $edition = $existing?->edition ?? $this->edition($request);
        $daily = $edition !== 'weekly';

        $data = $request->validate([
            'title' => 'required|string|max:255',
            'week_from' => 'nullable|date',
            'week_to' => 'nullable|date|after_or_equal:week_from',
            'published_on' => [$daily ? 'required' : 'nullable', 'date'],
            // max_file_uploads on the server is usually 20
            'page_files' => 'nullable|array|max:20',
            'page_files.*' => 'image|mimes:jpg,jpeg,png,webp|max:8192',
            'status' => 'required|in:active,inactive',
            // Schedule date and time, India time (empty = as soon as it is saved)
        ] + \App\Support\Schedule::RULES, \App\Support\Schedule::MESSAGES + [
            'week_to.after_or_equal' => 'The week must end on or after its start date.',
            'published_on.required' => 'Please enter the edition date.',
            'page_files.max' => 'Up to 20 pages can be uploaded at once.',
            'page_files.*.max' => 'Each page must be 8 MB or smaller.',
        ]);

        $publishAt = \App\Support\Schedule::publishAt($data);
        $uploads = $request->file('page_files', []);
        if (!$uploads && !$existing?->pages) {
            throw ValidationException::withMessages(['page_files' => 'Please upload the newspaper PDF.']);
        }

        return [[
            'edition' => $edition,
            'title' => $data['title'],
            'week_from' => $daily ? null : ($data['week_from'] ?? null),
            'week_to' => $daily ? null : ($data['week_to'] ?? null),
            // Weekly (no Edition Date box): the week's start, else the scheduled day, else the date it already has / today
            'published_on' => ($daily ? null : ($data['week_from'] ?? $publishAt?->copy()->timezone('Asia/Kolkata')->toDateString())) ?? $data['published_on'] ?? $existing?->published_on ?? now('Asia/Kolkata')->toDateString(),
            'status' => $data['status'],
            'publish_at' => $publishAt,
        ], $uploads];
    }
}
