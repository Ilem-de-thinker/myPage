<?php

namespace App\Shared\Support;

/**
 * The baseline Content Security Policy.
 *
 * Alpine runs through its CSP build, so expression evaluation does not require
 * unsafe-eval. Inline Blade registrations remain temporarily permitted until
 * those registrations are moved into the bundled component registry.
 */
final class ContentSecurityPolicy
{
    /**
     * @return array<string, string>
     */
    public static function directives(): array
    {
        $scriptSources = ["'self'", "'unsafe-inline'"];
        $styleSources = ["'self'", "'unsafe-inline'", 'https://fonts.googleapis.com'];
        $fontSources = ["'self'", 'data:', 'https://fonts.gstatic.com'];
        $connectSources = ["'self'"];

        // Vite's development server is same-machine traffic. Loosening only
        // these loopback origins keeps production reports meaningful while the
        // local HMR client, styles, and websocket still work.
        if (app()->isLocal()) {
            $scriptSources[] = 'http://localhost:*';
            $scriptSources[] = 'https://localhost:*';
            $scriptSources[] = 'http://127.0.0.1:*';
            $scriptSources[] = 'https://127.0.0.1:*';
            $styleSources[] = 'http://localhost:*';
            $styleSources[] = 'https://localhost:*';
            $styleSources[] = 'http://127.0.0.1:*';
            $styleSources[] = 'https://127.0.0.1:*';
            $connectSources[] = 'ws://localhost:*';
            $connectSources[] = 'wss://localhost:*';
            $connectSources[] = 'ws://127.0.0.1:*';
            $connectSources[] = 'wss://127.0.0.1:*';
            $fontSources[] = 'http://localhost:*';
            $fontSources[] = 'https://localhost:*';
        }

        return [
            'default-src' => "'self'",
            'base-uri' => "'self'",
            'form-action' => "'self'",
            'frame-ancestors' => "'self'",
            'object-src' => "'none'",
            'img-src' => "'self' data: https:",
            'media-src' => "'self' data: https:",
            'font-src' => implode(' ', $fontSources),
            'style-src' => implode(' ', $styleSources),
            'script-src' => implode(' ', $scriptSources),
            'connect-src' => implode(' ', $connectSources),
            'worker-src' => "'self'",
            'manifest-src' => "'self'",
            'report-uri' => (string) config('security.csp.report_uri', '/webhooks/csp-report'),
        ];
    }

    public static function header(): string
    {
        $directives = [];

        foreach (self::directives() as $name => $value) {
            $directives[] = "{$name} {$value}";
        }

        return implode('; ', $directives);
    }

    public static function reportOnlyHeader(): string
    {
        return self::header();
    }
}
