<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LegalPage;
use App\Support\Html;
use Illuminate\Http\Request;

/** Admin -> Legal Pages: edit Disclaimer, Privacy Policy, Terms & Conditions, Refund Policy and Cookie Policy. */
class LegalPageController extends Controller
{
    public function index()
    {
        $pages = collect(array_keys(LegalPage::PAGES))->map(fn ($slug) => LegalPage::for($slug));

        return view('admin.legal-pages.index', compact('pages'));
    }

    public function edit(string $slug)
    {
        $page = LegalPage::for($slug);

        return view('admin.legal-pages.edit', ['page' => $page, 'content' => $page->contentHtml()]);
    }

    public function update(Request $request, string $slug)
    {
        $page = LegalPage::for($slug);
        $data = $request->validate([
            'title' => 'required|string|max:200',
            'subtitle' => 'nullable|string|max:500',
            'meta' => 'nullable|string|max:1000',
            'content' => 'required|string|max:1000000',
        ]);
        $data['content'] = Html::cleanLegal($data['content']);

        if (trim(Html::plain($data['content'])) === '') {
            return back()->withInput()->withErrors(['content' => 'The page text cannot be empty.']);
        }

        $page->fill($data)->save();

        return redirect()->route('admin.legal-pages.edit', $slug)->with('success', $page->title . ' saved. The website shows the new text now.');
    }

    /** Throw away the admin's text and go back to the built-in default. */
    public function reset(string $slug)
    {
        $page = LegalPage::for($slug);
        if ($page->exists) {
            $page->delete();
        }

        return redirect()->route('admin.legal-pages.edit', $slug)->with('success', LegalPage::PAGES[$slug]['title'] . ' is back to the original text.');
    }
}
