<?php

namespace App\Http\Controllers;

use App\Models\BlogReaction;
use App\Models\MarketIndex;
use App\Models\UserBlog;
use App\Support\BlogFormatter;
use App\Support\ImageOptimizer;
use App\Support\Visitor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class BlogController extends Controller
{
    /** The blog list now lives at the top of Expert's View; old /blogs links land there. */
    public function index(): RedirectResponse
    {
        return redirect()->to(route('experts-view.index') . '#latest-blogs', 301);
    }

    public function create(): View
    {
        $tickerIndices = $this->tickerIndices();

        return view('blogs.create', compact('tickerIndices'));
    }

    public function store(Request $request): RedirectResponse
    {
        // Honeypot: a field hidden from people; bots that fill every input get silently ignored
        if ($request->filled('website')) {
            return redirect()->route('blog.create')->with('success', 'Thank you! Your blog has been submitted for review.');
        }

        $validated = $request->validate([
            // The writer's own name is shown on the blog (never the account it was sent from)
            'author_name' => 'required|string|min:2|max:100',
            // So the team can contact the writer; never shown on the website
            'author_email' => 'required|email|max:150',
            // Optional: shown next to the writer name
            'author_designation' => 'nullable|string|max:100',
            'author_reg_no' => ['nullable', 'string', 'max:50', 'regex:/^[A-Za-z0-9\/.\- ]+$/'],
            // Optional website link, shown on the blog ("www.site.com" is fine: https:// is added)
            'author_website' => ['nullable', 'string', 'max:255', fn ($attribute, $value, $fail) => UserBlog::normalizeWebsite($value) ? null : $fail('Please enter a valid website link, e.g. www.yourwebsite.com')],
            'title' => 'required|string|max:255',
            'content' => 'required|string|min:30',
            'author_photo' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            // Pictures added with "Add image"; image_numbers[i] is the "[Image N]" number of images[i]
            'images' => 'nullable|array|max:6',
            'images.*' => 'image|mimes:jpg,jpeg,png,webp|max:5120',
            'image_numbers' => 'nullable|array|max:6',
            'image_numbers.*' => 'integer|min:1|max:999',
            // "My personal views - Investment Point / Nikhil Bhatt had no involvement" checkbox
            'declaration' => 'accepted',
        ], [
            'declaration.accepted' => 'Please tick the declaration to submit your blog.',
            'author_name.required' => "Please enter the expert's name.",
            'author_name.min' => "Please enter the expert's full name.",
            'author_email.required' => 'Please enter your email address.',
            'author_email.email' => 'Please enter a valid email address, e.g. name@example.com.',
            'author_reg_no.regex' => 'The RA / IA / CA number can only contain letters, numbers, spaces, "/", "-" and ".".',
            'content.required' => 'Please write the blog description.',
            'content.min' => 'The blog description should be at least 30 characters.',
            'author_photo.image' => 'The profile picture must be an image (JPG, PNG or WEBP).',
            'author_photo.max' => 'The profile picture must be 2MB or smaller.',
            'images.max' => 'You can add up to 6 pictures.',
            'images.*.image' => 'Pictures must be JPG, PNG or WEBP images.',
            'images.*.mimes' => 'Pictures must be JPG, PNG or WEBP images.',
            'images.*.max' => 'Each picture must be 5MB or smaller.',
        ]);

        // Pictures still marked in the text are saved; the text becomes a formatted article
        $used = BlogFormatter::imageNumbers($validated['content']);
        $imageUrls = [];
        foreach ($request->file('images', []) as $i => $file) {
            $number = (int) $request->input("image_numbers.$i");
            if (in_array($number, $used, true) && !isset($imageUrls[$number])) {
                $imageUrls[$number] = asset('storage/' . ImageOptimizer::store($file, 'blog-images'));
            }
        }

        UserBlog::create([
            'user_id' => $request->user()?->id ?? auth('member')->id(),
            'author_name' => trim($validated['author_name']),
            'author_email' => strtolower(trim($validated['author_email'])),
            'author_photo' => $request->hasFile('author_photo') ? \App\Support\ImageOptimizer::store($request->file('author_photo'), 'blog-authors', 400) : null,
            'author_designation' => trim($validated['author_designation'] ?? '') ?: null,
            'author_reg_no' => strtoupper(trim($validated['author_reg_no'] ?? '')) ?: null,
            'author_website' => UserBlog::normalizeWebsite($validated['author_website'] ?? null),
            'title' => $validated['title'],
            'slug' => $this->uniqueSlug($validated['title']),
            'content' => BlogFormatter::toHtml($validated['content'], $imageUrls),
            'status' => 'pending',
        ]);

        return redirect()->route('blog.create')->with('success', 'Thank you! Your blog has been submitted and will appear on the site once it is approved.');
    }

    public function show(string $slug): View
    {
        $blog = UserBlog::where('slug', $slug)->where('status', 'approved')->firstOrFail();
        $moreBlogs = UserBlog::where('status', 'approved')
            ->whereKeyNot($blog->getKey())
            ->latest('approved_at')
            ->take(5)
            ->get();
        $tickerIndices = $this->tickerIndices();
        $reactions = $blog->reactionSummary(Visitor::id(request()));

        return view('blogs.show', compact('blog', 'moreBlogs', 'tickerIndices', 'reactions'));
    }

    /**
     * Like / dislike button: one vote per reader (anonymous visitor id). Clicking the same button
     * again takes the vote back; the other button changes it.
     */
    public function react(Request $request, string $slug): JsonResponse
    {
        $blog = UserBlog::where('slug', $slug)->where('status', 'approved')->firstOrFail();
        $value = $request->validate(['reaction' => 'required|in:like,dislike'])['reaction'] === 'like' ? BlogReaction::LIKE : BlogReaction::DISLIKE;
        $visitor = Visitor::id($request, true);

        $vote = $blog->reactions()->where('visitor', $visitor)->first();
        if ($vote && $vote->reaction === $value) {
            $vote->delete();
        } elseif ($vote) {
            $vote->update(['reaction' => $value]);
        } else {
            $blog->reactions()->firstOrCreate(['visitor' => $visitor], ['reaction' => $value]);
        }

        return response()->json($blog->reactionSummary($visitor));
    }

    private function uniqueSlug(string $title): string
    {
        $base = Str::slug($title) ?: 'user-blog';
        $slug = $base;
        $counter = 2;

        while (UserBlog::where('slug', $slug)->exists()) {
            $slug = $base . '-' . $counter++;
        }

        return $slug;
    }

    private function tickerIndices()
    {
        $tickerNames = ['NIFTY 50', 'SENSEX', 'BANK NIFTY', 'INDIA VIX'];

        return MarketIndex::whereIn('name', $tickerNames)->get()->sortBy(function ($index) use ($tickerNames) {
            return array_search($index->name, $tickerNames, true);
        })->values();
    }
}
