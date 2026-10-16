<?php

use Illuminate\Support\Facades\Log;

use function Pest\Laravel\get;
use function Pest\Laravel\postJson;

it('sends baseline browser protections on a public page', function () {
    $response = get('/');

    $response->assertOk();
    $response->assertHeader('X-Content-Type-Options', 'nosniff');
    $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
    $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    $response->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');

    $policy = $response->headers->get('Content-Security-Policy-Report-Only');

    expect($policy)
        ->toContain("frame-ancestors 'self'")
        ->toContain("object-src 'none'")
        ->toContain('report-uri /webhooks/csp-report');
});

it('sends HSTS only when the response travels over HTTPS', function () {
    get('/')->assertHeaderMissing('Strict-Transport-Security');

    get('https://localhost/')
        ->assertOk()
        ->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
});

it('accepts a CSP violation report and strips sensitive URL parts before logging', function () {
    Log::spy();

    postJson('/webhooks/csp-report', [
        'csp-report' => [
            'document-uri' => 'https://seller.example/shop?session=secret-session&token=secret-token',
            'effective-directive' => 'script-src',
            'violated-directive' => 'script-src',
            'blocked-uri' => 'https://evil.example/assets/evil.js?session=secret-session',
            'source-file' => 'https://seller.example/assets/app.js',
            'line-number' => 132,
            'column-number' => 15,
            'status-code' => 200,
        ],
    ])->assertStatus(202);

    Log::shouldHaveReceived('warning')->once()->with('csp.violation', Mockery::on(
        fn (array $context): bool => ($context['document'] ?? null) === 'https://seller.example/shop'
            && ($context['blocked_source'] ?? null) === 'https://evil.example/assets/evil.js'
            && ($context['source'] ?? null) === 'https://seller.example/assets/app.js'
            && ($context['line'] ?? null) === 132
            && ! str_contains((string) json_encode($context), 'secret-session')
    ));
});

it('accepts a malformed CSP violation report without failing the request', function () {
    postJson('/webhooks/csp-report', ['csp-report' => 'not-an-array'])->assertStatus(202);
});
