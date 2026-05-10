<?php

return [

    'page_size' => (int) env('MONITORING_PAGE_SIZE', 50),

    'sentry' => [
        'dsn' => env('SENTRY_LARAVEL_DSN', env('SENTRY_DSN')),
        'traces_sample_rate' => env('SENTRY_TRACES_SAMPLE_RATE') === null
            ? null
            : (float) env('SENTRY_TRACES_SAMPLE_RATE'),
        'profiles_sample_rate' => env('SENTRY_PROFILES_SAMPLE_RATE') === null
            ? null
            : (float) env('SENTRY_PROFILES_SAMPLE_RATE'),
    ],

    'statsd' => [
        'enabled' => (bool) env('STATSD_ENABLED', false),
        'host' => env('STATSD_HOST', '127.0.0.1'),
        'port' => (int) env('STATSD_PORT', 8125),
        'prefix' => trim((string) env('STATSD_PREFIX', 'ax4')),
        'timeout' => (float) env('STATSD_TIMEOUT', 0.1),
        'global_tags' => array_values(array_filter(array_map(
            static fn (string $tag) => trim($tag),
            explode(',', (string) env('STATSD_GLOBAL_TAGS', ''))
        ))),
    ],

    'telescope' => [
        'enabled' => env('TELESCOPE_ENABLED', env('APP_ENV') !== 'production'),
        'allowed_emails' => array_values(array_filter(array_map(
            static fn (string $email) => trim($email),
            explode(',', (string) env('TELESCOPE_ALLOWED_EMAILS', ''))
        ))),
        'guard' => env('TELESCOPE_GUARD'),
    ],

    'alerts' => [
        'enabled' => env('EVENT_ALERTS_ENABLED', true),
        'default_channels' => array_values(array_filter(array_map(
            static fn (string $channel) => trim($channel),
            explode(',', (string) env('EVENT_ALERTS_CHANNELS', 'mail,slack'))
        ))),
        'rules' => [
            // 'dispatch.list.closed' => ['severity' => 'info', 'channels' => ['slack']],
            // 'tracking.alert.*' => ['severity' => 'critical', 'channels' => ['mail', 'slack']],
        ],
        'mail' => [
            'enabled' => (bool) env('EVENT_ALERTS_MAIL_ENABLED', false),
            'recipients' => array_values(array_filter(array_map(
                static fn (string $recipient) => trim($recipient),
                explode(',', (string) env('EVENT_ALERTS_MAIL_TO', ''))
            ))),
            'subject_prefix' => env('EVENT_ALERTS_MAIL_SUBJECT_PREFIX', '[AX4 Alert]'),
        ],
        'slack' => [
            'enabled' => (bool) env('EVENT_ALERTS_SLACK_ENABLED', false),
            'webhook' => env('EVENT_ALERTS_SLACK_WEBHOOK'),
            'channel' => env('EVENT_ALERTS_SLACK_CHANNEL'),
        ],
    ],

];
