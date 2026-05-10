<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnforceSecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        if (! $response->headers->has('X-Frame-Options')) {
            $response->headers->set('X-Frame-Options', 'DENY', false);
        }

        if (! $response->headers->has('X-Content-Type-Options')) {
            $response->headers->set('X-Content-Type-Options', 'nosniff', false);
        }

        if (! $response->headers->has('Referrer-Policy')) {
            $response->headers->set('Referrer-Policy', 'same-origin', false);
        }

        if (! $response->headers->has('Permissions-Policy')) {
            $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()', false);
        }

        if ($request->isSecure() && ! $response->headers->has('Strict-Transport-Security')) {
            $response->headers->set('Strict-Transport-Security', 'max-age=63072000; includeSubDomains; preload', false);
        }

        $cspHeader = $this->buildContentSecurityPolicy();
        if ($cspHeader !== null) {
            $headerName = config('security.csp.report_only', false)
                ? 'Content-Security-Policy-Report-Only'
                : 'Content-Security-Policy';

            $response->headers->set($headerName, $cspHeader, false);
        }

        return $response;
    }

    private function buildContentSecurityPolicy(): ?string
    {
        $directives = config('security.csp.directives', []);

        if (! is_array($directives) || empty($directives)) {
            return null;
        }

        $segments = [];

        foreach ($directives as $directive => $values) {
            if (! is_string($directive) || $directive === '') {
                continue;
            }

            $values = array_values(array_filter(
                array_map(static fn ($value) => is_string($value) ? trim($value) : null, (array) $values),
                static fn ($value) => $value !== null && $value !== ''
            ));

            if (empty($values)) {
                continue;
            }

            $segments[] = sprintf('%s %s', $directive, implode(' ', $values));
        }

        $reportUri = config('security.csp.report_uri');
        if (is_string($reportUri) && $reportUri !== '') {
            $segments[] = sprintf('report-uri %s', $reportUri);
        }

        return empty($segments) ? null : implode('; ', $segments);
    }
}
