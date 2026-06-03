<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $nonce = base64_encode(random_bytes(16));

        view()->share('cspNonce', $nonce);
        Vite::useCspNonce($nonce);

        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=()');
        $response->headers->set('Content-Security-Policy',
            "default-src 'self'; " .
            "script-src 'self' 'nonce-{$nonce}'; " .
            "style-src 'self' 'unsafe-inline' https://fonts.bunny.net; " .
            "img-src 'self' data: blob: https://res.cloudinary.com; " .
            "media-src 'self' https://res.cloudinary.com blob:; " .
            "font-src 'self' data: https://fonts.bunny.net; " .
            "connect-src 'self' wss://ws-eu.pusher.com https://sockjs-eu.pusher.com; " .
            "object-src 'none'; " .
            "base-uri 'self'; " .
            "form-action 'self';"
        );
        $response->headers->remove('X-Powered-By');

        return $response;
    }
}
