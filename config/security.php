<?php

$applicationEnv = env('APP_ENV', 'production');
$isLocal = in_array($applicationEnv, ['local', 'development'], true);
$viteHosts = $isLocal ? ['http://127.0.0.1:5173', 'http://localhost:5173'] : [];
$viteSockets = $isLocal ? ['ws://127.0.0.1:5173', 'ws://localhost:5173'] : [];

return [
    'rate_limiting' => [
        'login' => [
            'max_attempts' => (int) env('SECURITY_LOGIN_MAX_ATTEMPTS', 5),
            'decay_seconds' => (int) env('SECURITY_LOGIN_DECAY_SECONDS', 600),
        ],
        'api' => [
            'max_attempts' => (int) env('SECURITY_API_MAX_ATTEMPTS', 120),
            'decay_seconds' => (int) env('SECURITY_API_DECAY_SECONDS', 60),
        ],
    ],
    'passwords' => [
        'min_length' => (int) env('SECURITY_PASSWORD_MIN_LENGTH', 12),
        'require_uppercase' => (bool) env('SECURITY_PASSWORD_REQUIRE_UPPERCASE', true),
        'require_lowercase' => (bool) env('SECURITY_PASSWORD_REQUIRE_LOWERCASE', true),
        'require_numeric' => (bool) env('SECURITY_PASSWORD_REQUIRE_NUMERIC', true),
        'require_symbol' => (bool) env('SECURITY_PASSWORD_REQUIRE_SYMBOL', true),
    ],
    'csp' => [
        'directives' => [
            'default-src' => ["'self'"],
            'base-uri' => ["'self'"],
            'form-action' => ["'self'"],
            'img-src' => array_filter([
                "'self'",
                'data:',
                'https://upload.wikimedia.org',
            ]),
            'script-src' => array_filter(array_merge([
                "'self'",
                'https://cdn.jsdelivr.net',
            ], $viteHosts)),
            'style-src' => array_filter(array_merge([
                "'self'",
                "'unsafe-inline'",
                'https://cdn.jsdelivr.net',
                'https://cdnjs.cloudflare.com',
            ], $viteHosts)),
            'font-src' => ["'self'", 'https://fonts.bunny.net', 'https://cdnjs.cloudflare.com'],
            'connect-src' => array_filter(array_merge([
                "'self'",
                'https://cdn.jsdelivr.net',
            ], $viteHosts, $viteSockets)),
            'frame-ancestors' => ["'none'"],
        ],
        'report_only' => (bool) env('SECURITY_CSP_REPORT_ONLY', false),
        'report_uri' => env('SECURITY_CSP_REPORT_URI'),
    ],
    'secrets' => [
        'rotation_grace_seconds' => (int) env('SECURITY_SECRET_ROTATION_GRACE_SECONDS', 900),
    ],
];
