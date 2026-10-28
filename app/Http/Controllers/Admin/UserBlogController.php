<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\BlogApprovedMail;
use App\Models\UserBlog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Throwable;

class UserBlogController extends Controller
{
    public function index(): View
    {
        $blogs = UserBlog::with('author')->latest()->paginate(20);
        $views = \App\Models\PageView::viewsPerArticle('blog', $blogs->pluck('slug'));

        return view('admin.blogs.index', compact('blogs', 'views'));
    }

    public function edit(UserBlog $blog): View
    {
        return view('admin.blogs.edit', compact('blog'));
    }

    public function update(Request $request, UserBlog $blog): RedirectResponse
    {
        $validated = $request->validate([
            'author_name' => 'required|string|min:2|max:100',
            // Optional here: blogs sent before the email was required have none
            'author_email' => 'nullable|email|max:150',
            'author_designation' => 'nullable|string|max:100',
            'author_reg_no' => ['nullable', 'string', 'max:50', 'regex:/^[A-Za-z0-9\/.\- ]+$/'],
            'author_website' => ['nullable', 'string', 'max:255', fn ($attribute, $value, $fail) => UserBlog::normalizeWebsite($value) ? null : $fail('Please enter a valid website link, e.g. www.yourwebsite.com')],
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'status' => 'required|in:pending,approved,rejected',
        ], [
            'author_name.required' => 'Please enter the writer name shown on the blog.',
            'author_reg_no.regex' => 'The RA / IA / CA number can only contain letters, numbers, spaces, "/", "-" and ".".',
        ]);

        $justApproved = $blog->status !== 'approved' && $validated['status'] === 'approved';

        if ($request->boolean('remove_photo')) {
            $blog->deleteWriterPhoto();
        }

        $blog->update([
            'author_name' => trim($validated['author_name']),
            'author_email' => strtolower(trim($validated['author_email'] ?? '')) ?: null,
            'author_designation' => trim($validated['author_designation'] ?? '') ?: null,
            'author_reg_no' => strtoupper(trim($validated['author_reg_no'] ?? '')) ?: null,
            'author_website' => UserBlog::normalizeWebsite($validated['author_website'] ?? null),
            'title' => $validated['title'],
            'content' => $validated['content'],
            'status' => $validated['status'],
            'approved_at' => $validated['status'] === 'approved' ? ($blog->approved_at ?? now()) : null,
        ]);

        $redirect = redirect()->route('admin.blogs.index')->with('success', 'User blog updated successfully.');

        // Just approved: email the blog to its writer automatically
        if ($justApproved) {
            $email = $blog->author_email ?: $blog->author?->email;

            if (!$email) {
                return $redirect->with('error', 'Blog approved, but no email was sent: this blog has no writer email.');
            }

            try {
                Mail::to($email, $blog->writer_name)->send(new BlogApprovedMail($blog->fresh()));

                return $redirect->with('success', "Blog approved and emailed to {$email}.");
            } catch (Throwable $e) {
                report($e);

                return $redirect->with('error', "Blog approved, but the email to {$email} could not be sent: " . Str::limit($e->getMessage(), 160));
            }
        }

        return $redirect;
    }

    public function destroy(UserBlog $blog): RedirectResponse
    {
        $blog->deleteFiles();
        $blog->delete();

        return redirect()->route('admin.blogs.index')->with('success', 'User blog deleted successfully.');
    }
}
