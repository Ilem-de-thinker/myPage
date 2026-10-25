<?php

namespace App\Shared\Middleware;

use App\Shared\Support\ContentSecurityPolicy;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Browser security headers for every web response.
 *
 * The enforced policy keeps the existing Alpine templates working while
 * restricting origins, frames, forms, objects, and workers. Report-only mode
 * remains available for deployments that need to inspect violations first.
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
        $policy = ContentSecurityPolicy::header();

        if (config('security.csp.enforce', true)) {
            $response->headers->set('Content-Security-Policy', $policy);
        }

        if (config('security.csp.report_only', true)) {
            $response->headers->set('Content-Security-Policy-Report-Only', $policy);
        }

        $response->headers->set('X-Permitted-Cross-Domain-Policies', 'none');
        $response->headers->set('Cross-Origin-Opener-Policy', 'same-origin');

        // Only when the response actually travelled over HTTPS. Setting HSTS on
        // plain HTTP responses would be ignored at best and misleading at worst.
        if ($request->isSecure()) {
            $maxAge = (int) config('security.hsts_max_age', 31536000);

            $response->headers->set('Strict-Transport-Security', "max-age={$maxAge}; includeSubDomains");
        }

        return $response;
    }
}
