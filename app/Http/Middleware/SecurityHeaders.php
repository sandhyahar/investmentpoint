<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Standard browser security headers for every page. */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $headers = $response->headers;

        $headers->set('X-Frame-Options', 'SAMEORIGIN');            // other sites can't show our pages in a frame (clickjacking)
        $headers->set('X-Content-Type-Options', 'nosniff');
        $headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $headers->set('X-Permitted-Cross-Domain-Policies', 'none');
        // Payment Request API only for Razorpay's checkout (Google Pay / UPI buttons inside it)
        $headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=(self "https://api.razorpay.com" "https://checkout.razorpay.com")');
        // No plugins, no <base> tricks, no framing by other sites, and forms only post to this site
        // (scripts are not restricted here: the pages use inline scripts, CDNs, YouTube, Google Translate)
        $csp = "object-src 'none'; base-uri 'self'; frame-ancestors 'self'; form-action 'self'";
        if ($request->isSecure()) {
            // Always use https from now on. Not "includeSubDomains": english.investmentpoint.in (old server) only works on http
            $headers->set('Strict-Transport-Security', 'max-age=31536000');
            $csp .= '; upgrade-insecure-requests';
        }
        $headers->set('Content-Security-Policy', $csp);
        $headers->remove('X-Powered-By');
        header_remove('X-Powered-By'); // don't advertise the PHP version

        return $response;
    }
}
