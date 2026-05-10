<?php

namespace Tests\Feature\Security;

use Tests\TestCase;

final class SecurityHeadersTest extends TestCase
{
    public function test_default_responses_include_security_headers(): void
    {
        $response = $this->followingRedirects()->get('/');

        $response->assertOk();
        $response->assertHeader('X-Frame-Options', 'DENY');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('Referrer-Policy', 'same-origin');
        $response->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');

        $cspHeader = $response->headers->get('Content-Security-Policy')
            ?? $response->headers->get('Content-Security-Policy-Report-Only');

        $this->assertNotNull($cspHeader, 'Expected a CSP header to be present.');
        $this->assertStringContainsString("default-src 'self'", (string) $cspHeader);
    }
}
