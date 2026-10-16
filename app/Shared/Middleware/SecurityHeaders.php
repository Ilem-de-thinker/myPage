<?php

namespace App\Shared\Middleware;

use App\Shared\Support\ContentSecurityPolicy;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Baseline response headers for browser-handled routes.
 *
 * CSP stays report-only while inline handlers and Alpine's expression compiler
 * remain in the templates. That still gives production violation telemetry and
 * avoids trading security observability for a broken checkout flow.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
        $response->headers->set('Content-Security-Policy-Report-Only', ContentSecurityPolicy::reportOnlyHeader());

        // Only when the response actually travelled over HTTPS. Setting HSTS on
        // plain HTTP responses would be ignored at best and misleading at worst.
        if ($request->isSecure()) {
            $maxAge = (int) config('security.hsts_max_age', 31536000);

            $response->headers->set('Strict-Transport-Security', "max-age={$maxAge}; includeSubDomains");
        }

        return $response;
    }
}
