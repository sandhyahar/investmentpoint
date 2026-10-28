<?php

namespace App\Http\Controllers;

use App\Models\UserBlog;
use App\Support\ShortLink;

/**
 * investmentpoint.in/{name}: an approved blog with that address (as before), else the short share link
 * of the newest Market Trend / Technical Analysis article with that name (investmentpoint.in/nifty).
 */
class ShortLinkController extends Controller
{
    public function show(string $slug)
    {
        if (UserBlog::where('slug', $slug)->where('status', 'approved')->exists()) {
            return app(BlogController::class)->show($slug);
        }
        $target = ShortLink::resolve($slug);
        abort_unless($target, 404);

        // 302, not 301: the link opens a newer article once one is added
        return redirect()->away($target, 302)->header('Cache-Control', 'no-store');
    }
}
