<?php

namespace App\Http\Middleware;

use App\Support\ContentVersion;
use Closure;
use Illuminate\Http\Request;

/**
 * After any successful admin change (create / update / delete / settings), tell open
 * public pages to refresh their content. Covers every admin section, including bulk deletes.
 */
class BumpContentVersion
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        // POST/PUT/PATCH/DELETE that succeeded (redirect back = saved; 4xx/5xx or validation errors = not saved)
        // (JSON calls like "Generate with AI" don't change site content)
        if (!$request->isMethodSafe() && !$request->expectsJson() && $response->getStatusCode() < 400 && !$request->session()->has('errors')) {
            ContentVersion::bump();
        }

        return $response;
    }
}
