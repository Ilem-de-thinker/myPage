<?php

namespace App\Shared\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Receives browser Content Security Policy violation reports.
 *
 * Always answers 202: a malformed browser payload is not the seller's or the
 * buyer's fault, and the endpoint exists to learn what legitimate resources
 * might need allow-listing before CSP can be enforced.
 */
class CspReportController
{
    public function __invoke(Request $request): JsonResponse
    {
        $report = $request->input('csp-report');

        Log::warning('csp.violation', self::context(is_array($report) ? $report : []));

        return response()->json(['received' => true], 202);
    }

    /**
     * Keep useful violation metadata while stripping anything that could carry
     * credentials, session identifiers, or full seller/buyer URLs into logs.
     *
     * @param  array<string, mixed>  $report
     * @return array<string, int|string|null>
     */
    private static function context(array $report): array
    {
        return [
            'document' => self::publicUrl($report['document-uri'] ?? null),
            'effective_directive' => self::directive($report['effective-directive'] ?? null),
            'violated_directive' => self::directive($report['violated-directive'] ?? null),
            'blocked_source' => self::publicUrl($report['blocked-uri'] ?? null),
            'source' => self::publicUrl($report['source-file'] ?? null),
            'line' => self::position($report['line-number'] ?? null),
            'column' => self::position($report['column-number'] ?? null),
            'status_code' => self::position($report['status-code'] ?? null),
        ];
    }

    private static function directive(mixed $value): string
    {
        if (! is_string($value)) {
            return 'unknown';
        }

        $value = strtolower(trim($value));

        return preg_match('/^[a-z][a-z0-9-]{0,63}$/', $value) === 1 ? $value : 'unknown';
    }

    private static function publicUrl(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        if ($value === '') {
            return null;
        }

        $lower = strtolower($value);

        if (in_array($lower, ['self', 'inline', 'eval', 'none', 'data:', 'blob:', 'about:blank'], true)) {
            return $lower;
        }

        $parts = parse_url($value);

        if (! is_array($parts) || ! isset($parts['scheme'], $parts['host'])) {
            return null;
        }

        $scheme = strtolower((string) $parts['scheme']);
        $host = strtolower(rtrim((string) $parts['host'], '.'));

        if (! in_array($scheme, ['http', 'https'], true) || filter_var($host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) === false) {
            return $scheme;
        }

        $path = isset($parts['path']) && str_starts_with((string) $parts['path'], '/')
            ? (string) $parts['path']
            : '/';

        // Query strings, fragments, and userinfo stay out of the log.
        return Str::limit("{$scheme}://{$host}{$path}", 200, '');
    }

    private static function position(mixed $value): ?int
    {
        if (is_int($value) && $value >= 0 && $value <= 1_000_000) {
            return $value;
        }

        if (is_string($value) && ctype_digit($value)) {
            $position = (int) $value;

            return $position <= 1_000_000 ? $position : null;
        }

        return null;
    }
}
