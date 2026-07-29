<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-XSS-Protection', '0');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
        $response->headers->set(
            'Content-Security-Policy',
            "default-src 'self'; ".
            "base-uri 'self'; ".
            "form-action 'self'; ".
            "frame-ancestors 'none'; ".
            "object-src 'none'; ".
            "script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; ".
            "style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://fonts.googleapis.com; ".
            "font-src 'self' https://cdn.jsdelivr.net https://fonts.gstatic.com; ".
            "img-src 'self' data:; ".
            "connect-src 'self' https://cdn.jsdelivr.net;"
        );

        if (Auth::check()) {
            $response->headers->set('Cache-Control', 'no-store, private');
            $response->headers->set('Pragma', 'no-cache');
        }

        // HSTS sÃ³ pode ser anunciado quando a requisiÃ§Ã£o chegou por HTTPS.
        if (app()->environment('production') && $request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }
}
