<?php

namespace App\Http\Controllers;

use App\Models\ReaderQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ReaderQueryController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $back = redirect()->to(route('disclaimer') . '#ask');

        // Honeypot: a field hidden from people; bots that fill every input get silently ignored
        if ($request->filled('website')) {
            return $back->with('query_success', true);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'contact' => 'required|string|max:150',
            'message' => 'required|string|min:5|max:5000',
            'files' => 'nullable|array|max:5',
            'files.*' => 'file|mimes:jpg,jpeg,png,webp,pdf,doc,docx,xls,xlsx,txt|max:5120',
        ], [
            'name.required' => 'Please enter your name.',
            'contact.required' => 'Please enter your mobile number or email so we can reply.',
            'message.required' => 'Please type your question.',
            'files.max' => 'You can attach up to 5 files.',
            'files.*.mimes' => 'Files must be images, PDF, Word, Excel or text.',
            'files.*.max' => 'Each file must be 5MB or smaller.',
        ]);

        $attachments = [];
        foreach ($request->file('files', []) as $file) {
            $attachments[] = [
                'path' => $file->store(ReaderQuery::FOLDER, 'local'),
                'name' => $file->getClientOriginalName(),
                'size' => $file->getSize(),
            ];
        }

        ReaderQuery::create([
            'name' => $validated['name'],
            'contact' => $validated['contact'],
            'message' => $validated['message'],
            'attachments' => $attachments ?: null,
        ]);

        return $back->with('query_success', true);
    }
}
