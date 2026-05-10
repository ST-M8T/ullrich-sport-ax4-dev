<?php

use Illuminate\Support\Str;

return [

    'name' => env('HORIZON_NAME'),

    'domain' => env('HORIZON_DOMAIN'),

    'path' => env('HORIZON_PATH', 'horizon'),

    'use' => env('HORIZON_REDIS_CONNECTION', 'default'),

    'prefix' => env(
        'HORIZON_PREFIX',
        Str::slug(env('APP_NAME', 'laravel'), '_').'_horizon:'
    ),

    'middleware' => ['web'],

    'waits' => [
        'redis:default' => (int) env('HORIZON_WAIT_DEFAULT', 60),
        'redis:domain-events' => (int) env('HORIZON_WAIT_DOMAIN_EVENTS', 30),
        'redis:monitoring' => (int) env('HORIZON_WAIT_MONITORING', 45),
    ],

    'trim' => [
        'recent' => 60,
        'pending' => 60,
        'completed' => 60,
        'recent_failed' => 10080,
        'failed' => 10080,
        'monitored' => 10080,
    ],

    'silenced' => [
        // App\Jobs\ExampleJob::class,
    ],

    'silenced_tags' => [
        // 'notifications',
    ],

    'metrics' => [
        'trim_snapshots' => [
            'job' => 24,
            'queue' => 24,
        ],
    ],

    'fast_termination' => false,

    'memory_limit' => (int) env('HORIZON_MEMORY_LIMIT', 128),

    'defaults' => [
        'domain-events' => [
            'connection' => env('HORIZON_DOMAIN_EVENTS_CONNECTION', env('DOMAIN_EVENTS_QUEUE_CONNECTION', 'redis')),
            'queue' => [env('DOMAIN_EVENTS_QUEUE', 'domain-events')],
            'balance' => env('HORIZON_DOMAIN_EVENTS_BALANCE', 'auto'),
            'autoScalingStrategy' => env('HORIZON_DOMAIN_EVENTS_AUTOSCALE', 'time'),
            'maxProcesses' => (int) env('HORIZON_DOMAIN_EVENTS_MAX_PROCESSES', 3),
            'maxTime' => (int) env('HORIZON_DOMAIN_EVENTS_MAX_TIME', 0),
            'maxJobs' => (int) env('HORIZON_DOMAIN_EVENTS_MAX_JOBS', 0),
            'memory' => (int) env('HORIZON_DOMAIN_EVENTS_MEMORY', 256),
            'tries' => (int) env('DOMAIN_EVENTS_QUEUE_TRIES', 5),
            'timeout' => (int) env('HORIZON_DOMAIN_EVENTS_TIMEOUT', 120),
            'nice' => (int) env('HORIZON_DOMAIN_EVENTS_NICE', 0),
        ],
        'integrations' => [
            'connection' => env('HORIZON_INTEGRATIONS_CONNECTION', env('DOMAIN_EVENTS_FOLLOW_UP_CONNECTION', 'redis')),
            'queue' => array_values(array_filter([
                env('DOMAIN_EVENTS_FOLLOW_UP_QUEUE', 'monitoring'),
                env('HORIZON_INTEGRATIONS_FALLBACK_QUEUE', 'default'),
            ])),
            'balance' => env('HORIZON_INTEGRATIONS_BALANCE', 'simple'),
            'autoScalingStrategy' => env('HORIZON_INTEGRATIONS_AUTOSCALE', 'time'),
            'maxProcesses' => (int) env('HORIZON_INTEGRATIONS_MAX_PROCESSES', 2),
            'maxTime' => (int) env('HORIZON_INTEGRATIONS_MAX_TIME', 0),
            'maxJobs' => (int) env('HORIZON_INTEGRATIONS_MAX_JOBS', 0),
            'memory' => (int) env('HORIZON_INTEGRATIONS_MEMORY', 256),
            'tries' => (int) env('DOMAIN_EVENTS_FOLLOW_UP_TRIES', 3),
            'timeout' => (int) env('HORIZON_INTEGRATIONS_TIMEOUT', 120),
            'nice' => (int) env('HORIZON_INTEGRATIONS_NICE', 0),
        ],
        'default' => [
            'connection' => env(
                'HORIZON_DEFAULT_CONNECTION',
                env('QUEUE_FAILOVER_PRIMARY', env('QUEUE_CONNECTION', 'redis'))
            ),
            'queue' => array_values(array_filter(explode(',', (string) env('HORIZON_DEFAULT_QUEUES', 'default')))),
            'balance' => env('HORIZON_DEFAULT_BALANCE', 'auto'),
            'autoScalingStrategy' => env('HORIZON_DEFAULT_AUTOSCALE', 'time'),
            'maxProcesses' => (int) env('HORIZON_DEFAULT_MAX_PROCESSES', 2),
            'maxTime' => (int) env('HORIZON_DEFAULT_MAX_TIME', 0),
            'maxJobs' => (int) env('HORIZON_DEFAULT_MAX_JOBS', 0),
            'memory' => (int) env('HORIZON_DEFAULT_MEMORY', 128),
            'tries' => (int) env('HORIZON_DEFAULT_TRIES', 1),
            'timeout' => (int) env('HORIZON_DEFAULT_TIMEOUT', 90),
            'nice' => (int) env('HORIZON_DEFAULT_NICE', 0),
        ],
    ],

    'environments' => [
        'production' => [
            'domain-events' => [
                'maxProcesses' => (int) env('HORIZON_DOMAIN_EVENTS_PROCESSES', 10),
                'balanceMaxShift' => (int) env('HORIZON_DOMAIN_EVENTS_BALANCE_MAX_SHIFT', 1),
                'balanceCooldown' => (int) env('HORIZON_DOMAIN_EVENTS_BALANCE_COOLDOWN', 3),
            ],
            'integrations' => [
                'maxProcesses' => (int) env('HORIZON_INTEGRATIONS_PROCESSES', 6),
            ],
            'default' => [
                'maxProcesses' => (int) env('HORIZON_DEFAULT_PROCESSES', 5),
            ],
        ],

        'local' => [
            'domain-events' => [
                'maxProcesses' => (int) env('HORIZON_DOMAIN_EVENTS_LOCAL_PROCESSES', 1),
            ],
            'integrations' => [
                'maxProcesses' => (int) env('HORIZON_INTEGRATIONS_LOCAL_PROCESSES', 1),
            ],
            'default' => [
                'maxProcesses' => (int) env('HORIZON_DEFAULT_LOCAL_PROCESSES', 1),
            ],
        ],
    ],

];
