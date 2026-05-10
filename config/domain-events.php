<?php

$domainBackoff = array_values(array_filter(array_map(
    static fn (string $value) => max(0, (int) trim($value)),
    explode(',', (string) env('DOMAIN_EVENTS_QUEUE_BACKOFF', '60,180,600'))
)));

$followUpBackoff = array_values(array_filter(array_map(
    static fn (string $value) => max(0, (int) trim($value)),
    explode(',', (string) env('DOMAIN_EVENTS_FOLLOW_UP_BACKOFF', '120,300,600'))
)));

return [

    'queue' => [
        'connection' => env(
            'DOMAIN_EVENTS_QUEUE_CONNECTION',
            env('QUEUE_FAILOVER_PRIMARY', env('QUEUE_CONNECTION', 'database'))
        ),
        'name' => env('DOMAIN_EVENTS_QUEUE', 'domain-events'),
        'tries' => (int) env('DOMAIN_EVENTS_QUEUE_TRIES', 5),
        'backoff' => $domainBackoff !== [] ? $domainBackoff : [60, 180, 600],
    ],

    'follow_up_queue' => [
        'connection' => env(
            'DOMAIN_EVENTS_FOLLOW_UP_CONNECTION',
            env('QUEUE_FAILOVER_PRIMARY', env('QUEUE_CONNECTION', 'database'))
        ),
        'name' => env('DOMAIN_EVENTS_FOLLOW_UP_QUEUE', 'monitoring'),
        'tries' => (int) env('DOMAIN_EVENTS_FOLLOW_UP_TRIES', 3),
        'backoff' => $followUpBackoff !== [] ? $followUpBackoff : [120, 300, 600],
    ],

];
