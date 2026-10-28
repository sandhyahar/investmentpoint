<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\News;
use App\Models\TechnicalAnalysis;
use App\Support\ContentVersion;
use App\Support\ShortLink;
use Illuminate\Http\Request;

/**
 * Share box (admin lists): save the article's own Share URL (investmentpoint.in/{name}) and return the
 * link to share now. Empty = the name made from the title, as before.
 */
class ShareLinkController extends Controller
{
    private const TYPES = ['news' => News::class, 'analysis' => TechnicalAnalysis::class];

    public function update(Request $request, string $type, int $id)
    {
        abort_unless(isset(self::TYPES[$type]), 404);
        $article = self::TYPES[$type]::findOrFail($id);
        $data = $request->validate(['share_name' => ShortLink::rule()]);

        $article->update(['share_name' => ShortLink::normalize($data['share_name'] ?? null)]);
        ContentVersion::bump(); // the short links are rebuilt (a JSON call doesn't bump it by itself)

        return response()->json([
            'url' => $article->share_url,
            'share_name' => $article->share_name,
            'auto' => ShortLink::nameFor($article),
        ]);
    }
}
