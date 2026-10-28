<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ReaderQuery;
use Illuminate\Support\Facades\Storage;

class ReaderQueryController extends Controller
{
    public function index()
    {
        $queries = ReaderQuery::latest()->paginate(20);

        return view('admin.queries.index', compact('queries'));
    }

    public function show(ReaderQuery $readerQuery)
    {
        if (!$readerQuery->read_at) {
            $readerQuery->update(['read_at' => now()]);
        }

        return view('admin.queries.show', ['query' => $readerQuery]);
    }

    public function download(ReaderQuery $readerQuery, int $index)
    {
        $file = ($readerQuery->attachments ?? [])[$index] ?? abort(404);
        abort_unless(Storage::disk('local')->exists($file['path']), 404);

        return Storage::disk('local')->download($file['path'], $file['name']);
    }

    public function destroy(ReaderQuery $readerQuery)
    {
        $readerQuery->delete();

        return redirect()->route('admin.queries.index')->with('success', 'Question deleted successfully.');
    }
}
